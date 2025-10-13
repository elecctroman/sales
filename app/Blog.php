<?php

namespace App;

use PDO;
use App\Database;
use App\Helpers;

class Blog
{
    /**
     * Paginate blog posts for listing.
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @param int $offset
     * @param bool $includeDrafts
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public static function paginatePosts(array $filters = array(), int $limit = 12, int $offset = 0, bool $includeDrafts = false): array
    {
        $pdo = Database::connection();
        $conditions = array('bp.deleted_at IS NULL');
        $params = array();

        if (!$includeDrafts) {
            $conditions[] = "bp.status IN ('published','scheduled')";
            $conditions[] = '(bp.status <> \"published\" OR bp.published_at <= NOW())';
        } elseif (!empty($filters['status'])) {
            $conditions[] = 'bp.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $conditions[] = 'bc.slug = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['tag'])) {
            $conditions[] = 'bt.slug = :tag';
            $params['tag'] = $filters['tag'];
        }

        if (!empty($filters['query'])) {
            $conditions[] = '(bp.title LIKE :query OR bp.summary LIKE :query OR bp.content LIKE :query)';
            $params['query'] = '%' . $filters['query'] . '%';
        }

        if (!empty($filters['author'])) {
            $conditions[] = 'bp.created_by = :author';
            $params['author'] = (int)$filters['author'];
        }

        if (!empty($filters['year'])) {
            $conditions[] = 'YEAR(bp.published_at) = :year';
            $params['year'] = (int)$filters['year'];
        }

        if (!empty($filters['month'])) {
            $conditions[] = 'MONTH(bp.published_at) = :month';
            $params['month'] = (int)$filters['month'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = 'SELECT COUNT(DISTINCT bp.id)
                     FROM blog_posts bp
                     LEFT JOIN blog_categories bc ON bc.id = bp.category_id
                     LEFT JOIN blog_post_tags bpt ON bpt.post_id = bp.id
                     LEFT JOIN blog_tags bt ON bt.id = bpt.tag_id
                     ' . $where;

        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = 'SELECT bp.*, bc.name AS category_name, bc.slug AS category_slug, bc.is_active AS category_active,
                       u.name AS author_name, u.email AS author_email
                FROM blog_posts bp
                LEFT JOIN blog_categories bc ON bc.id = bp.category_id
                LEFT JOIN users u ON u.id = bp.created_by
                ' . $where . '
                GROUP BY bp.id
                ORDER BY COALESCE(bp.published_at, bp.created_at) DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        if ($items) {
            $postIds = array_map(static function ($item) {
                return (int)$item['id'];
            }, $items);
            $tags = self::tagsForPosts($postIds);
            foreach ($items as $index => $item) {
                $items[$index]['tags'] = isset($tags[$item['id']]) ? $tags[$item['id']] : array();
            }
        }

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Fetch a published blog post by slug.
     *
     * @param string $slug
     * @return array<string, mixed>|null
     */
    public static function findPublished(string $slug)
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT bp.*, bc.name AS category_name, bc.slug AS category_slug
            FROM blog_posts bp
            LEFT JOIN blog_categories bc ON bc.id = bp.category_id
            WHERE bp.slug = :slug AND bp.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute(array('slug' => $slug));
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) {
            return null;
        }

        if ($post['status'] === 'draft') {
            return null;
        }

        if ($post['status'] === 'scheduled' && (!isset($post['published_at']) || strtotime($post['published_at']) > time())) {
            return null;
        }

        if ($post['status'] === 'published' && isset($post['published_at']) && strtotime($post['published_at']) > time()) {
            return null;
        }

        $postId = (int)$post['id'];
        $post['tags'] = self::tagsForPosts(array($postId))[$postId] ?? array();
        $post['comments'] = self::approvedComments($postId);

        return $post;
    }

    /**
     * Create a blog post record.
     *
     * @param array<string, mixed> $payload
     * @param int|null $userId
     * @return int
     */
    public static function createPost(array $payload, ?int $userId = null): int
    {
        $pdo = Database::connection();
        $data = self::preparePostPayload($payload, null);

        $stmt = $pdo->prepare('INSERT INTO blog_posts (category_id, title, slug, summary, content, cover_image, status, meta_title, meta_description, meta_keywords, published_at, reading_time, canonical_url, created_at, created_by) VALUES (:category_id, :title, :slug, :summary, :content, :cover_image, :status, :meta_title, :meta_description, :meta_keywords, :published_at, :reading_time, :canonical_url, NOW(), :created_by)');
        $stmt->execute(array(
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'summary' => $data['summary'],
            'content' => $data['content'],
            'cover_image' => $data['cover_image'],
            'status' => $data['status'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'meta_keywords' => $data['meta_keywords'],
            'published_at' => $data['published_at'],
            'reading_time' => $data['reading_time'],
            'canonical_url' => $data['canonical_url'],
            'created_by' => $userId,
        ));

        $postId = (int)$pdo->lastInsertId();
        self::syncTags($postId, $data['tags']);

        return $postId;
    }

    /**
     * Update an existing blog post.
     */
    public static function updatePost(int $postId, array $payload, ?int $userId = null): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $pdo = Database::connection();
        $existing = self::findPost($postId);
        if (!$existing) {
            return false;
        }

        $data = self::preparePostPayload($payload, $postId);

        $stmt = $pdo->prepare('UPDATE blog_posts SET category_id = :category_id, title = :title, slug = :slug, summary = :summary, content = :content, cover_image = :cover_image, status = :status, meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords, published_at = :published_at, reading_time = :reading_time, canonical_url = :canonical_url, updated_at = NOW(), updated_by = :updated_by WHERE id = :id');
        $success = $stmt->execute(array(
            'id' => $postId,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'summary' => $data['summary'],
            'content' => $data['content'],
            'cover_image' => $data['cover_image'],
            'status' => $data['status'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'meta_keywords' => $data['meta_keywords'],
            'published_at' => $data['published_at'],
            'reading_time' => $data['reading_time'],
            'canonical_url' => $data['canonical_url'],
            'updated_by' => $userId,
        ));

        if ($success) {
            self::syncTags($postId, $data['tags']);
        }

        return $success;
    }

    /**
     * Soft delete a blog post.
     */
    public static function deletePost(int $postId, ?int $userId = null): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE blog_posts SET deleted_at = NOW(), updated_at = NOW(), updated_by = :user WHERE id = :id');
        return $stmt->execute(array('id' => $postId, 'user' => $userId));
    }

    /**
     * Update status of blog post.
     */
    public static function setStatus(int $postId, string $status, ?string $publishAt = null, ?int $userId = null): bool
    {
        $valid = array('draft', 'scheduled', 'published', 'archived');
        if (!in_array($status, $valid, true)) {
            $status = 'draft';
        }

        $publishedAt = null;
        if ($publishAt) {
            $timestamp = strtotime($publishAt);
            if ($timestamp !== false) {
                $publishedAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE blog_posts SET status = :status, published_at = :published_at, updated_at = NOW(), updated_by = :user WHERE id = :id');

        return $stmt->execute(array(
            'id' => $postId,
            'status' => $status,
            'published_at' => $publishedAt,
            'user' => $userId,
        ));
    }

    /**
     * Retrieve categories.
     *
     * @param bool $onlyActive
     * @return array<int, array<string, mixed>>
     */
    public static function categories(bool $onlyActive = false): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM blog_categories WHERE deleted_at IS NULL';
        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * Retrieve tag cloud with usage count.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function tags(int $limit = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT bt.*, COUNT(bpt.post_id) AS usage_count FROM blog_tags bt LEFT JOIN blog_post_tags bpt ON bpt.tag_id = bt.id GROUP BY bt.id ORDER BY usage_count DESC, bt.name ASC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * Create or update a category.
     *
     * @param array<string, mixed> $payload
     * @param int|null $categoryId
     * @param int|null $userId
     * @return int
     */
    public static function saveCategory(array $payload, ?int $categoryId = null, ?int $userId = null): int
    {
        $name = isset($payload['name']) ? trim((string)$payload['name']) : '';
        $slug = isset($payload['slug']) ? trim((string)$payload['slug']) : $name;
        $description = isset($payload['description']) ? trim((string)$payload['description']) : null;
        $isActive = !empty($payload['is_active']) ? 1 : 0;

        $slug = self::uniqueCategorySlug($slug, $categoryId);

        $pdo = Database::connection();
        if ($categoryId) {
            $stmt = $pdo->prepare('UPDATE blog_categories SET name = :name, slug = :slug, description = :description, is_active = :is_active, updated_at = NOW(), updated_by = :user WHERE id = :id');
            $stmt->execute(array(
                'id' => $categoryId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => $isActive,
                'user' => $userId,
            ));

            return $categoryId;
        }

        $stmt = $pdo->prepare('INSERT INTO blog_categories (name, slug, description, is_active, created_at, created_by) VALUES (:name, :slug, :description, :is_active, NOW(), :user)');
        $stmt->execute(array(
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'is_active' => $isActive,
            'user' => $userId,
        ));

        return (int)$pdo->lastInsertId();
    }

    /**
     * Soft delete a category.
     */
    public static function deleteCategory(int $categoryId, ?int $userId = null): bool
    {
        if ($categoryId <= 0) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE blog_categories SET deleted_at = NOW(), updated_at = NOW(), updated_by = :user WHERE id = :id');

        return $stmt->execute(array('id' => $categoryId, 'user' => $userId));
    }

    /**
     * Persist a comment and return its ID.
     */
    public static function addComment(int $postId, array $payload, ?int $userId = null): int
    {
        $authorName = isset($payload['author_name']) ? trim((string)$payload['author_name']) : null;
        $authorEmail = isset($payload['author_email']) ? trim((string)$payload['author_email']) : null;
        $content = isset($payload['content']) ? trim((string)$payload['content']) : '';
        $status = isset($payload['status']) ? (string)$payload['status'] : 'pending';

        if ($content === '') {
            return 0;
        }

        $validStatuses = array('pending', 'approved', 'rejected', 'spam');
        if (!in_array($status, $validStatuses, true)) {
            $status = 'pending';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO blog_comments (post_id, user_id, author_name, author_email, content, status, created_at, created_by) VALUES (:post_id, :user_id, :author_name, :author_email, :content, :status, NOW(), :created_by)');
        $stmt->execute(array(
            'post_id' => $postId,
            'user_id' => $userId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'content' => $content,
            'status' => $status,
            'created_by' => $userId,
        ));

        return (int)$pdo->lastInsertId();
    }

    /**
     * Update a comment status.
     */
    public static function updateCommentStatus(int $commentId, string $status, ?int $userId = null): bool
    {
        $validStatuses = array('pending', 'approved', 'rejected', 'spam');
        if (!in_array($status, $validStatuses, true)) {
            $status = 'pending';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE blog_comments SET status = :status, updated_at = NOW(), updated_by = :user WHERE id = :id');
        return $stmt->execute(array('status' => $status, 'user' => $userId, 'id' => $commentId));
    }

    /**
     * Fetch recent published posts.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function recentPosts(int $limit = 5): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT bp.*, bc.slug AS category_slug
            FROM blog_posts bp
            LEFT JOIN blog_categories bc ON bc.id = bp.category_id
            WHERE bp.deleted_at IS NULL
              AND bp.status = \"published\"
              AND (bp.published_at IS NULL OR bp.published_at <= NOW())
            ORDER BY COALESCE(bp.published_at, bp.created_at) DESC
            LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

        if ($items) {
            $postIds = array_map(static function ($item) {
                return (int)$item['id'];
            }, $items);
            $tags = self::tagsForPosts($postIds);
            foreach ($items as $index => $item) {
                $items[$index]['tags'] = isset($tags[$item['id']]) ? $tags[$item['id']] : array();
            }
        }

        return $items;
    }

    /**
     * Retrieve approved comments for a post.
     *
     * @param int $postId
     * @return array<int, array<string, mixed>>
     */
    public static function approvedComments(int $postId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM blog_comments WHERE post_id = :post_id AND status = \"approved\" ORDER BY created_at DESC');
        $stmt->execute(array('post_id' => $postId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * Fetch raw post data for internal use.
     *
     * @param int $postId
     * @return array<string, mixed>|null
     */
    public static function findPost(int $postId)
    {
        if ($postId <= 0) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $postId));
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($post) {
            $post['tags'] = self::tagsForPosts(array($postId))[$postId] ?? array();
        }

        return $post ?: null;
    }

    /**
     * Generate a unique slug for posts.
     */
    public static function uniquePostSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Helpers::slugify($slug);
        if ($base === '') {
            $base = 'yazi';
        }

        $candidate = $base;
        $index = 1;
        $pdo = Database::connection();

        while (true) {
            $params = array('slug' => $candidate);
            $sql = 'SELECT id FROM blog_posts WHERE slug = :slug AND deleted_at IS NULL';
            if ($ignoreId) {
                $sql .= ' AND id <> :ignore';
                $params['ignore'] = $ignoreId;
            }

            $stmt = $pdo->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }

            $candidate = $base . '-' . ++$index;
        }
    }

    /**
     * Generate a unique slug for categories.
     */
    public static function uniqueCategorySlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Helpers::slugify($slug);
        if ($base === '') {
            $base = 'kategori';
        }

        $candidate = $base;
        $index = 1;
        $pdo = Database::connection();

        while (true) {
            $params = array('slug' => $candidate);
            $sql = 'SELECT id FROM blog_categories WHERE slug = :slug AND deleted_at IS NULL';
            if ($ignoreId) {
                $sql .= ' AND id <> :ignore';
                $params['ignore'] = $ignoreId;
            }

            $stmt = $pdo->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }

            $candidate = $base . '-' . ++$index;
        }
    }

    /**
     * Normalise and prepare post payload.
     *
     * @param array<string, mixed> $payload
     * @param int|null $postId
     * @return array<string, mixed>
     */
    private static function preparePostPayload(array $payload, ?int $postId): array
    {
        $title = isset($payload['title']) ? trim((string)$payload['title']) : '';
        $slug = isset($payload['slug']) ? trim((string)$payload['slug']) : $title;
        $summary = isset($payload['summary']) ? trim((string)$payload['summary']) : null;
        $content = isset($payload['content']) ? (string)$payload['content'] : null;
        $status = isset($payload['status']) ? (string)$payload['status'] : 'draft';
        $metaTitle = isset($payload['meta_title']) ? trim((string)$payload['meta_title']) : null;
        $metaDescription = isset($payload['meta_description']) ? trim((string)$payload['meta_description']) : null;
        $metaKeywords = isset($payload['meta_keywords']) ? trim((string)$payload['meta_keywords']) : null;
        $coverImage = isset($payload['cover_image']) ? trim((string)$payload['cover_image']) : null;
        $categoryId = isset($payload['category_id']) ? (int)$payload['category_id'] : null;
        if ($categoryId <= 0) {
            $categoryId = null;
        }

        $readingTime = isset($payload['reading_time']) ? (int)$payload['reading_time'] : null;
        if ($readingTime !== null && $readingTime <= 0) {
            $readingTime = null;
        }

        $canonicalUrl = isset($payload['canonical_url']) ? trim((string)$payload['canonical_url']) : null;

        $validStatuses = array('draft', 'scheduled', 'published', 'archived');
        if (!in_array($status, $validStatuses, true)) {
            $status = 'draft';
        }

        $publishedAt = null;
        if (!empty($payload['published_at'])) {
            $timestamp = strtotime((string)$payload['published_at']);
            if ($timestamp !== false) {
                $publishedAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $tags = array();
        if (!empty($payload['tags'])) {
            if (is_array($payload['tags'])) {
                $tags = $payload['tags'];
            } else {
                $tags = preg_split('/[,;]+/', (string)$payload['tags']);
            }
            $tags = array_filter(array_map(static function ($tag) {
                return Helpers::slugify(trim((string)$tag));
            }, $tags));
        }

        $uniqueSlug = self::uniquePostSlug($slug, $postId);

        return array(
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $uniqueSlug,
            'summary' => $summary,
            'content' => $content,
            'cover_image' => $coverImage,
            'status' => $status,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'published_at' => $publishedAt,
            'reading_time' => $readingTime,
            'canonical_url' => $canonicalUrl,
            'tags' => $tags,
        );
    }

    /**
     * Load tags for the given posts.
     *
     * @param array<int, int> $postIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function tagsForPosts(array $postIds): array
    {
        $postIds = array_filter(array_map('intval', $postIds));
        if (!$postIds) {
            return array();
        }

        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare('SELECT bt.*, bpt.post_id FROM blog_tags bt INNER JOIN blog_post_tags bpt ON bpt.tag_id = bt.id WHERE bpt.post_id IN (' . $placeholders . ')');
        $stmt->execute($postIds);

        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $postId = (int)$row['post_id'];
            if (!isset($result[$postId])) {
                $result[$postId] = array();
            }
            $result[$postId][] = $row;
        }

        return $result;
    }

    /**
     * Sync post tags by slug list.
     *
     * @param int $postId
     * @param array<int, string> $tags
     * @return void
     */
    private static function syncTags(int $postId, array $tags): void
    {
        $pdo = Database::connection();
        $normalised = array();
        foreach ($tags as $tag) {
            $slug = Helpers::slugify((string)$tag);
            if ($slug === '') {
                continue;
            }
            $normalised[$slug] = $slug;
        }

        $pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :post_id')->execute(array('post_id' => $postId));
        if (!$normalised) {
            return;
        }

        $existingStmt = $pdo->prepare('SELECT id, slug FROM blog_tags WHERE slug IN (' . implode(',', array_fill(0, count($normalised), '?')) . ')');
        $existingStmt->execute(array_values($normalised));
        $existing = array();
        while ($row = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['slug']] = (int)$row['id'];
        }

        $tagIds = array();
        foreach ($normalised as $slug) {
            if (isset($existing[$slug])) {
                $tagIds[] = $existing[$slug];
                continue;
            }
            $insert = $pdo->prepare('INSERT INTO blog_tags (name, slug, created_at) VALUES (:name, :slug, NOW())');
            $label = trim(preg_replace('#\s+#', ' ', ucwords(str_replace('-', ' ', $slug))));
            if ($label === '') {
                $label = strtoupper($slug);
            }
            $insert->execute(array('name' => $label, 'slug' => $slug));
            $tagIds[] = (int)$pdo->lastInsertId();
        }

        $insertValues = array();
        foreach ($tagIds as $tagId) {
            $insertValues[] = '(' . (int)$postId . ', ' . (int)$tagId . ')';
        }

        if ($insertValues) {
            $pdo->exec('INSERT INTO blog_post_tags (post_id, tag_id) VALUES ' . implode(',', $insertValues));
        }
    }
}

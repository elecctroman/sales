<?php

namespace App;

use PDO;
use App\Database;
use App\Helpers;


class Page
{
    /**
     * Retrieve a paginated list of pages for the admin panel.
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @param int $offset
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(array $filters = array(), int $limit = 25, int $offset = 0): array
    {
        $pdo = Database::connection();

        $conditions = array('p.deleted_at IS NULL');
        $params = array();

        if (!empty($filters['status']) && in_array($filters['status'], array('draft', 'scheduled', 'published', 'archived'), true)) {
            $conditions[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['visibility']) && in_array($filters['visibility'], array('public', 'private'), true)) {
            $conditions[] = 'p.visibility = :visibility';
            $params['visibility'] = $filters['visibility'];
        }

        if (!empty($filters['query'])) {
            $conditions[] = '(p.title LIKE :query OR p.slug LIKE :query OR p.meta_title LIKE :query)';
            $params['query'] = '%' . $filters['query'] . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM pages p ' . $where);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = 'SELECT p.*, parent.title AS parent_title
                FROM pages p
                LEFT JOIN pages parent ON parent.id = p.parent_id
                ' . $where . '
                ORDER BY COALESCE(p.published_at, p.created_at) DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Retrieve a hierarchical list of pages for parent selection UIs.
     *
     * @param int|null $excludeId
     * @return array<int, array{id: int, title: string, depth: int}>
     */
    public static function hierarchyOptions(?int $excludeId = null): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id, parent_id, title FROM pages WHERE deleted_at IS NULL ORDER BY COALESCE(parent_id, 0) ASC, title ASC');

        $tree = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pageId = (int)$row['id'];
            if ($excludeId !== null && $pageId === $excludeId) {
                continue;
            }

            $parentId = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
            if (!isset($tree[$parentId])) {
                $tree[$parentId] = array();
            }

            $tree[$parentId][] = array(
                'id' => $pageId,
                'title' => (string)$row['title'],
            );
        }

        $ordered = array();
        $walker = function (int $parentId, int $depth) use (&$walker, &$tree, &$ordered) {
            if (empty($tree[$parentId])) {
                return;
            }

            foreach ($tree[$parentId] as $node) {
                $ordered[] = array(
                    'id' => (int)$node['id'],
                    'title' => (string)$node['title'],
                    'depth' => $depth,
                );

                $walker($node['id'], $depth + 1);
            }
        };

        $walker(0, 0);

        return $ordered;
    }

    /**
     * Fetch a published page by slug.
     *
     * @param string $slug
     * @return array<string, mixed>|null
     */
    public static function findPublishedBySlug(string $slug)
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = :slug AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('slug' => $slug));
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$page) {
            return null;
        }

        if ($page['visibility'] === 'private') {
            return null;
        }

        if ($page['status'] === 'published') {
            if (!empty($page['published_at']) && strtotime($page['published_at']) > time()) {
                return null;
            }

            return $page;
        }

        if ($page['status'] === 'scheduled') {
            if (!empty($page['published_at']) && strtotime($page['published_at']) <= time()) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Fetch a page by id.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public static function find(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));

        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        return $page ?: null;
    }

    /**
     * Create a new page record.
     *
     * @param array<string, mixed> $data
     * @param int|null $userId
     * @return int
     */
    public static function create(array $data, ?int $userId = null): int
    {
        $pdo = Database::connection();

        $payload = self::preparePayload($data, null);

        $columns = array(
            'title', 'slug', 'summary', 'content', 'status', 'visibility', 'meta_title', 'meta_description', 'meta_keywords',
            'template', 'hero_image', 'parent_id', 'published_at', 'created_at', 'created_by'
        );

        $placeholders = array(
            ':title', ':slug', ':summary', ':content', ':status', ':visibility', ':meta_title', ':meta_description', ':meta_keywords',
            ':template', ':hero_image', ':parent_id', ':published_at', 'NOW()', ':created_by'
        );

        $stmt = $pdo->prepare('INSERT INTO pages (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')');
        $stmt->execute(array(
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'summary' => $payload['summary'],
            'content' => $payload['content'],
            'status' => $payload['status'],
            'visibility' => $payload['visibility'],
            'meta_title' => $payload['meta_title'],
            'meta_description' => $payload['meta_description'],
            'meta_keywords' => $payload['meta_keywords'],
            'template' => $payload['template'],
            'hero_image' => $payload['hero_image'],
            'parent_id' => $payload['parent_id'],
            'published_at' => $payload['published_at'],
            'created_by' => $userId,
        ));

        $pageId = (int)$pdo->lastInsertId();
        self::recordRevision($pageId, $payload, $userId);

        return $pageId;
    }

    /**
     * Update an existing page record.
     *
     * @param int $pageId
     * @param array<string, mixed> $data
     * @param int|null $userId
     * @return bool
     */
    public static function update(int $pageId, array $data, ?int $userId = null): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        $existing = self::find($pageId);
        if (!$existing) {
            return false;
        }

        $pdo = Database::connection();
        $payload = self::preparePayload($data, $pageId);

        $set = array(
            'title = :title',
            'slug = :slug',
            'summary = :summary',
            'content = :content',
            'status = :status',
            'visibility = :visibility',
            'meta_title = :meta_title',
            'meta_description = :meta_description',
            'meta_keywords = :meta_keywords',
            'template = :template',
            'hero_image = :hero_image',
            'parent_id = :parent_id',
            'published_at = :published_at',
            'updated_at = NOW()',
            'updated_by = :updated_by',
        );

        $stmt = $pdo->prepare('UPDATE pages SET ' . implode(',', $set) . ' WHERE id = :id');
        $success = $stmt->execute(array(
            'id' => $pageId,
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'summary' => $payload['summary'],
            'content' => $payload['content'],
            'status' => $payload['status'],
            'visibility' => $payload['visibility'],
            'meta_title' => $payload['meta_title'],
            'meta_description' => $payload['meta_description'],
            'meta_keywords' => $payload['meta_keywords'],
            'template' => $payload['template'],
            'hero_image' => $payload['hero_image'],
            'parent_id' => $payload['parent_id'],
            'published_at' => $payload['published_at'],
            'updated_by' => $userId,
        ));

        if ($success) {
            self::recordRevision($pageId, $payload, $userId);
        }

        return $success;
    }

    /**
     * Soft delete a page.
     */
    public static function delete(int $pageId, ?int $userId = null): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE pages SET deleted_at = NOW(), status = "archived", updated_at = NOW(), updated_by = :user WHERE id = :id');

        return $stmt->execute(array('id' => $pageId, 'user' => $userId));
    }

    /**
     * Update publication status for a page.
     */
    public static function setStatus(int $pageId, string $status, ?string $publishAt = null, ?int $userId = null): bool
    {
        $validStatuses = array('draft', 'scheduled', 'published', 'archived');
        if (!in_array($status, $validStatuses, true)) {
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
        $stmt = $pdo->prepare('UPDATE pages SET status = :status, published_at = :published_at, updated_at = NOW(), updated_by = :user WHERE id = :id');

        return $stmt->execute(array(
            'id' => $pageId,
            'status' => $status,
            'published_at' => $publishedAt,
            'user' => $userId,
        ));
    }

    /**
     * Ensure slug uniqueness for pages.
     */
    public static function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Helpers::slugify($slug);
        if ($base === '') {
            $base = 'sayfa';
        }

        $candidate = $base;
        $index = 1;
        $pdo = Database::connection();

        while (true) {
            $params = array('slug' => $candidate);
            $sql = 'SELECT id FROM pages WHERE slug = :slug AND deleted_at IS NULL';
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
     * @param array<string, mixed> $payload
     * @param int|null $pageId
     * @return array<string, mixed>
     */
    private static function preparePayload(array $payload, ?int $pageId): array
    {
        $title = isset($payload['title']) ? trim((string)$payload['title']) : '';
        $slug = isset($payload['slug']) ? trim((string)$payload['slug']) : $title;
        $summary = isset($payload['summary']) ? trim((string)$payload['summary']) : null;
        $content = isset($payload['content']) ? (string)$payload['content'] : null;
        $status = isset($payload['status']) ? (string)$payload['status'] : 'draft';
        $visibility = isset($payload['visibility']) ? (string)$payload['visibility'] : 'public';
        $metaTitle = isset($payload['meta_title']) ? trim((string)$payload['meta_title']) : null;
        $metaDescription = isset($payload['meta_description']) ? trim((string)$payload['meta_description']) : null;
        $metaKeywords = isset($payload['meta_keywords']) ? trim((string)$payload['meta_keywords']) : null;
        $template = isset($payload['template']) ? trim((string)$payload['template']) : 'default';
        $heroImage = isset($payload['hero_image']) ? trim((string)$payload['hero_image']) : null;
        $parentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : null;
        if ($parentId <= 0) {
            $parentId = null;
        }
        if ($pageId !== null && $parentId === $pageId) {
            $parentId = null;
        }

        $validStatuses = array('draft', 'scheduled', 'published', 'archived');
        if (!in_array($status, $validStatuses, true)) {
            $status = 'draft';
        }

        $validVisibility = array('public', 'private');
        if (!in_array($visibility, $validVisibility, true)) {
            $visibility = 'public';
        }

        $uniqueSlug = self::uniqueSlug($slug, $pageId);

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

        return array(
            'title' => $title,
            'slug' => $uniqueSlug,
            'summary' => $summary,
            'content' => $content,
            'status' => $status,
            'visibility' => $visibility,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'template' => $template,
            'hero_image' => $heroImage,
            'parent_id' => $parentId,
            'published_at' => $publishedAt,
        );
    }

    /**
     * Persist a revision snapshot for audit history.
     *
     * @param int $pageId
     * @param array<string, mixed> $payload
     * @param int|null $userId
     * @return void
     */
    private static function recordRevision(int $pageId, array $payload, ?int $userId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO page_revisions (page_id, title, summary, content, meta_title, meta_description, meta_keywords, template, hero_image, created_by, created_at) VALUES (:page_id, :title, :summary, :content, :meta_title, :meta_description, :meta_keywords, :template, :hero_image, :created_by, NOW())');
        $stmt->execute(array(
            'page_id' => $pageId,
            'title' => $payload['title'],
            'summary' => $payload['summary'],
            'content' => $payload['content'],
            'meta_title' => $payload['meta_title'],
            'meta_description' => $payload['meta_description'],
            'meta_keywords' => $payload['meta_keywords'],
            'template' => $payload['template'],
            'hero_image' => $payload['hero_image'],
            'created_by' => $userId,
        ));
    }
}

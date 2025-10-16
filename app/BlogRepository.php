<?php

namespace App;

use App\Database;
use App\Helpers;
use PDO;

class BlogRepository
{
    /**
     * @var bool
     */
    private static $schemaEnsured = false;

    /**
     * Ensure the database schema for blog posts exists.
     *
     * @return void
     */
    private static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            self::$schemaEnsured = true;
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            excerpt TEXT NULL,
            content LONGTEXT NOT NULL,
            author_name VARCHAR(150) NULL,
            featured_image VARCHAR(255) NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(255) NULL,
            seo_keywords TEXT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_blog_posts_published (is_published, published_at),
            INDEX idx_blog_posts_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        if (!Database::tableHasColumn('blog_posts', 'author_name')) {
            $pdo->exec('ALTER TABLE blog_posts ADD COLUMN author_name VARCHAR(150) NULL AFTER content');
        }

        if (!Database::tableHasColumn('blog_posts', 'published_at')) {
            $pdo->exec('ALTER TABLE blog_posts ADD COLUMN published_at DATETIME NULL AFTER is_published');
        }

        if (!Database::tableHasColumn('blog_posts', 'excerpt')) {
            $pdo->exec('ALTER TABLE blog_posts ADD COLUMN excerpt TEXT NULL AFTER slug');
        }

        self::$schemaEnsured = true;
    }

    /**
     * Retrieve a collection of published posts for the public site.
     *
     * @param int $limit
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    public static function listPublished(int $limit = 6, int $offset = 0): array
    {
        self::ensureSchema();

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return array();
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC, created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

        return array_map(function (array $row): array {
            return self::normaliseRow($row);
        }, $rows);
    }

    /**
     * Paginate published posts for the blog listing.
     *
     * @param int $page
     * @param int $perPage
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,pages:int}
     */
    public static function paginatePublished(int $page = 1, int $perPage = 9): array
    {
        self::ensureSchema();

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return array('items' => array(), 'total' => 0, 'page' => $page, 'pages' => 0);
        }

        $countStmt = $pdo->query('SELECT COUNT(*) FROM blog_posts WHERE is_published = 1');
        $total = (int)$countStmt->fetchColumn();

        $pages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        if ($pages > 0 && $page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;
        if ($offset < 0) {
            $offset = 0;
        }

        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC, created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(function (array $row): array {
            return self::normaliseRow($row);
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array());

        return array(
            'items' => $items,
            'total' => $total,
            'page' => $pages === 0 ? 1 : $page,
            'pages' => $pages,
        );
    }

    /**
     * Locate a published post by slug.
     *
     * @param string $slug
     * @return array<string,mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        self::ensureSchema();

        $slug = Helpers::slugify($slug);
        if ($slug === '') {
            return null;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE slug = :slug AND is_published = 1 LIMIT 1');
        $stmt->execute(array('slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return self::normaliseRow($row);
    }

    /**
     * Locate a post by its primary identifier regardless of status.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function findById(int $id): ?array
    {
        self::ensureSchema();

        if ($id <= 0) {
            return null;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normaliseRow($row) : null;
    }

    /**
     * Persist a blog post. When id is present, the record will be updated.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function save(array $payload): array
    {
        self::ensureSchema();

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Veritabanı bağlantısı kurulamadı.');
        }

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $title = isset($payload['title']) ? trim((string)$payload['title']) : '';
        $slugInput = isset($payload['slug']) ? trim((string)$payload['slug']) : '';
        $excerpt = isset($payload['excerpt']) ? trim((string)$payload['excerpt']) : '';
        $content = isset($payload['content']) ? (string)$payload['content'] : '';
        $author = isset($payload['author_name']) ? trim((string)$payload['author_name']) : '';
        $featured = isset($payload['featured_image']) ? trim((string)$payload['featured_image']) : '';
        $seoTitle = isset($payload['seo_title']) ? trim((string)$payload['seo_title']) : '';
        $seoDescription = isset($payload['seo_description']) ? trim((string)$payload['seo_description']) : '';
        $seoKeywords = isset($payload['seo_keywords']) ? trim((string)$payload['seo_keywords']) : '';
        $isPublished = !empty($payload['is_published']);
        $publishedAt = isset($payload['published_at']) ? trim((string)$payload['published_at']) : '';

        if ($title === '') {
            throw new \InvalidArgumentException('Başlık alanı zorunludur.');
        }

        if ($content === '') {
            throw new \InvalidArgumentException('İçerik alanı boş olamaz.');
        }

        if ($author !== '' && mb_strlen($author) > 150) {
            throw new \InvalidArgumentException('Yazar adı 150 karakterden uzun olamaz.');
        }

        if ($seoTitle !== '' && mb_strlen($seoTitle) > 255) {
            throw new \InvalidArgumentException('SEO başlığı 255 karakterden uzun olamaz.');
        }

        if ($seoDescription !== '' && mb_strlen($seoDescription) > 255) {
            throw new \InvalidArgumentException('SEO açıklaması 255 karakterden uzun olamaz.');
        }

        $slug = $slugInput !== '' ? Helpers::slugify($slugInput) : Helpers::slugify($title);
        if ($slug === '') {
            $slug = 'makale-' . bin2hex(random_bytes(4));
        }

        $slug = self::uniqueSlug($slug, $id ?: null);

        if ($excerpt === '' && $content !== '') {
            $excerpt = strip_tags($content);
        }

        if ($excerpt !== '') {
            $excerpt = mb_substr($excerpt, 0, 600);
        }

        if ($featured !== '' && stripos($featured, 'http') !== 0 && strpos($featured, '/') !== 0) {
            $featured = '/' . ltrim($featured, '/');
        }

        if ($author === '') {
            $author = 'OyunHesap İçerik Ekibi';
        }

        $now = date('Y-m-d H:i:s');
        $finalPublishedAt = $isPublished ? ($publishedAt !== '' ? $publishedAt : $now) : ($publishedAt !== '' ? $publishedAt : null);

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE blog_posts SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, author_name = :author, featured_image = :featured, seo_title = :seo_title, seo_description = :seo_description, seo_keywords = :seo_keywords, is_published = :is_published, published_at = :published_at, updated_at = NOW() WHERE id = :id');
            $stmt->execute(array(
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'content' => $content,
                'author' => $author !== '' ? $author : null,
                'featured' => $featured !== '' ? $featured : null,
                'seo_title' => $seoTitle !== '' ? $seoTitle : null,
                'seo_description' => $seoDescription !== '' ? $seoDescription : null,
                'seo_keywords' => $seoKeywords !== '' ? $seoKeywords : null,
                'is_published' => $isPublished ? 1 : 0,
                'published_at' => $finalPublishedAt,
                'id' => $id,
            ));
        } else {
            $stmt = $pdo->prepare('INSERT INTO blog_posts (title, slug, excerpt, content, author_name, featured_image, seo_title, seo_description, seo_keywords, is_published, published_at, created_at) VALUES (:title, :slug, :excerpt, :content, :author, :featured, :seo_title, :seo_description, :seo_keywords, :is_published, :published_at, :created_at)');
            $stmt->execute(array(
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'content' => $content,
                'author' => $author !== '' ? $author : null,
                'featured' => $featured !== '' ? $featured : null,
                'seo_title' => $seoTitle !== '' ? $seoTitle : null,
                'seo_description' => $seoDescription !== '' ? $seoDescription : null,
                'seo_keywords' => $seoKeywords !== '' ? $seoKeywords : null,
                'is_published' => $isPublished ? 1 : 0,
                'published_at' => $finalPublishedAt,
                'created_at' => $now,
            ));
            $id = (int)$pdo->lastInsertId();
        }

        $saved = self::findById($id);

        if (!$saved) {
            throw new \RuntimeException('Blog yazısı kaydedilemedi.');
        }

        return $saved;
    }

    /**
     * Remove a post permanently.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        self::ensureSchema();

        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
        return $stmt->execute(array('id' => $id));
    }

    /**
     * Update the publication status of a post.
     *
     * @param int $id
     * @param bool $published
     * @return bool
     */
    public static function setPublished(int $id, bool $published): bool
    {
        self::ensureSchema();

        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return false;
        }

        $publishedAt = $published ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare('UPDATE blog_posts SET is_published = :published, published_at = :published_at, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(array(
            'published' => $published ? 1 : 0,
            'published_at' => $publishedAt,
            'id' => $id,
        ));
    }

    /**
     * Retrieve all posts for the administration panel.
     *
     * @param int $limit
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    public static function all(int $limit = 50, int $offset = 0): array
    {
        self::ensureSchema();

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return array();
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $stmt = $pdo->prepare('SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

        return array_map(function (array $row): array {
            return self::normaliseRow($row);
        }, $rows);
    }

    /**
     * Ensure the slug is unique within the table.
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return string
     */
    private static function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return $slug;
        }

        $base = $slug;
        $counter = 1;

        $query = 'SELECT COUNT(*) FROM blog_posts WHERE slug = :slug';
        if ($ignoreId !== null) {
            $query .= ' AND id != :id';
        }
        $stmt = $pdo->prepare($query);

        while (true) {
            $params = array('slug' => $slug);
            if ($ignoreId !== null) {
                $params['id'] = $ignoreId;
            }
            $stmt->execute($params);
            $count = (int)$stmt->fetchColumn();

            if ($count === 0) {
                return $slug;
            }

            $counter++;
            $slug = $base . '-' . $counter;
        }
    }

    /**
     * Normalise a database row for template consumption.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normaliseRow(array $row): array
    {
        $publishedAt = isset($row['published_at']) && $row['published_at'] !== null
            ? (string)$row['published_at']
            : null;

        return array(
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'title' => isset($row['title']) ? (string)$row['title'] : '',
            'slug' => isset($row['slug']) ? (string)$row['slug'] : '',
            'excerpt' => isset($row['excerpt']) ? (string)$row['excerpt'] : '',
            'content' => isset($row['content']) ? (string)$row['content'] : '',
            'author_name' => isset($row['author_name']) ? (string)$row['author_name'] : null,
            'featured_image' => isset($row['featured_image']) ? (string)$row['featured_image'] : null,
            'seo_title' => isset($row['seo_title']) ? (string)$row['seo_title'] : null,
            'seo_description' => isset($row['seo_description']) ? (string)$row['seo_description'] : null,
            'seo_keywords' => isset($row['seo_keywords']) ? (string)$row['seo_keywords'] : null,
            'is_published' => !empty($row['is_published']),
            'published_at' => $publishedAt,
            'created_at' => isset($row['created_at']) ? (string)$row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : null,
            'date_label' => $publishedAt ? date('d M Y', strtotime($publishedAt)) : null,
            'url' => Helpers::absoluteUrl('/blog/' . rawurlencode(isset($row['slug']) ? (string)$row['slug'] : '')),
        );
    }
}


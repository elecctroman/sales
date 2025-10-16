<?php

namespace App\Services;

use App\Database;
use App\BlogRepository;
use App\Helpers;
use App\Settings;
use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class ContentAutomationService
{
    /**
     * Generate missing product descriptions and short descriptions.
     *
     * @param int $limit
     * @return array<string,mixed>
     */
    public static function generateMissingProductDescriptions($limit = 20)
    {
        $pdo = Database::connection();
        $limit = max(1, (int)$limit);

        $query = 'SELECT p.id, p.name, p.description, p.short_description, c.name AS category_name '
            . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id '
            . 'WHERE (p.description IS NULL OR p.description = \'\' OR p.short_description IS NULL OR p.short_description = \'\') '
            . 'ORDER BY p.updated_at IS NULL DESC, p.id ASC LIMIT ' . $limit;

        $stmt = $pdo->query($query);

        $keywords = self::keywords();
        $results = array(
            'processed' => 0,
            'updated' => 0,
            'errors' => array(),
        );

        while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $results['processed']++;
            try {
                $payload = self::requestStructuredContent('product_description', self::buildProductPrompt($row, $keywords));
                $description = isset($payload['description']) ? (string)$payload['description'] : '';
                $short = isset($payload['short_description']) ? (string)$payload['short_description'] : '';

                if ($description === '' && isset($payload['content'])) {
                    $description = (string)$payload['content'];
                }

                if ($description === '') {
                    throw new RuntimeException('İçerik boş döndü.');
                }

                if ($short === '') {
                    $short = Helpers::truncate($description, 180);
                }

                $update = $pdo->prepare('UPDATE products SET description = :description, short_description = :short, updated_at = NOW() WHERE id = :id');
                $update->execute(array(
                    'description' => $description,
                    'short' => $short,
                    'id' => (int)$row['id'],
                ));

                $results['updated']++;
            } catch (\Throwable $exception) {
                $results['errors'][] = 'Ürün #' . (int)$row['id'] . ' için açıklama üretilemedi: ' . $exception->getMessage();
            }
        }

        Settings::set('integration_contentbot_last_description_sync', date('Y-m-d H:i:s'));

        return $results;
    }

    /**
     * Generate a comment for the next eligible product respecting interval configuration.
     *
     * @return array<string,mixed>
     */
    public static function processCommentCycle()
    {
        if (!self::isCommentsEnabled()) {
            throw new RuntimeException('Yorum otomasyonu pasif.');
        }

        $interval = max(1, (int)Settings::get('integration_contentbot_comment_interval_minutes'));
        $nextAt = Settings::get('integration_contentbot_next_comment_at');
        $now = new DateTimeImmutable('now');

        if ($nextAt) {
            try {
                $scheduled = new DateTimeImmutable($nextAt);
                if ($scheduled > $now) {
                    return array(
                        'status' => 'scheduled',
                        'message' => 'Sonraki yorum zamanı henüz gelmedi.',
                        'next_comment_at' => $scheduled->format('Y-m-d H:i:s'),
                    );
                }
            } catch (\Exception $exception) {
                // ignore parse error
            }
        }

        $product = self::findProductNeedingComment();
        if (!$product) {
            return array(
                'status' => 'noop',
                'message' => 'Yorum bekleyen ürün bulunamadı.',
            );
        }

        $payload = self::requestStructuredContent('product_comment', self::buildCommentPrompt($product));
        $commentText = isset($payload['comment']) ? (string)$payload['comment'] : (isset($payload['content']) ? (string)$payload['content'] : '');
        if ($commentText === '') {
            throw new RuntimeException('Üretilen yorum boş.');
        }

        $rating = isset($payload['rating']) ? (int)$payload['rating'] : 5;
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }

        $author = self::pickAuthorName();

        $pdo = Database::connection();
        $insert = $pdo->prepare('INSERT INTO product_comments (product_id, user_id, author_name, author_email, content, rating, is_approved, created_at) '
            . 'VALUES (:product_id, NULL, :author_name, NULL, :content, :rating, 1, :created_at)');
        $insert->execute(array(
            'product_id' => (int)$product['id'],
            'author_name' => $author,
            'content' => $commentText,
            'rating' => $rating,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ));

        $next = $now->add(new DateInterval('PT' . $interval . 'M'));
        Settings::set('integration_contentbot_last_comment_run', $now->format('Y-m-d H:i:s'));
        Settings::set('integration_contentbot_next_comment_at', $next->format('Y-m-d H:i:s'));

        return array(
            'status' => 'created',
            'product_id' => (int)$product['id'],
            'product_name' => $product['name'],
            'comment' => $commentText,
            'next_comment_at' => $next->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Publish new SEO ready articles using provided keywords.
     *
     * @param int $maxArticles
     * @return array<string,mixed>
     */
    public static function publishArticles($maxArticles = 3)
    {
        if (!self::isArticlesEnabled()) {
            throw new RuntimeException('Makale otomasyonu pasif.');
        }

        $keywords = self::keywords();
        if (!$keywords) {
            throw new RuntimeException('Makale anahtar kelimeleri tanımlanmadı.');
        }

        self::ensureBlogTables();

        $created = array();
        $errors = array();
        $processed = 0;

        foreach ($keywords as $keyword) {
            if ($processed >= $maxArticles) {
                break;
            }
            $processed++;

            try {
                $prompt = self::buildArticlePrompt($keyword, $keywords);
                $payload = self::requestStructuredContent('blog_article', $prompt, 1600);

                $title = isset($payload['title']) ? (string)$payload['title'] : '';
                $content = isset($payload['content']) ? (string)$payload['content'] : '';
                $excerpt = isset($payload['excerpt']) ? (string)$payload['excerpt'] : Helpers::truncate($content, 240);
                $seoTitle = isset($payload['seo_title']) ? (string)$payload['seo_title'] : $title;
                $seoDescription = isset($payload['seo_description']) ? (string)$payload['seo_description'] : Helpers::truncate($content, 155);
                $seoKeywords = isset($payload['keywords']) ? (string)$payload['keywords'] : implode(', ', $keywords);
                $imagePrompt = isset($payload['image_prompt']) ? (string)$payload['image_prompt'] : 'Profesyonel blog başlığı için görsel: ' . $title;

                if ($title === '' || $content === '') {
                    throw new RuntimeException('Makale içeriği tamamlanamadı.');
                }

                $slug = self::uniqueSlug($title);
                $imagePath = DalleService::generateGenericImage($imagePrompt, 'blog-' . $slug);

                $pdo = Database::connection();
                $insert = $pdo->prepare('INSERT INTO blog_posts (title, slug, excerpt, content, featured_image, seo_title, seo_description, seo_keywords, is_published, published_at, created_at) '
                    . 'VALUES (:title, :slug, :excerpt, :content, :image, :seo_title, :seo_description, :seo_keywords, 1, NOW(), NOW())');
                $insert->execute(array(
                    'title' => $title,
                    'slug' => $slug,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'image' => $imagePath,
                    'seo_title' => $seoTitle,
                    'seo_description' => $seoDescription,
                    'seo_keywords' => $seoKeywords,
                ));

                $created[] = array(
                    'title' => $title,
                    'slug' => $slug,
                );
            } catch (\Throwable $exception) {
                $errors[] = '"' . $keyword . '" için makale üretilemedi: ' . $exception->getMessage();
            }
        }

        self::refreshHomepagePosts();

        $now = new DateTimeImmutable('now');
        $interval = max(30, (int)Settings::get('integration_contentbot_article_interval_minutes'));
        $next = $now->add(new DateInterval('PT' . $interval . 'M'));
        Settings::set('integration_contentbot_last_article_run', $now->format('Y-m-d H:i:s'));
        Settings::set('integration_contentbot_next_article_at', $next->format('Y-m-d H:i:s'));

        return array(
            'created' => $created,
            'errors' => $errors,
            'next_article_at' => $next->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Determine if automated product description generation is enabled.
     *
     * @return bool
     */
    public static function isDescriptionsEnabled()
    {
        return (bool)(int)Settings::get('integration_contentbot_descriptions_enabled');
    }

    /**
     * @return bool
     */
    public static function isCommentsEnabled()
    {
        return (bool)(int)Settings::get('integration_contentbot_comments_enabled');
    }

    /**
     * @return bool
     */
    public static function isArticlesEnabled()
    {
        return (bool)(int)Settings::get('integration_contentbot_articles_enabled');
    }

    /**
     * Parse keyword list from settings.
     *
     * @return array<int,string>
     */
    public static function keywords()
    {
        $raw = Settings::get('integration_contentbot_keywords');
        if ($raw === null || $raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded))); // already structured
        }

        return array();
    }

    /**
     * Build prompt for product descriptions.
     *
     * @param array<string,mixed> $product
     * @param array<int,string> $keywords
     * @return string
     */
    private static function buildProductPrompt(array $product, array $keywords)
    {
        $prompt = Settings::get('integration_contentbot_default_product_prompt');
        if (!$prompt) {
            $prompt = 'E-ticaret ürünü için SEO uyumlu açıklamalar hazırla. JSON formatında yanıt ver ve "description" ile "short_description" alanlarını doldur. Ürün adı: {{product_name}}. Kategori: {{category_name}}. Ürünün faydalarını, kullanım alanlarını ve benzersiz değer teklifini vurgula. Kısa açıklama maksimum 180 karakter olsun.';
        }

        $replacements = array(
            '{{product_name}}' => isset($product['name']) ? (string)$product['name'] : '',
            '{{category_name}}' => isset($product['category_name']) ? (string)$product['category_name'] : '',
            '{{keywords}}' => implode(', ', $keywords),
        );

        return strtr($prompt, $replacements);
    }

    /**
     * Build prompt for comment generation.
     *
     * @param array<string,mixed> $product
     * @return string
     */
    private static function buildCommentPrompt(array $product)
    {
        $prompt = Settings::get('integration_contentbot_default_comment_prompt');
        if (!$prompt) {
            $prompt = 'Bir e-ticaret ürünü için müşteri yorumu yaz. JSON döndür ve "comment" ile "rating" alanlarını doldur. Yorum doğal, güvenilir ve gerçek bir deneyimi yansıtmalı. Ürün adı: {{product_name}}. Maksimum 3 cümle kullan.';
        }

        return strtr($prompt, array(
            '{{product_name}}' => isset($product['name']) ? (string)$product['name'] : '',
        ));
    }

    /**
     * Build prompt for article generation.
     *
     * @param string $keyword
     * @param array<int,string> $keywords
     * @return string
     */
    private static function buildArticlePrompt($keyword, array $keywords)
    {
        $prompt = Settings::get('integration_contentbot_default_article_prompt');
        if (!$prompt) {
            $prompt = 'Tamamen SEO uyumlu, %100 özgün bir blog makalesi hazırla. JSON formatında dön ve "title", "excerpt", "content", "seo_title", "seo_description", "keywords" ve "image_prompt" alanlarını doldur. İçerik minimum 900 kelime olsun, H2 ve H3 başlıklar içer, metin Türkçe olmalı. Hedef anahtar kelime: {{keyword}}. Ek anahtar kelimeler: {{keywords}}. SERP uyumlu meta açıklama üret.';
        }

        return strtr($prompt, array(
            '{{keyword}}' => (string)$keyword,
            '{{keywords}}' => implode(', ', $keywords),
        ));
    }

    /**
     * Request structured JSON content from provider.
     *
     * @param string $contentType
     * @param string $prompt
     * @param int $maxTokens
     * @return array<string,mixed>
     */
    private static function requestStructuredContent($contentType, $prompt, $maxTokens = 900)
    {
        $endpoint = Settings::get('integration_contentbot_endpoint');
        $apiKey = Settings::get('integration_contentbot_api_key');
        $model = Settings::get('integration_contentbot_model') ?: 'gpt-4o-mini';

        if (!$endpoint || !$apiKey) {
            throw new RuntimeException('Makale & Yorum botu için API bilgileri eksik.');
        }

        $language = Settings::get('integration_contentbot_language') ?: 'tr';
        $tone = Settings::get('integration_contentbot_tone') ?: 'neutral';

        $system = 'Sen deneyimli bir içerik editörüsün. Yanıtlarını mutlaka JSON formatında üret.';
        if ($contentType === 'product_description') {
            $system = 'Sen e-ticaret konusunda uzman bir içerik editörüsün. Yanıtların JSON formatında ve SEO uyumlu olmalı.';
        } elseif ($contentType === 'product_comment') {
            $system = 'Sen gerçek kullanıcı yorumlarını taklit eden bir içerik üreticisisin. Yanıtın JSON formatında olmalı.';
        } elseif ($contentType === 'blog_article') {
            $system = 'Sen SEO odaklı içerikler üreten kıdemli bir içerik editörüsün. Yanıtların JSON formatında tüm alanları içermeli.';
        }

        $payload = array(
            'model' => $model,
            'messages' => array(
                array('role' => 'system', 'content' => $system . ' Dil: ' . strtoupper($language) . '. Ton: ' . $tone . '.'),
                array('role' => 'user', 'content' => $prompt),
            ),
            'temperature' => 0.7,
            'max_output_tokens' => $maxTokens,
        );

        $raw = OpenAiClient::postJsonForContent($apiKey, $endpoint, $payload);
        $decoded = self::decodeJsonResponse($raw);

        if (!is_array($decoded)) {
            throw new RuntimeException('Model JSON formatında yanıt döndürmedi.');
        }

        return $decoded;
    }

    /**
     * @param string $raw
     * @return array<string,mixed>|null
     */
    private static function decodeJsonResponse($raw)
    {
        $raw = trim((string)$raw);

        if ($raw === '') {
            return null;
        }

        if (strpos($raw, '```') !== false) {
            $raw = preg_replace('/^```json|```$/m', '', $raw);
            $raw = trim($raw);
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false) {
            $json = substr($raw, $start, $end - $start + 1);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findProductNeedingComment()
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT p.id, p.name FROM products p '
            . 'LEFT JOIN product_comments pc ON pc.product_id = p.id '
            . 'GROUP BY p.id '
            . 'HAVING SUM(pc.is_approved = 1) IS NULL OR SUM(pc.is_approved = 1) < 3 '
            . 'ORDER BY COUNT(pc.id) ASC, p.updated_at DESC, p.id DESC LIMIT 1');

        if ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            return $row;
        }

        return null;
    }

    /**
     * Ensure blog tables exist for article publishing.
     *
     * @return void
     */
    private static function ensureBlogTables()
    {
        try {
            BlogRepository::all(1);
        } catch (\Throwable $exception) {
            $pdo = Database::connection();
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
                INDEX idx_blog_posts_published (is_published, published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    /**
     * Refresh homepage blog posts setting using latest published articles.
     *
     * @return void
     */
    private static function refreshHomepagePosts()
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT title, slug, excerpt, featured_image, published_at FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC LIMIT 6');

        $items = array();
        while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $items[] = array(
                'title' => $row['title'],
                'excerpt' => Helpers::truncate($row['excerpt'], 160),
                'date' => date('d M Y', strtotime($row['published_at'])),
                'image' => $row['featured_image'],
                'slug' => $row['slug'],
                'url' => '/blog/' . $row['slug'],
            );
        }

        Settings::set('homepage_blog_posts', $items ? json_encode($items) : null);
    }

    /**
     * @return string
     */
    private static function uniqueSlug($title)
    {
        $slug = Helpers::slugify($title);
        if ($slug === '') {
            $slug = 'makale-' . bin2hex(random_bytes(4));
        }

        $pdo = Database::connection();
        $base = $slug;
        $counter = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE slug = :slug');
        while (true) {
            $stmt->execute(array('slug' => $slug));
            $count = (int)$stmt->fetchColumn();
            if ($count === 0) {
                break;
            }
            $slug = $base . '-' . (++$counter);
        }

        return $slug;
    }

    /**
     * Pick a synthetic author name.
     *
     * @return string
     */
    private static function pickAuthorName()
    {
        $names = array(
            'Ahmet K.', 'Selin T.', 'Emre Y.', 'Ece D.', 'Burak L.', 'Hande P.', 'Gamze R.', 'Kerem S.', 'Merve A.', 'Cem Ö.',
        );

        return $names[array_rand($names)];
    }
}

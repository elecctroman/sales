<?php

namespace App;

use App\Database;
use PDO;

class PageRepository
{
    /**
     * @var array<string,array|null>
     */
    private static $cache = array();

    /**
     * @var bool
     */
    private static $schemaEnsured = false;

    /**
     * Ensure the database schema exists for the pages module.
     *
     * @return void
     */
    public static function ensureSchema(): void
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

        $pdo->exec('CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(150) NOT NULL UNIQUE,
            title VARCHAR(191) NOT NULL,
            content MEDIUMTEXT NULL,
            meta_title VARCHAR(191) NULL,
            meta_description TEXT NULL,
            meta_keywords TEXT NULL,
            status ENUM("draft","published","archived") NOT NULL DEFAULT "draft",
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pages_status (status),
            INDEX idx_pages_published (published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        if (!Database::tableHasColumn('pages', 'meta_title')) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN meta_title VARCHAR(191) NULL AFTER content');
        }

        if (!Database::tableHasColumn('pages', 'meta_description')) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN meta_description TEXT NULL AFTER meta_title');
        }

        if (!Database::tableHasColumn('pages', 'meta_keywords')) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN meta_keywords TEXT NULL AFTER meta_description');
        }

        if (!Database::tableHasColumn('pages', 'status')) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN status ENUM("draft","published","archived") NOT NULL DEFAULT "draft" AFTER meta_keywords');
        }

        if (!Database::tableHasColumn('pages', 'published_at')) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN published_at DATETIME NULL AFTER status');
        }

        if (!Database::tableHasColumn('pages', 'updated_at')) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        }

        self::$schemaEnsured = true;
    }

    /**
     * Fetch a published page by slug. Falls back to default static content when available.
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
        self::ensureSchema();

        $slug = Helpers::slugify($slug);
        if ($slug === '') {
            return null;
        }

        if (array_key_exists($slug, self::$cache)) {
            return self::$cache[$slug] ?: null;
        }

        $page = null;

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT slug, title, content, meta_title, meta_description, meta_keywords FROM pages WHERE slug = :slug AND status = :status LIMIT 1');
            $stmt->execute(array(
                'slug' => $slug,
                'status' => 'published',
            ));
            $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $exception) {
            $page = null;
        }

        if ($page) {
            $page = array(
                'slug' => isset($page['slug']) ? (string)$page['slug'] : $slug,
                'title' => isset($page['title']) ? (string)$page['title'] : self::humanizeSlug($slug),
                'content' => isset($page['content']) ? (string)$page['content'] : '',
                'meta_title' => isset($page['meta_title']) ? (string)$page['meta_title'] : '',
                'meta_description' => isset($page['meta_description']) ? (string)$page['meta_description'] : '',
                'meta_keywords' => isset($page['meta_keywords']) ? (string)$page['meta_keywords'] : '',
            );
        } else {
            $page = self::defaultPage($slug);
        }

        self::$cache[$slug] = $page ?: false;

        return $page ?: null;
    }

    /**
     * Provide default copies for common static pages used by the storefront.
     *
     * @param string $slug
     * @return array|null
     */
    private static function defaultPage(string $slug): ?array
    {
        $defaults = self::defaultPages();

        if (!isset($defaults[$slug])) {
            return null;
        }

        $page = $defaults[$slug];
        $page['slug'] = $slug;

        return $page;
    }

    /**
     * @return array<string,array<string,string>>
     */
    public static function defaultPages(): array
    {
        return array(
            'gizlilik-politikasi' => array(
                'title' => 'Gizlilik Politikası',
                'content' => <<<HTML
<p>OyunHesap.com olarak gizliliğiniz bizim için önceliklidir. Hesabınızı oluştururken paylaştığınız kişisel verileri yalnızca siparişlerinizin işlenmesi, destek taleplerinizin yanıtlanması ve yasal yükümlülüklerimizin yerine getirilmesi amacıyla işleriz.</p>
<p>Verileriniz ISO 27001 uyumlu altyapımızda şifrelenmiş olarak saklanır. Üçüncü taraf servis sağlayıcılarıyla paylaşımlar yalnızca hizmetin zorunlu olduğu durumlarla sınırlıdır ve tüm transferlerde veri işleme sözleşmeleri uygulanır.</p>
<p>Hesabınızla ilgili değişiklikleri profil panelinizden yönetebilir, dilediğiniz zaman destek ekibimizle iletişime geçerek veri erişimi veya silme talebinde bulunabilirsiniz.</p>
HTML,
                'meta_title' => 'Gizlilik Politikası',
                'meta_description' => 'Kişisel verilerinizin nasıl korunduğunu ve işlendiğini öğrenin.',
                'meta_keywords' => 'gizlilik, kvkk, veri koruma',
            ),
            'iade' => array(
                'title' => 'İade Politikası',
                'content' => <<<HTML
<p>Dijital ürün teslimatlarınız siparişiniz onaylandığı anda hesabınıza tanımlanır. Ürün anahtarının veya hesabının çalışmadığı durumlarda, 24 saat içinde destek bölümümüzden kayıt açarak ücretsiz değişim veya iade talep edebilirsiniz.</p>
<p>Talebiniz incelenirken sipariş numarası, ödeme dekontu ve yaşadığınız sorunun ekran görüntülerini iletmeniz çözüm sürecini hızlandırır. İnceleme tamamlandığında bakiye iadesi hesabınıza otomatik yansıtılır.</p>
<p>Yetkisiz paylaşım, kötüye kullanım veya satışı gerçekleştirilen ürünlerin üçüncü taraf platformlarda etkinleştirilmesi durumlarında iade yapılamaz.</p>
HTML,
                'meta_title' => 'İade Şartları',
                'meta_description' => 'Dijital ürünler için iade ve değişim sürecini öğrenin.',
                'meta_keywords' => 'iade politikası, dijital ürün iadesi, para iadesi',
            ),
            'kullanim-sartlari' => array(
                'title' => 'Kullanım Şartları',
                'content' => <<<HTML
<p>OyunHesap.com hizmetlerini kullanan tüm ziyaretçiler aşağıdaki kuralları kabul etmiş sayılır. Hesap güvenliğinizden siz sorumlusunuz; oturum bilgilerinizi üçüncü kişilerle paylaşmayın ve iki adımlı doğrulama çözümlerini aktif edin.</p>
<p>Platform üzerinden satın alınan ürünler yalnızca kişisel kullanım içindir. Yeniden satış veya yetkisiz paylaşım tespit edilmesi halinde ilgili hesap askıya alınabilir ve hukuki süreç başlatılabilir.</p>
<p>Destek ekibimiz 7/24 hizmet vermektedir. Tüm iletişim kanallarında topluluk kurallarına uymayı, hakaret, tehdit veya spam içerik paylaşmamayı kabul etmiş olursunuz.</p>
HTML,
                'meta_title' => 'Kullanım Şartları',
                'meta_description' => 'OyunHesap.com kullanım sözleşmesi ve kuralları.',
                'meta_keywords' => 'kullanım şartları, sözleşme, kullanıcı kuralları',
            ),
            'api-dokumantasyon' => array(
                'title' => 'API Dokümantasyonu',
                'content' => <<<HTML
<h2>Genel Bakış</h2>
<p>OyunHesap API, stoktaki dijital ürünlerinizi e-ticaret mağazalarınıza bağlamanız için REST mimarisiyle tasarlanmıştır. Tüm uç noktalar JSON yanıt döndürür ve TLS üzerinden çalışır.</p>

<h3>Temel URL</h3>
<p><code>{SİTENİZ}/api/v1/</code></p>

<h3>Kimlik Doğrulama</h3>
<p>Her isteğe <code>Authorization: Bearer &lt;API_KEY&gt;</code> başlığı eklenmelidir. Ayrıca entegrasyon yaptığınız müşteri hesabının e-posta adresini <code>X-User-Email</code> veya <code>X-Reseller-Email</code> başlığında göndermeniz zorunludur.</p>

<h2>Ürün Listesi</h2>
<p><strong>GET /api/v1/products</strong> — Yetkili hesabın görüntüleme iznine sahip olduğu kategorileri ve aktif ürünleri döndürür.</p>
<pre><code>curl -X GET "{SİTENİZ}/api/v1/products" \
  -H "Authorization: Bearer API_KEY" \
  -H "X-User-Email: musteri@ornek.com"</code></pre>

<h2>Sipariş Oluşturma</h2>
<p><strong>POST /api/v1/orders</strong> — WooCommerce veya özel mağazanızdaki siparişleri anlık olarak panele aktarmanızı sağlar.</p>
<pre><code>{
  "order_id": "WC-12045",
  "currency": "TRY",
  "customer": {
    "name": "Ada Yılmaz",
    "email": "ada@example.com"
  },
  "items": [
    { "sku": "VALO-2050", "quantity": 2 },
    { "sku": "PUBG-660", "quantity": 1, "note": "Hediye" }
  ]
}</code></pre>

<h3>Başarılı Yanıt</h3>
<pre><code>{
  "success": true,
  "data": {
    "orders": [8451, 8452],
    "remaining_balance": 542.70
  }
}</code></pre>

<h3>Durum Kodları</h3>
<table>
    <thead>
        <tr><th>Kod</th><th>Anlamı</th></tr>
    </thead>
    <tbody>
        <tr><td>200</td><td>İşlem başarılı.</td></tr>
        <tr><td>201</td><td>Sipariş oluşturuldu.</td></tr>
        <tr><td>401</td><td>Kimlik doğrulama hatası.</td></tr>
        <tr><td>422</td><td>Eksik veya hatalı parametre.</td></tr>
        <tr><td>500</td><td>Sunucu hatası, destek ekibi ile iletişime geçin.</td></tr>
    </tbody>
</table>

<h2>Webhook Bildirimleri</h2>
<p>Hesabınıza tanımlı webhook URL'si varsa, başarılı siparişler JSON formatında aynı anahtarla imzalanarak gönderilir. İmzayı doğrulamak için <code>Authorization</code> başlığındaki token değerini kullanabilirsiniz.</p>

<p>Daha fazla örnek ve SDK talepleriniz için <a href="/support.php">destek bölümümüz</a> ile iletişime geçebilirsiniz.</p>
HTML,
                'meta_title' => 'REST API Dokümantasyonu',
                'meta_description' => 'OyunHesap REST API uç noktaları ve entegrasyon talimatları.',
                'meta_keywords' => 'api dokümantasyonu, rest api, entegrasyon',
            ),
            'about-us' => array(
                'title' => 'Hakkımızda',
                'content' => '<p>OyunHesap.com, oyuncular ve içerik üreticileri için güvenilir dijital ürün ve servis platformudur. Yüzlerce oyun, yazılım ve abonelik seçeneğini tek çatı altında topluyoruz.</p><p>7/24 canlı destek, gelişmiş güvenlik kontrolleri ve hızlı teslimat altyapımızla müşterilerimize kusursuz bir deneyim sunmayı hedefliyoruz.</p>',
                'meta_title' => 'OyunHesap.com Hakkında',
                'meta_description' => 'OyunHesap.com ekibini ve sunduğumuz hizmetleri yakından tanıyın.',
                'meta_keywords' => 'hakkımızda, oyun hesap, dijital ürün',
            ),
            'careers' => array(
                'title' => 'Kariyer',
                'content' => '<p>Teknoloji ve oyun dünyasını seven yetenekli ekip arkadaşları arıyoruz. Destek, yazılım geliştirme, içerik ve pazarlama ekiplerimize katılmak için güncel pozisyonlarımızı inceleyebilirsiniz.</p><p>Başvurularınızı <a href="mailto:insan.kaynaklari@oyunhesap.com">insan.kaynaklari@oyunhesap.com</a> adresine özgeçmişinizle birlikte gönderebilirsiniz.</p>',
                'meta_title' => 'Kariyer Fırsatları',
                'meta_description' => 'OyunHesap.com bünyesindeki kariyer fırsatlarını ve açık pozisyonları keşfedin.',
                'meta_keywords' => 'kariyer, iş başvurusu, oyun sektörü',
            ),
            'order-tracking' => array(
                'title' => 'Sipariş Takibi',
                'content' => '<p>Siparişlerinizi hesap paneliniz üzerinden anlık olarak takip edebilirsiniz. Takıldığınız bir nokta olursa destek ekibimiz her zaman yanınızda.</p><p>Misafir kullanıcılar destek formumuzu kullanarak durum bilgisi talep edebilir.</p>',
                'meta_title' => 'Sipariş Takibi',
                'meta_description' => 'OyunHesap.com siparişlerinizi nasıl takip edeceğinizi öğrenin.',
                'meta_keywords' => 'sipariş takibi, order tracking',
            ),
        );
    }

    /**
     * Persist default pages in the database if they do not exist.
     *
     * @return void
     */
    public static function ensureDefaultPages(): void
    {
        self::ensureSchema();

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return;
        }

        $defaults = self::defaultPages();

        foreach ($defaults as $slug => $data) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM pages WHERE slug = :slug');
            $stmt->execute(array('slug' => $slug));
            if ((int)$stmt->fetchColumn() > 0) {
                continue;
            }

            $insert = $pdo->prepare('INSERT INTO pages (slug, title, content, meta_title, meta_description, meta_keywords, status, published_at, created_at) VALUES (:slug, :title, :content, :meta_title, :meta_description, :meta_keywords, :status, NOW(), NOW())');
            $insert->execute(array(
                'slug' => $slug,
                'title' => $data['title'],
                'content' => $data['content'],
                'meta_title' => $data['meta_title'],
                'meta_description' => $data['meta_description'],
                'meta_keywords' => $data['meta_keywords'],
                'status' => 'published',
            ));
        }
    }

    /**
     * Retrieve all pages for administration interfaces.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        self::ensureSchema();

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return array();
        }

        $stmt = $pdo->query('SELECT * FROM pages ORDER BY created_at DESC');

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    /**
     * Persist a page record.
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
            throw new \RuntimeException('Sayfa kaydedilirken veritabanı bağlantısı kurulamadı.');
        }

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $title = isset($payload['title']) ? trim((string)$payload['title']) : '';
        $slugInput = isset($payload['slug']) ? trim((string)$payload['slug']) : '';
        $content = isset($payload['content']) ? (string)$payload['content'] : '';
        $metaTitle = isset($payload['meta_title']) ? trim((string)$payload['meta_title']) : '';
        $metaDescription = isset($payload['meta_description']) ? trim((string)$payload['meta_description']) : '';
        $metaKeywords = isset($payload['meta_keywords']) ? trim((string)$payload['meta_keywords']) : '';
        $status = isset($payload['status']) ? strtolower((string)$payload['status']) : 'draft';

        if ($title === '') {
            throw new \InvalidArgumentException('Sayfa başlığı zorunludur.');
        }

        if (!in_array($status, array('draft', 'published', 'archived'), true)) {
            $status = 'draft';
        }

        $slug = $slugInput !== '' ? Helpers::slugify($slugInput) : Helpers::slugify($title);
        if ($slug === '') {
            throw new \InvalidArgumentException('Geçerli bir sayfa adresi belirleyin.');
        }

        $slug = self::uniqueSlug($slug, $id > 0 ? $id : null);

        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $existing = null;
        if ($id > 0) {
            $currentStmt = $pdo->prepare('SELECT status, published_at FROM pages WHERE id = :id LIMIT 1');
            $currentStmt->execute(array('id' => $id));
            $existing = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($existing && !empty($existing['published_at'])) {
                if ($status === 'published' && (!isset($payload['published_at']) || trim((string)$payload['published_at']) === '')) {
                    $publishedAt = $existing['published_at'];
                }
            }

            $stmt = $pdo->prepare('UPDATE pages SET slug = :slug, title = :title, content = :content, meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords, status = :status, published_at = :published_at, updated_at = NOW() WHERE id = :id');
            $stmt->execute(array(
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
                'status' => $status,
                'published_at' => $status === 'published' ? $publishedAt : null,
                'id' => $id,
            ));
        } else {
            $stmt = $pdo->prepare('INSERT INTO pages (slug, title, content, meta_title, meta_description, meta_keywords, status, published_at, created_at) VALUES (:slug, :title, :content, :meta_title, :meta_description, :meta_keywords, :status, :published_at, NOW())');
            $stmt->execute(array(
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'meta_keywords' => $metaKeywords !== '' ? $metaKeywords : null,
                'status' => $status,
                'published_at' => $status === 'published' ? $publishedAt : null,
            ));
            $id = (int)$pdo->lastInsertId();
        }

        self::$cache = array();

        return self::findBySlug($slug);
    }

    /**
     * Locate a page by identifier regardless of status.
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

        $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return array(
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'slug' => isset($row['slug']) ? (string)$row['slug'] : '',
            'title' => isset($row['title']) ? (string)$row['title'] : '',
            'content' => isset($row['content']) ? (string)$row['content'] : '',
            'meta_title' => isset($row['meta_title']) ? (string)$row['meta_title'] : null,
            'meta_description' => isset($row['meta_description']) ? (string)$row['meta_description'] : null,
            'meta_keywords' => isset($row['meta_keywords']) ? (string)$row['meta_keywords'] : null,
            'status' => isset($row['status']) ? (string)$row['status'] : 'draft',
            'published_at' => isset($row['published_at']) ? $row['published_at'] : null,
            'created_at' => isset($row['created_at']) ? $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? $row['updated_at'] : null,
        );
    }

    /**
     * Remove a page by identifier.
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

        $stmt = $pdo->prepare('DELETE FROM pages WHERE id = :id');
        $result = $stmt->execute(array('id' => $id));

        if ($result) {
            self::$cache = array();
        }

        return $result;
    }

    /**
     * Ensure uniqueness of page slugs.
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return string
     */
    private static function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        self::ensureSchema();

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            return $slug;
        }

        $base = $slug;
        $suffix = 1;

        $query = 'SELECT COUNT(*) FROM pages WHERE slug = :slug';
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

            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }

    /**
     * @param string $slug
     * @return string
     */
    private static function humanizeSlug(string $slug): string
    {
        $slug = trim(str_replace('-', ' ', $slug));
        if ($slug === '') {
            return 'Sayfa';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($slug, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($slug);
    }
}

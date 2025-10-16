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
     * Fetch a published page by slug. Falls back to default static content when available.
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
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
        $defaults = array(
            'privacy-policy' => array(
                'title' => 'Gizlilik Politikası',
                'content' => '<p>OyunHesap.com olarak kişisel verilerinizi yalnızca hizmetlerimizi sunmak, güvenliği sağlamak ve yasal yükümlülüklerimizi yerine getirmek amacıyla topluyoruz. Bilgileriniz yetkisiz erişime karşı güvenlik kontrolleri ile korunur.</p><p>Hesabınızla ilgili tüm değişiklikleri şeffaflıkla bildirir, paylaşım izinlerini dilediğiniz zaman güncellemenize olanak tanırız.</p>',
                'meta_title' => 'Gizlilik Politikası',
                'meta_description' => 'OyunHesap.com kişisel verileri nasıl işlediğini ve koruduğunu öğrenin.',
                'meta_keywords' => 'gizlilik politikası, kvkk, veri koruma',
            ),
            'return-policy' => array(
                'title' => 'İade Politikası',
                'content' => '<p>Dijital ürün teslimatlarımız anlık olarak gerçekleştirilir. Üründe teknik bir sorun yaşarsanız 24 saat içinde destek ekibimizle iletişime geçebilirsiniz.</p><p>Sipariş numaranız ve yaşadığınız problemi paylaşmanız halinde işleminizi hızla inceleyerek çözüm üretiriz.</p>',
                'meta_title' => 'İade Politikası',
                'meta_description' => 'OyunHesap.com dijital ürün iadeleri ve değişim süreçleri hakkında bilgi alın.',
                'meta_keywords' => 'iade, değişim, dijital ürün',
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

        if (!isset($defaults[$slug])) {
            return null;
        }

        return array_merge(array('slug' => $slug), $defaults[$slug]);
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

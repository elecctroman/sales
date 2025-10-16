<?php
use App\Helpers;

$footerGroups = isset($GLOBALS['theme_footer_menus']) ? $GLOBALS['theme_footer_menus'] : array();

if (!$footerGroups) {
    $footerGroups = array(
        array(
            'title' => 'Müşteri Hizmetleri',
            'items' => array(
                array('title' => 'Destek Merkezi', 'url' => '/support.php'),
                array('title' => 'Sipariş Takibi', 'url' => Helpers::pageUrl('order-tracking')),
                array('title' => 'İade Politikası', 'url' => Helpers::pageUrl('iade')),
                array('title' => 'Gizlilik Politikası', 'url' => Helpers::pageUrl('gizlilik-politikasi')),
                array('title' => 'Kullanım Şartları', 'url' => Helpers::pageUrl('kullanim-sartlari')),
            ),
        ),
        array(
            'title' => 'Popüler Ürünler',
            'items' => array(
                array('title' => 'Valorant Points', 'url' => Helpers::categoryUrl('valorant')),
                array('title' => 'PUBG UC', 'url' => Helpers::categoryUrl('pubg')),
                array('title' => 'Windows Lisansları', 'url' => Helpers::categoryUrl('windows')),
                array('title' => 'Tasarım Araçları', 'url' => Helpers::categoryUrl('design-tools')),
            ),
        ),
        array(
            'title' => 'Şirket',
            'items' => array(
                array('title' => 'Blog', 'url' => '/blog'),
                array('title' => 'Hakkımızda', 'url' => Helpers::pageUrl('about-us')),
                array('title' => 'Kariyer', 'url' => Helpers::pageUrl('careers')),
                array('title' => 'İletişim', 'url' => '/contact.php'),
            ),
        ),
    );
}
?>
<footer class="site-footer">
    <div class="site-footer__grid">
        <div>
            <h4>OyunHesap.com</h4>
            <p>Anında dijital teslimat, güvenli ödeme ve 7/24 canlı destek.</p>
            <div class="footer-social">
                <a href="#">Facebook</a>
                <a href="#">Twitter</a>
                <a href="#">YouTube</a>
                <a href="#">Discord</a>
            </div>
        </div>
        <?php foreach ($footerGroups as $group): ?>
            <div>
                <h5><?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                <?php if (!empty($group['items'])): ?>
                    <ul>
                        <?php foreach ($group['items'] as $item): ?>
                            <?php
                                $label = isset($item['title']) ? (string)$item['title'] : 'Bağlantı';
                                $url = isset($item['url']) && $item['url'] !== '' ? (string)$item['url'] : '#';
                            ?>
                            <li>
                                <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"<?= isset($item['target']) && $item['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="site-footer__copy">&copy; <?= date('Y') ?> OyunHesap.com - Tüm hakları saklıdır.</p>
</footer>

<?php
use App\Helpers;
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
        <div>
            <h5>Müşteri Hizmetleri</h5>
            <ul>
                <li><a href="/support.php">Destek Merkezi</a></li>
                <li><a href="#">Sipariş Takibi</a></li>
                <li><a href="#">İade Politikası</a></li>
                <li><a href="#">Gizlilik Politikası</a></li>
            </ul>
        </div>
        <div>
            <h5>Popüler Ürünler</h5>
            <ul>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('valorant')) ?>">Valorant Points</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('pubg')) ?>">PUBG UC</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('windows')) ?>">Windows Lisansları</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('design-tools')) ?>">Tasarım Araçları</a></li>
            </ul>
        </div>
        <div>
            <h5>Şirket</h5>
            <ul>
                <li><a href="/blog.php">Blog</a></li>
                <li><a href="#">Hakkımızda</a></li>
                <li><a href="#">Kariyer</a></li>
                <li><a href="/contact.php">İletişim</a></li>
            </ul>
        </div>
    </div>
    <p class="site-footer__copy">&copy; <?= date('Y') ?> OyunHesap.com - Tüm hakları saklıdır.</p>
</footer>

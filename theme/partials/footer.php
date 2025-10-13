<?php use App\Helpers; ?>
<footer class="site-footer">
    <div class="site-footer__grid">
        <div>
            <h4>OyunHesap.com</h4>
            <p>Instant digital delivery, secure payments and 24/7 live support.</p>
            <div class="footer-social">
                <a href="#">Facebook</a>
                <a href="#">Twitter</a>
                <a href="#">YouTube</a>
                <a href="#">Discord</a>
            </div>
        </div>
        <div>
            <h5>Customer Service</h5>
            <ul>
                <li><a href="<?= htmlspecialchars(Helpers::supportUrl(), ENT_QUOTES, 'UTF-8') ?>">Support Center</a></li>
                <li><a href="#">Order Tracking</a></li>
                <li><a href="#">Refund Policy</a></li>
                <li><a href="#">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <h5>Popular Products</h5>
            <ul>
                <li><a href="<?= htmlspecialchars(Helpers::catalogUrl(), ENT_QUOTES, 'UTF-8') ?>#valorant">Valorant Points</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::catalogUrl(), ENT_QUOTES, 'UTF-8') ?>#pubg">PUBG UC</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::catalogUrl(), ENT_QUOTES, 'UTF-8') ?>#windows">Windows Keys</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::catalogUrl(), ENT_QUOTES, 'UTF-8') ?>#design">Design Tools</a></li>
            </ul>
        </div>
        <div>
            <h5>Company</h5>
            <ul>
                <li><a href="<?= htmlspecialchars(Helpers::blogUrl(), ENT_QUOTES, 'UTF-8') ?>">Blog</a></li>
                <li><a href="#">About</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::contactUrl(), ENT_QUOTES, 'UTF-8') ?>">Contact</a></li>
            </ul>
        </div>
    </div>
    <p class="site-footer__copy">&copy; <?= date('Y') ?> OyunHesap.com - All rights reserved.</p>
</footer>

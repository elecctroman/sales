<?php
use App\Helpers;
?>
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
                <li><a href="/support.php">Support Center</a></li>
                <li><a href="#">Order Tracking</a></li>
                <li><a href="#">Refund Policy</a></li>
                <li><a href="#">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <h5>Popular Products</h5>
            <ul>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('valorant')) ?>">Valorant Points</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('pubg')) ?>">PUBG UC</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('windows')) ?>">Windows Keys</a></li>
                <li><a href="<?= htmlspecialchars(Helpers::categoryUrl('design-tools')) ?>">Design Tools</a></li>
            </ul>
        </div>
        <div>
            <h5>Company</h5>
            <ul>
                <li><a href="/blog.php">Blog</a></li>
                <li><a href="#">About</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="/contact.php">Contact</a></li>
            </ul>
        </div>
    </div>
    <p class="site-footer__copy">&copy; <?= date('Y') ?> OyunHesap.com - All rights reserved.</p>
</footer>
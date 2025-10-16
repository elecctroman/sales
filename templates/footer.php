<?php

use App\Helpers;

$pageScripts = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$pageInlineScripts = isset($GLOBALS['pageInlineScripts']) && is_array($GLOBALS['pageInlineScripts']) ? $GLOBALS['pageInlineScripts'] : array();
?>
    </div>
</main>
<footer class="public-footer py-4 border-top">
    <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
        <small class="text-muted mb-0">&copy; <?= date('Y') ?> <?= Helpers::sanitize(Helpers::siteName()) ?></small>
        <div class="d-flex gap-3">
            <a class="text-decoration-none" href="/terms.php">Kullanım Şartları</a>
            <a class="text-decoration-none" href="/privacy.php">Gizlilik</a>
            <a class="text-decoration-none" href="/contact.php">İletişim</a>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php foreach ($pageInlineScripts as $inlineScript): ?>
    <script><?= $inlineScript ?></script>
<?php endforeach; ?>
</body>
</html>


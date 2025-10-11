<?php

use App\Helpers;

$languageBufferActive = !empty($GLOBALS['app_lang_buffer_started']);
$pageScripts = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$pageInlineScripts = isset($GLOBALS['pageInlineScripts']) && is_array($GLOBALS['pageInlineScripts']) ? $GLOBALS['pageInlineScripts'] : array();
?>
        </main>
        <footer class="app-footer">
            <small>© <?= date('Y') ?> <?= Helpers::sanitize(Helpers::siteName()) ?></small>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var toggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
    var closeTargets = document.querySelectorAll('[data-sidebar-close]');
    var body = document.body;
    var sidebar = document.getElementById('appSidebar');

    if (!toggleButtons.length || !sidebar) {
        return;
    }

    var openSidebar = function () {
        body.classList.add('app-sidebar-open');
        sidebar.setAttribute('aria-hidden', 'false');
        toggleButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
        });
    };

    var closeSidebar = function () {
        body.classList.remove('app-sidebar-open');
        sidebar.setAttribute('aria-hidden', 'true');
        toggleButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    };

    toggleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (body.classList.contains('app-sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    });

    closeTargets.forEach(function (target) {
        target.addEventListener('click', closeSidebar);
    });

    var syncStateForViewport = function () {
        if (window.innerWidth >= 992) {
            body.classList.remove('app-sidebar-open');
            sidebar.setAttribute('aria-hidden', 'false');
            toggleButtons.forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
            });
        } else {
            closeSidebar();
        }
    };

    syncStateForViewport();

    window.addEventListener('resize', syncStateForViewport);
})();
</script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php foreach ($pageInlineScripts as $inlineScript): ?>
    <script><?= $inlineScript ?></script>
<?php endforeach; ?>
</body>
</html>
<?php
if ($languageBufferActive) {
    ob_end_flush();
    unset($GLOBALS['app_lang_buffer_started']);
}
?>

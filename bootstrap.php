<?php
session_start();

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$configPath = __DIR__ . '/config/config.php';
$installerPath = __DIR__ . '/install.php';

if (!file_exists($configPath)) {
    if (file_exists($installerPath)) {
        header('Location: /install.php');
        exit;
    }

    include __DIR__ . '/templates/auth-header.php';
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Digital Commerce Platform</div>
                <p class="text-muted mt-2">Configuration missing</p>
            </div>
            <div class="alert alert-warning">
                <h5 class="alert-heading">Setup Required</h5>
                <p class="mb-2">Copy <code>config/config.sample.php</code> to <code>config/config.php</code> and provide your database credentials.</p>
                <ol class="mb-0 text-start">
                    <li>Copy the sample configuration file.</li>
                    <li>Update <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code> and optionally <code>DB_PORT</code>.</li>
                    <li>Create the database and import <code>schema.sql</code>.</li>
                    <li>Reload this page to continue.</li>
                </ol>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/auth-footer.php';
    exit;
}

require $configPath;

try {
    App\Database::initialize([
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASSWORD,
        'port' => defined('DB_PORT') ? DB_PORT : null,
        'socket' => defined('DB_SOCKET') ? DB_SOCKET : null,
    ]);
} catch (\PDOException $exception) {
    include __DIR__ . '/templates/auth-header.php';
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Digital Commerce Platform</div>
                <p class="text-muted mt-2">Database connection failed</p>
            </div>
            <div class="alert alert-danger">
                <h5 class="alert-heading">Connection Error</h5>
                <p class="mb-2">Check the database credentials in <code>config/config.php</code> and verify your database server.</p>
                <p class="mb-0 small text-muted">Details: <?= App\Helpers::sanitize($exception->getMessage()) ?></p>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/auth-footer.php';
    exit;
}

App\Lang::boot();

try {
    App\PageRepository::ensureSchema();
    App\PageRepository::ensureDefaultPages();
} catch (\Throwable $pageException) {
    // Ignore schema bootstrap failures to keep the application usable.
}

try {
    App\CouponService::ensureSchema();
} catch (\Throwable $couponException) {
    // Ignore coupon schema issues during bootstrap.
}

try {
    App\MenuRepository::bootstrap();
    App\MenuRepository::syncCategoryMenu();
    $GLOBALS['theme_nav_categories'] = App\MenuRepository::buildHeaderMenu();
    $GLOBALS['theme_footer_menus'] = App\MenuRepository::buildFooterMenu();
    $GLOBALS['admin_menu_sections'] = App\MenuRepository::buildAdminMenu(isset($_SESSION['user']) ? $_SESSION['user'] : null);
} catch (\Throwable $menuException) {
    // Menu services are optional for bootstrap; ignore failures to keep the application responsive.
}

if (!empty($_SESSION['user'])) {
    $freshUser = App\Auth::findUser((int)$_SESSION['user']['id']);
    if ($freshUser) {
        $_SESSION['user'] = $freshUser;

        if (isset($freshUser['status']) && $freshUser['status'] !== 'active') {
            App\Helpers::setFlash('error', 'Your account is inactive. Please contact support.');
            unset($_SESSION['user']);
            App\Helpers::redirect('/admin/login.php');
        }
    }
}



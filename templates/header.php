<?php

use App\Auth;
use App\Helpers;
use App\Database;
use App\Lang;
use App\FeatureToggle;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$pageHeadline = isset($pageTitle) ? $pageTitle : 'Panel';

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();
$metaDescription = Helpers::seoDescription();
$metaKeywords = Helpers::seoKeywords();

if (!isset($GLOBALS['app_lang_buffer_started'])) {
    $GLOBALS['app_lang_buffer_started'] = true;
    ob_start(function ($buffer) {
        return Lang::filterOutput($buffer);
    });
}

$menuSections = array();
$menuBadges = array();
$currentScript = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$isAdminArea = false;
$isAdminRole = $user ? Auth::isAdminRole($user['role']) : false;

if ($isAdminRole) {
    $isAdminArea = strpos($currentScript, '/admin/') === 0;

    try {
        $sidebarPdo = Database::connection();

        if (Auth::userHasRole($user, array('super_admin', 'admin', 'support'))) {
            $menuBadges['/admin/orders.php'] = (int)$sidebarPdo
                ->query("SELECT COUNT(*) FROM package_orders WHERE status IN ('pending','paid')")
                ->fetchColumn();
            $menuBadges['/admin/product-orders.php'] = (int)$sidebarPdo
                ->query("SELECT COUNT(*) FROM product_orders WHERE status IN ('pending','processing')")
                ->fetchColumn();
        }
    } catch (\Throwable $sidebarException) {
        $menuBadges = array();
    }
}

if ($user) {
    if ($isAdminRole && $isAdminArea) {
        $adminSections = array(
            array(
                'heading' => 'Genel',
                'items' => array(
                    array('label' => 'Genel Bakış', 'href' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php', 'icon' => 'bi-speedometer2', 'roles' => Auth::adminRoles()),
                    array('label' => 'Raporlar', 'href' => '/admin/reports.php', 'pattern' => '/admin/reports.php', 'icon' => 'bi-graph-up', 'roles' => array('super_admin', 'admin', 'finance')),
                    array('label' => 'Paketler', 'href' => '/admin/packages.php', 'pattern' => '/admin/packages.php', 'icon' => 'bi-box-seam', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Siparişler', 'href' => '/admin/orders.php', 'pattern' => '/admin/orders.php', 'icon' => 'bi-receipt', 'roles' => array('super_admin', 'admin', 'support'), 'badge' => isset($menuBadges['/admin/orders.php']) ? (int)$menuBadges['/admin/orders.php'] : 0),
                    array('label' => 'Ürün Siparişleri', 'href' => '/admin/product-orders.php', 'pattern' => '/admin/product-orders.php', 'icon' => 'bi-basket', 'roles' => array('super_admin', 'admin', 'support'), 'badge' => isset($menuBadges['/admin/product-orders.php']) ? (int)$menuBadges['/admin/product-orders.php'] : 0),
                    array('label' => 'Bayiler', 'href' => '/admin/users.php', 'pattern' => '/admin/users.php', 'icon' => 'bi-people', 'roles' => array('super_admin', 'admin')),
                ),
            ),
            array(
                'heading' => 'Ürün Yönetimi',
                'items' => array(
                    array('label' => 'Ürünler', 'href' => '/admin/products.php', 'pattern' => '/admin/products.php', 'icon' => 'bi-box', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Kategoriler', 'href' => '/admin/categories.php', 'pattern' => '/admin/categories.php', 'icon' => 'bi-diagram-3', 'roles' => array('super_admin', 'admin', 'content')),
                ),
            ),
            array(
                'heading' => 'WooCommerce',
                'items' => array(
                    array('label' => 'İçe Aktar', 'href' => '/admin/woocommerce-import.php', 'pattern' => '/admin/woocommerce-import.php', 'icon' => 'bi-file-arrow-up', 'roles' => array('super_admin', 'admin', 'content')),
                    array('label' => 'Dışa Aktar', 'href' => '/admin/woocommerce-export.php', 'pattern' => '/admin/woocommerce-export.php', 'icon' => 'bi-file-arrow-down', 'roles' => array('super_admin', 'admin', 'content')),
                ),
            ),
            array(
                'heading' => 'Finans & Destek',
                'items' => array(
                    array('label' => 'Bakiyeler', 'href' => '/admin/balances.php', 'pattern' => '/admin/balances.php', 'icon' => 'bi-cash-stack', 'roles' => array('super_admin', 'admin', 'finance')),
                    array('label' => 'Destek', 'href' => '/admin/support.php', 'pattern' => '/admin/support.php', 'icon' => 'bi-life-preserver', 'roles' => array('super_admin', 'admin', 'support')),
                ),
            ),
            array(
                'heading' => 'Ayarlar',
                'items' => array(
                    array('label' => 'Genel Ayarlar', 'href' => '/admin/settings-general.php', 'pattern' => '/admin/settings-general.php', 'icon' => 'bi-gear', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Mail Ayarları', 'href' => '/admin/settings-mail.php', 'pattern' => '/admin/settings-mail.php', 'icon' => 'bi-envelope-gear', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Telegram Entegrasyonu', 'href' => '/admin/settings-telegram.php', 'pattern' => '/admin/settings-telegram.php', 'icon' => 'bi-telegram', 'roles' => array('super_admin', 'admin')),
                    array('label' => 'Ödeme Methodları', 'href' => '/admin/settings-payments.php', 'pattern' => '/admin/settings-payments.php', 'icon' => 'bi-credit-card', 'roles' => array('super_admin', 'admin', 'finance')),
                ),
            ),
            array(
                'heading' => 'Denetim',
                'items' => array(
                    array('label' => 'Aktivite Kayıtları', 'href' => '/admin/activity-logs.php', 'pattern' => '/admin/activity-logs.php', 'icon' => 'bi-clipboard-data', 'roles' => array('super_admin', 'admin')),
                ),
            ),
        );

        $menuSections = array();
        foreach ($adminSections as $section) {
            $items = array();
            foreach ($section['items'] as $item) {
                $allowedRoles = isset($item['roles']) ? $item['roles'] : Auth::adminRoles();
                if (Auth::userHasRole($user, $allowedRoles)) {
                    $items[] = $item;
                }
            }

            if ($items) {
                $section['items'] = $items;
                $menuSections[] = $section;
            }
        }
    } else {
        $resellerItems = array(
            array('label' => 'Kontrol Paneli', 'href' => '/dashboard.php', 'pattern' => '/dashboard.php', 'icon' => 'bi-speedometer2'),
        );

        if (Helpers::featureEnabled('products')) {
            $resellerItems[] = array('label' => 'Ürünler', 'href' => '/products.php', 'pattern' => '/products.php', 'icon' => 'bi-box');
        }

        if (Helpers::featureEnabled('orders')) {
            $resellerItems[] = array('label' => 'Siparişlerim', 'href' => '/orders.php', 'pattern' => '/orders.php', 'icon' => 'bi-receipt');
        }

        if (Helpers::featureEnabled('balance')) {
            $resellerItems[] = array('label' => 'Bakiyem', 'href' => '/balance.php', 'pattern' => '/balance.php', 'icon' => 'bi-wallet2');
        }

        if (Helpers::featureEnabled('support')) {
            $resellerItems[] = array('label' => 'Destek', 'href' => '/support.php', 'pattern' => '/support.php', 'icon' => 'bi-life-preserver');
        }

        $resellerItems[] = array('label' => 'Profilim', 'href' => '/profile.php', 'pattern' => '/profile.php', 'icon' => 'bi-person');

        $menuSections = array(
            array(
                'heading' => 'Bayi Paneli',
                'items' => $resellerItems,
            ),
        );
    }
}
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helpers::sanitize($pageTitle) . ' | ' : '' ?><?= Helpers::sanitize($siteName) ?></title>
    <meta name="description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <meta name="keywords" content="<?= Helpers::sanitize($metaKeywords) ?>">
    <meta property="og:site_name" content="<?= Helpers::sanitize($siteName) ?>">
    <meta property="og:title" content="<?= Helpers::sanitize(isset($pageTitle) ? $pageTitle : $siteName) ?>">
    <meta property="og:description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <a href="<?= $isAdminArea ? '/admin/dashboard.php' : '/dashboard.php' ?>"><?= Helpers::sanitize($siteName) ?></a>
                <?php if ($siteTagline): ?>
                    <div class="sidebar-brand-tagline text-muted small"><?= Helpers::sanitize($siteTagline) ?></div>
                <?php endif; ?>
            </div>
            <div class="sidebar-user">
                <div class="sidebar-user-name"><?= Helpers::sanitize($user['name']) ?></div>
                <div class="sidebar-user-role text-uppercase"><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></div>
                <?php if (Helpers::featureEnabled('balance')): ?>
                    <div class="sidebar-user-balance">
                        <?= Helpers::sanitize('Bakiye') ?>:
                        <strong><?= Helpers::formatCurrency((float)$user['balance']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($menuSections as $section): ?>
                    <div class="sidebar-section">
                        <div class="sidebar-section-title"><?= Helpers::sanitize($section['heading']) ?></div>
                        <ul class="list-unstyled">
                            <?php foreach ($section['items'] as $item): ?>
                                <li>
                                    <?php $badge = isset($item['badge']) ? (int)$item['badge'] : 0; ?>
                                    <a href="<?= $item['href'] ?>" class="sidebar-link <?= Helpers::isActive($item['pattern']) ? 'active' : '' ?>">
                                        <?php if (!empty($item['icon'])): ?>
                                            <span class="sidebar-link-icon"><i class="<?= Helpers::sanitize($item['icon']) ?>"></i></span>
                                        <?php endif; ?>
                                        <span class="sidebar-link-text"><?= Helpers::sanitize($item['label']) ?></span>
                                        <?php if ($badge > 0): ?>
                                            <span class="sidebar-link-badge"><?= $badge ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="/logout.php" class="btn btn-outline-light w-100"><?= Helpers::sanitize('Çıkış Yap') ?></a>
            </div>
        </aside>
    <?php endif; ?>
    <div class="app-main d-flex flex-column flex-grow-1">
        <?php if ($user): ?>
            <header class="app-topbar d-flex align-items-center justify-content-between gap-3">
                <div>
                    <h1 class="h4 mb-1"><?= Helpers::sanitize($pageHeadline) ?></h1>
                    <p class="text-muted mb-0"><?= date('d F Y') ?></p>
                </div>
                <?php if ($isAdminRole && !$isAdminArea): ?>
                    <div class="d-flex align-items-center gap-2">
                        <a href="/admin/dashboard.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-speedometer2 me-1"></i> <?= Helpers::sanitize('Yönetim Paneli') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <main class="app-content flex-grow-1 container-fluid">

<?php

use App\Auth;
use App\Helpers;
use App\Database;
use App\FeatureToggle;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$pageHeadline = isset($pageTitle) ? $pageTitle : 'Panel';

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();
$metaDescription = Helpers::seoDescription();
$metaKeywords = Helpers::seoKeywords();

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
            $menuBadges['/admin/payment-notifications.php'] = (int)$sidebarPdo
                ->query("SELECT COUNT(*) FROM bank_transfer_notifications WHERE status = 'pending'")
                ->fetchColumn();
        }
    } catch (\Throwable $sidebarException) {
        $menuBadges = array();
    }
}

if ($user) {
    if ($isAdminRole && $isAdminArea) {
        $menuSections = isset($GLOBALS['admin_menu_sections']) && is_array($GLOBALS['admin_menu_sections'])
            ? $GLOBALS['admin_menu_sections']
            : array();

        if ($menuSections) {
            $applyBadgeMap = function (array $items) use (&$applyBadgeMap, $menuBadges) {
                $result = array();
                foreach ($items as $item) {
                    if (isset($item['href']) && isset($menuBadges[$item['href']])) {
                        $item['badge'] = (int)$menuBadges[$item['href']];
                    }
                    if (!empty($item['children']) && is_array($item['children'])) {
                        $item['children'] = $applyBadgeMap($item['children']);
                    }
                    $result[] = $item;
                }

                return $result;
            };

            foreach ($menuSections as &$section) {
                if (!empty($section['items'])) {
                    $section['items'] = $applyBadgeMap($section['items']);
                }
            }
            unset($section);
        }
    } else {
        $menuSections = array();
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
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
    <link href="/assets/admin/css/admin.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="app-sidebar" id="appSidebar" aria-hidden="true">
            <button type="button" class="sidebar-close-btn" data-sidebar-close>
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="sidebar-brand">
                <a href="<?= $isAdminArea ? '/admin/dashboard.php' : '/dashboard.php' ?>" class="sidebar-logo">
                    <span class="sidebar-logo-icon">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </span>
                    <span class="sidebar-logo-text">
                        <span class="sidebar-logo-headline"><?= Helpers::sanitize($siteName) ?></span>
                        <span class="sidebar-logo-subtitle"><?= $isAdminArea ? Helpers::sanitize('Admin Paneli') : Helpers::sanitize('Kontrol Paneli') ?></span>
                    </span>
                </a>
            </div>
            <?php if ($user): ?>
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <span><?= Helpers::sanitize(strtoupper(substr(isset($user['name']) ? $user['name'] : 'U', 0, 1))) ?></span>
                    </div>
                    <div class="sidebar-user-meta">
                        <div class="sidebar-user-name"><?= Helpers::sanitize(isset($user['name']) ? $user['name'] : '') ?></div>
                        <div class="sidebar-user-role"><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></div>
                        <?php if (Helpers::featureEnabled('balance') && !($isAdminRole && $isAdminArea)): ?>
                            <div class="sidebar-user-balance">
                                <?= Helpers::sanitize('Bakiye') ?>: <strong><?= Helpers::formatCurrency((float)$user['balance']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <nav class="sidebar-nav">
                <div class="sidebar-scroll">
                    <?php foreach ($menuSections as $section): ?>
                        <div class="sidebar-section">
                            <?php if (!empty($section['heading'])): ?>
                                <div class="sidebar-section-title"><?= Helpers::sanitize($section['heading']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($section['items'])): ?>
                                <ul class="sidebar-menu list-unstyled">
                                    <?php foreach ($section['items'] as $item): ?>
                                        <?php
                                        $hasChildren = !empty($item['children']);
                                        $itemPatterns = array();
                                        if (isset($item['pattern'])) {
                                            $itemPatterns = is_array($item['pattern']) ? $item['pattern'] : array($item['pattern']);
                                        }
                                        $itemActive = false;
                                        foreach ($itemPatterns as $pattern) {
                                            if ($pattern && Helpers::isActive($pattern)) {
                                                $itemActive = true;
                                                break;
                                            }
                                        }
                                        $childStates = array();
                                        if ($hasChildren) {
                                            foreach ($item['children'] as $childIndex => $childItem) {
                                                $childPattern = isset($childItem['pattern']) ? $childItem['pattern'] : '';
                                                $childActive = $childPattern ? Helpers::isActive($childPattern) : false;
                                                $childStates[$childIndex] = $childActive;
                                                if ($childActive) {
                                                    $itemActive = true;
                                                }
                                            }
                                        }
                                        ?>
                                        <li class="sidebar-item<?= $hasChildren ? ' has-children' : '' ?><?= $itemActive ? ' is-active' : '' ?><?= $hasChildren && $itemActive ? ' is-open' : '' ?>">
                                            <?php if ($hasChildren): ?>
                                                <button class="sidebar-link sidebar-toggle" type="button" data-menu-toggle aria-expanded="<?= $hasChildren && $itemActive ? 'true' : 'false' ?>">
                                                    <?php if (!empty($item['icon'])): ?>
                                                        <span class="sidebar-link-icon"><i class="<?= Helpers::sanitize($item['icon']) ?>"></i></span>
                                                    <?php endif; ?>
                                                    <span class="sidebar-link-text"><?= Helpers::sanitize($item['label']) ?></span>
                                                    <span class="sidebar-caret"><i class="bi bi-chevron-down"></i></span>
                                                </button>
                                                <ul class="sidebar-submenu list-unstyled">
                                                    <?php foreach ($item['children'] as $childIndex => $child): ?>
                                                        <?php $childActive = !empty($childStates[$childIndex]); ?>
                                                        <li>
                                                            <a href="<?= $child['href'] ?>" class="sidebar-sublink<?= $childActive ? ' active' : '' ?>">
                                                                <span class="sidebar-bullet"></span>
                                                                <span class="sidebar-link-text"><?= Helpers::sanitize($child['label']) ?></span>
                                                                <?php if (!empty($child['badge'])): ?>
                                                                    <span class="sidebar-badge"><?= (int)$child['badge'] ?></span>
                                                                <?php endif; ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <a href="<?= $item['href'] ?>" class="sidebar-link<?= $itemActive ? ' active' : '' ?>">
                                                    <?php if (!empty($item['icon'])): ?>
                                                        <span class="sidebar-link-icon"><i class="<?= Helpers::sanitize($item['icon']) ?>"></i></span>
                                                    <?php endif; ?>
                                                    <span class="sidebar-link-text"><?= Helpers::sanitize($item['label']) ?></span>
                                                    <?php if (!empty($item['badge'])): ?>
                                                        <span class="sidebar-badge"><?= (int)$item['badge'] ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </nav>
            <div class="sidebar-footer">
                <a href="/logout.php" class="sidebar-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span><?= Helpers::sanitize('Cikis Yap') ?></span>
                </a>
            </div>
        </aside>
        <div class="app-sidebar-backdrop" data-sidebar-close></div>
    <?php endif; ?>
    <div class="app-main d-flex flex-column flex-grow-1">
        <?php if ($user): ?>
            <header class="app-topbar d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-primary sidebar-mobile-toggle d-lg-none" type="button" data-sidebar-toggle aria-controls="appSidebar" aria-expanded="false" aria-label="<?= Helpers::sanitize('Menuyu Ac') ?>">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h4 mb-1"><?= Helpers::sanitize($pageHeadline) ?></h1>
                        <p class="text-muted mb-0"><?= date('d F Y') ?></p>
                    </div>
                </div>
                <?php if ($isAdminRole && !$isAdminArea): ?>
                    <div class="d-flex align-items-center gap-2">
                        <a href="/admin/dashboard.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-speedometer2 me-1"></i> <?= Helpers::sanitize('Yonetim Paneli') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <main class="app-content flex-grow-1 container-fluid">

<?php

use App\Helpers;
use App\Lang;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Lang::boot();

$siteName = Helpers::siteName();
$metaDescription = Helpers::seoDescription();
$metaKeywords = Helpers::seoKeywords();
$pageTitle = isset($pageTitle) ? $pageTitle : $siteName;

if (!isset($GLOBALS['app_lang_buffer_started'])) {
    $GLOBALS['app_lang_buffer_started'] = true;
    ob_start(function ($buffer) {
        return Lang::filterOutput($buffer);
    });
}
?>
<!DOCTYPE html>
<html lang="<?= Lang::htmlLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::sanitize($pageTitle) ?><?= $pageTitle !== $siteName ? ' | ' . Helpers::sanitize($siteName) : '' ?></title>
    <meta name="description" content="<?= Helpers::sanitize($metaDescription) ?>">
    <meta name="keywords" content="<?= Helpers::sanitize($metaKeywords) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<header class="public-header py-3 border-bottom">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <a href="/" class="navbar-brand fw-semibold mb-0"><?= Helpers::sanitize($siteName) ?></a>
        <nav class="d-flex align-items-center gap-3">
            <a class="nav-link px-2" href="/products.php">Ürünler</a>
            <a class="nav-link px-2" href="/support.php">Destek</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a class="btn btn-outline-primary btn-sm" href="/dashboard.php">Hesabım</a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="/login.php">Giriş Yap</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="public-main-content py-4">
    <div class="container">

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
        <a href="<?= htmlspecialchars(Helpers::absoluteUrl('/'), ENT_QUOTES, 'UTF-8') ?>" class="navbar-brand fw-semibold mb-0"><?= Helpers::sanitize($siteName) ?></a>
        <nav class="d-flex align-items-center gap-3">
            <a class="nav-link px-2" href="<?= htmlspecialchars(Helpers::catalogUrl(), ENT_QUOTES, 'UTF-8') ?>">Ürünler</a>
            <a class="nav-link px-2" href="<?= htmlspecialchars(Helpers::supportUrl(), ENT_QUOTES, 'UTF-8') ?>">Destek</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(Helpers::accountUrl(), ENT_QUOTES, 'UTF-8') ?>">Hesabım</a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(Helpers::loginUrl(), ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="public-main-content py-4">
    <div class="container">

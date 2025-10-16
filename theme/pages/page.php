<?php
use App\Helpers;

$pageData = isset($page) && is_array($page) ? $page : array();
$title = isset($pageData['title']) ? trim((string)$pageData['title']) : '';
$content = isset($pageData['content']) ? (string)$pageData['content'] : '';
$displayTitle = $title !== '' ? $title : 'Sayfa';
$bodyHtml = Helpers::sanitizePageHtml($content);
?>

<section class="static-page">
    <header class="static-page__header">
        <h1><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    </header>
    <article class="static-page__body">
        <?= $bodyHtml !== '' ? $bodyHtml : '<p class="static-page__empty">Bu sayfa için içerik bulunmuyor.</p>' ?>
    </article>
</section>

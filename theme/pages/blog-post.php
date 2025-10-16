<?php
use App\Helpers;

$postData = isset($post) && is_array($post) ? $post : array();
$title = isset($postData['title']) ? (string)$postData['title'] : 'Blog Yazısı';
$content = isset($postData['content']) ? (string)$postData['content'] : '';
$author = isset($postData['author_name']) ? (string)$postData['author_name'] : '';
$publishedAt = isset($postData['published_at']) ? $postData['published_at'] : null;
$featuredImage = isset($postData['featured_image']) ? (string)$postData['featured_image'] : '';
$bodyHtml = Helpers::sanitizePageHtml($content);

$publishedHuman = '';
$publishedIso = '';
if ($publishedAt) {
    $timestamp = strtotime($publishedAt);
    if ($timestamp) {
        $publishedHuman = date('d M Y', $timestamp);
        $publishedIso = date('c', $timestamp);
    }
}
?>

<section class="blog-post">
    <?php if ($featuredImage !== ''): ?>
        <div class="blog-post__hero">
            <img src="<?= htmlspecialchars($featuredImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
        </div>
    <?php endif; ?>

    <article class="blog-post__article">
        <header class="blog-post__header">
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="blog-post__meta">
                <?php if ($author !== ''): ?>
                    <span class="blog-post__author">Yazar: <?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($publishedHuman !== ''): ?>
                    <time datetime="<?= htmlspecialchars($publishedIso, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($publishedHuman, ENT_QUOTES, 'UTF-8') ?></time>
                <?php endif; ?>
            </div>
        </header>

        <div class="blog-post__content">
            <?= $bodyHtml !== '' ? $bodyHtml : '<p class="blog-post__empty">Bu yazı için içerik eklenmemiş.</p>' ?>
        </div>

        <footer class="blog-post__footer">
            <a class="blog-post__back" href="/blog">← Bloga geri dön</a>
        </footer>
    </article>
</section>

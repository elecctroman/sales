<?php
use App\Helpers;

$page = isset($pageContext['page']) ? $pageContext['page'] : null;
$breadcrumbs = isset($pageContext['breadcrumbs']) ? $pageContext['breadcrumbs'] : array();

if (!$page) {
    ?>
    <section class="page-content">
        <div class="page-content__header">
            <h1>Sayfa bulunamadı</h1>
            <p class="text-muted">İstediğiniz sayfa yayında değil veya kaldırıldı.</p>
            <a class="btn btn-primary" href="<?= htmlspecialchars(Helpers::absoluteUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Ana sayfaya dön</a>
        </div>
    </section>
    <?php
    return;
}

$title = isset($page['title']) ? (string)$page['title'] : 'Sayfa';
$summary = isset($page['summary']) ? (string)$page['summary'] : '';
$content = isset($page['content']) ? (string)$page['content'] : '';
$heroImage = isset($page['hero_image']) ? trim((string)$page['hero_image']) : '';
$template = isset($page['template']) ? (string)$page['template'] : 'default';
$publishedAt = isset($page['published_at']) ? (string)$page['published_at'] : (isset($page['created_at']) ? (string)$page['created_at'] : null);
$publishedHuman = $publishedAt ? date('d M Y', strtotime($publishedAt)) : null;
?>
<section class="page-content page-content--<?= htmlspecialchars($template, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($heroImage !== ''): ?>
        <div class="page-content__hero" style="background-image: url('<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>');"></div>
    <?php endif; ?>
    <div class="page-content__body">
        <?php if ($breadcrumbs): ?>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <li>
                            <?php if (!empty($crumb['url'])): ?>
                                <a href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                                <span><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>
        <header class="page-content__header">
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($summary !== ''): ?>
                <p class="page-content__summary"><?= nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endif; ?>
            <?php if ($publishedHuman): ?>
                <div class="page-content__meta">Güncelleme: <?= htmlspecialchars($publishedHuman, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </header>
        <article class="page-content__article">
            <?= $content !== '' ? $content : '<p class="text-muted">Bu sayfa için içerik hazırlanıyor.</p>' ?>
        </article>
    </div>
</section>

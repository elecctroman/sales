<?php
use App\Helpers;

$blogPosts = isset($posts) && is_array($posts) ? $posts : array();
$blogPagination = isset($pagination) && is_array($pagination) ? $pagination : array();
$currentPage = isset($blogPagination['page']) ? (int)$blogPagination['page'] : 1;
$totalPages = isset($blogPagination['pages']) ? (int)$blogPagination['pages'] : 0;
?>

<section class="blog">
    <div class="section-header">
        <div>
            <h1>Blog</h1>
            <small>İpuçları, güncellemeler ve e-ticaret haberleri</small>
        </div>
        <a class="section-link" href="/">Ana sayfaya dön</a>
    </div>

    <?php if ($blogPosts): ?>
        <div class="blog__grid">
            <?php foreach ($blogPosts as $post): ?>
                <?php
                    $postTitle = isset($post['title']) ? (string)$post['title'] : '';
                    $postDate = isset($post['date']) && $post['date'] !== '' ? (string)$post['date'] : (isset($post['date_label']) ? (string)$post['date_label'] : '');
                    $postExcerpt = isset($post['excerpt']) ? (string)$post['excerpt'] : '';
                    $postImage = isset($post['image']) ? (string)$post['image'] : '';
                    $postSlug = isset($post['slug']) ? (string)$post['slug'] : '';
                    $postUrl = isset($post['url']) && $post['url'] !== '' ? (string)$post['url'] : ($postSlug !== '' ? '/blog/' . $postSlug : '#');
                ?>
                <article class="blog-card">
                    <?php if ($postImage !== ''): ?>
                        <img src="<?= htmlspecialchars($postImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <?php if ($postDate !== ''): ?>
                        <small><?= htmlspecialchars($postDate, ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if ($postExcerpt !== ''): ?>
                        <p><?= htmlspecialchars($postExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8') ?>">Devamını Oku</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="blog__empty">Henüz yayınlanmış bir blog içeriği bulunmuyor.</p>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <?php
            $window = 2;
            $startPage = max(1, $currentPage - $window);
            $endPage = min($totalPages, $currentPage + $window);
        ?>
        <nav class="blog-pagination" aria-label="Blog sayfalama">
            <?php if ($currentPage > 1): ?>
                <a class="blog-pagination__link blog-pagination__link--prev" href="<?= htmlspecialchars(Helpers::replaceQueryParameters('/blog', array('page' => $currentPage - 1), array('remove' => array('page'))), ENT_QUOTES, 'UTF-8') ?>" aria-label="Önceki sayfa">Önceki</a>
            <?php endif; ?>

            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                <a class="blog-pagination__link<?= $page === $currentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Helpers::replaceQueryParameters('/blog', array('page' => $page), array('remove' => array('page'))), ENT_QUOTES, 'UTF-8') ?>">
                    <?= $page ?>
                </a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a class="blog-pagination__link blog-pagination__link--next" href="<?= htmlspecialchars(Helpers::replaceQueryParameters('/blog', array('page' => $currentPage + 1), array('remove' => array('page'))), ENT_QUOTES, 'UTF-8') ?>" aria-label="Sonraki sayfa">Sonraki</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php
use App\Helpers;

$blog = isset($blog) ? $blog : (isset($blogContext) ? $blogContext : array());
$view = isset($blog['view']) ? $blog['view'] : 'list';
$categories = isset($blog['categories']) && is_array($blog['categories']) ? $blog['categories'] : array();
$tags = isset($blog['tags']) && is_array($blog['tags']) ? $blog['tags'] : array();
$searchTerm = isset($blog['search']) ? (string)$blog['search'] : '';
$feedback = isset($blog['feedback']) && is_array($blog['feedback']) ? $blog['feedback'] : array('errors' => array(), 'success' => '');
$filters = isset($blog['filters']) && is_array($blog['filters']) ? $blog['filters'] : array();
$recent = isset($blog['recent']) && is_array($blog['recent']) ? $blog['recent'] : array();
$related = isset($blog['related']) && is_array($blog['related']) ? $blog['related'] : array();
$pagination = isset($blog['pagination']) && is_array($blog['pagination']) ? $blog['pagination'] : array('page' => 1, 'pages' => 1);
?>
<section class="blog">
    <?php if ($view === 'detail' && !empty($blog['post'])): ?>
        <?php $post = $blog['post']; ?>
        <?php $cover = isset($post['cover_image']) && $post['cover_image'] !== '' ? $post['cover_image'] : '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg'; ?>
        <?php $publishedAt = isset($post['published_at']) && $post['published_at'] ? (string)$post['published_at'] : (isset($post['created_at']) ? (string)$post['created_at'] : null); ?>
        <?php $publishedHuman = $publishedAt ? date('d M Y', strtotime($publishedAt)) : ''; ?>
        <article class="blog-detail">
            <header class="blog-detail__header">
                <div class="blog-detail__meta">
                    <?php if ($publishedHuman !== ''): ?>
                        <span class="blog-detail__meta-item">
                            <span class="material-icons" aria-hidden="true">event</span>
                            <?= htmlspecialchars($publishedHuman, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($post['reading_time'])): ?>
                        <span class="blog-detail__meta-item">
                            <span class="material-icons" aria-hidden="true">schedule</span>
                            <?= (int)$post['reading_time'] ?> dk okuma
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($post['category_name']) && !empty($post['category_slug'])): ?>
                        <a class="blog-detail__meta-item" href="<?= htmlspecialchars(Helpers::blogCategoryUrl($post['category_slug']), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="material-icons" aria-hidden="true">folder_open</span>
                            <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif; ?>
                </div>
                <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($post['summary'])): ?>
                    <p class="blog-detail__summary"><?= nl2br(htmlspecialchars($post['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
            </header>
            <div class="blog-detail__cover">
                <img src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
            </div>
            <div class="blog-detail__content">
                <?= isset($post['content']) && $post['content'] !== '' ? $post['content'] : '<p class="text-muted">İçerik hazırlanıyor.</p>' ?>
            </div>
            <?php if (!empty($post['tags'])): ?>
                <footer class="blog-detail__footer">
                    <div class="blog-tags">
                        <span class="blog-tags__label">Etiketler:</span>
                        <?php foreach ($post['tags'] as $tag): ?>
                            <a class="blog-tag" href="<?= htmlspecialchars(Helpers::blogTagUrl($tag['slug']), ENT_QUOTES, 'UTF-8') ?>">#<?= htmlspecialchars($tag['name'] ?? $tag['slug'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </div>
                </footer>
            <?php endif; ?>
            <section class="blog-comments" id="yorumlar">
                <header class="blog-comments__header">
                    <h2>Yorumlar</h2>
                    <small><?= isset($post['comments']) ? count($post['comments']) : 0 ?> yorum</small>
                </header>
                <?php if (!empty($feedback['success'])): ?>
                    <div class="alert alert-success" role="status" aria-live="polite">
                        <strong>Teşekkürler!</strong>
                        <p class="mb-0"><?= htmlspecialchars($feedback['success'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($feedback['errors'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul>
                            <?php foreach ($feedback['errors'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($post['comments'])): ?>
                    <ul class="blog-comments__list">
                        <?php foreach ($post['comments'] as $comment): ?>
                            <?php $commentDate = isset($comment['created_at']) ? date('d.m.Y H:i', strtotime($comment['created_at'])) : ''; ?>
                            <li class="blog-comments__item">
                                <div class="blog-comments__avatar" aria-hidden="true">
                                    <span class="material-icons">person</span>
                                </div>
                                <div class="blog-comments__body">
                                    <header>
                                        <strong><?= htmlspecialchars($comment['author'] ?? ($comment['author_name'] ?? 'Ziyaretçi'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($commentDate !== ''): ?>
                                            <span><?= htmlspecialchars($commentDate, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </header>
                                    <p><?= nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Henüz yorum yapılmamış. İlk yorumu siz yazın.</p>
                <?php endif; ?>
                <form class="blog-comments__form" method="post" action="<?= htmlspecialchars(Helpers::blogPostUrl($post), ENT_QUOTES, 'UTF-8') ?>#yorumlar">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="post_slug" value="<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="blog-comments__form-grid">
                        <div class="form-group">
                            <label for="comment-name">Adınız</label>
                            <input id="comment-name" type="text" name="author_name" placeholder="Adınız">
                        </div>
                        <div class="form-group">
                            <label for="comment-email">E-posta</label>
                            <input id="comment-email" type="email" name="author_email" placeholder="ornek@mail.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="comment-message">Yorumunuz</label>
                        <textarea id="comment-message" name="content" rows="4" required placeholder="Yorumunuzu yazınız"></textarea>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Yorumu Gönder</button>
                    </div>
                </form>
            </section>
            <?php if ($related): ?>
                <section class="blog-related">
                    <h3>Benzer Yazılar</h3>
                    <div class="blog-related__grid">
                        <?php foreach ($related as $item): ?>
                            <article class="blog-card">
                                <?php $coverRelated = isset($item['cover_image']) && $item['cover_image'] !== '' ? $item['cover_image'] : '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg'; ?>
                                <img src="<?= htmlspecialchars($coverRelated, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                                <small><?= htmlspecialchars($item['published_human'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                                <h3><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($item['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <a href="<?= htmlspecialchars(Helpers::blogPostUrl($item), ENT_QUOTES, 'UTF-8') ?>">Devamını oku</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
    <?php else: ?>
        <header class="blog__header">
            <div>
                <h1>Blog</h1>
                <p>İpuçları, rehberler ve sektör haberleri</p>
            </div>
            <form class="blog__search" method="get" action="<?= htmlspecialchars(Helpers::blogUrl(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="search" name="search" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" placeholder="Anahtar kelime ara">
                <button type="submit" class="btn btn-primary">Ara</button>
            </form>
        </header>
        <div class="blog__filters">
            <?php if ($categories): ?>
                <div class="blog__filter-group">
                    <span>Kategoriler:</span>
                    <a class="chip<?= empty($filters['category']) ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Helpers::blogUrl(), ENT_QUOTES, 'UTF-8') ?>">Tümü</a>
                    <?php foreach ($categories as $category): ?>
                        <?php if (empty($category['slug'])) { continue; } ?>
                        <a class="chip<?= isset($filters['category']) && $filters['category'] === $category['slug'] ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Helpers::blogCategoryUrl($category['slug']), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($tags): ?>
                <div class="blog__filter-group">
                    <span>Etiketler:</span>
                    <?php foreach ($tags as $tag): ?>
                        <?php if (empty($tag['slug'])) { continue; } ?>
                        <a class="chip chip--light<?= isset($filters['tag']) && $filters['tag'] === $tag['slug'] ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Helpers::blogTagUrl($tag['slug']), ENT_QUOTES, 'UTF-8') ?>">
                            #<?= htmlspecialchars($tag['name'] ?? $tag['slug'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="blog__grid">
            <?php foreach ($blog['posts'] ?? array() as $post): ?>
                <?php $cover = isset($post['cover_image']) && $post['cover_image'] !== '' ? $post['cover_image'] : '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg'; ?>
                <article class="blog-card">
                    <img src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                    <small><?= htmlspecialchars($post['published_human'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    <h3><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($post['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="<?= htmlspecialchars($post['url'] ?? Helpers::blogPostUrl($post), ENT_QUOTES, 'UTF-8') ?>">Devamını oku</a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($pagination['pages']) && $pagination['pages'] > 1): ?>
            <nav class="pagination" aria-label="Blog sayfaları">
                <?php for ($page = 1; $page <= (int)$pagination['pages']; $page++): ?>
                    <?php $query = array();
                    if (!empty($filters['category'])) { $query['category_slug'] = $filters['category']; }
                    if (!empty($filters['tag'])) { $query['tag_slug'] = $filters['tag']; }
                    if ($searchTerm !== '') { $query['search'] = $searchTerm; }
                    if ($page > 1) { $query['page'] = $page; }
                    $pageUrl = Helpers::blogUrl();
                    if (!empty($filters['category'])) { $pageUrl = Helpers::blogCategoryUrl($filters['category']); }
                    elseif (!empty($filters['tag'])) { $pageUrl = Helpers::blogTagUrl($filters['tag']); }
                    if ($query) { $pageUrl .= '?' . http_build_query($query); }
                    ?>
                    <a class="pagination__link<?= (int)$pagination['page'] === $page ? ' is-active' : '' ?>" href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $page ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
        <?php if ($recent): ?>
            <aside class="blog-sidebar">
                <h3>Son Yazılar</h3>
                <ul>
                    <?php foreach ($recent as $post): ?>
                        <li>
                            <a href="<?= htmlspecialchars($post['url'], ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                <small><?= htmlspecialchars($post['published_human'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
use App\Helpers;
$canShop = !empty($isLoggedIn);
$sliderData = isset($slider) && is_array($slider) ? $slider : array();
$mainSlides = isset($sliderData['mainSlides']) && is_array($sliderData['mainSlides']) ? $sliderData['mainSlides'] : array();
$sideBanners = isset($sliderData['sideBanners']) && is_array($sliderData['sideBanners']) ? $sliderData['sideBanners'] : array();
$instagramBlock = isset($sliderData['instagram']) && is_array($sliderData['instagram']) ? $sliderData['instagram'] : array();
$sliderCategories = isset($sliderData['categories']) && is_array($sliderData['categories']) ? $sliderData['categories'] : array();
?>

<section class="hero">
    <div class="hero__grid">
        <div class="hero__main">
            <div class="hero__slides" data-hero-slider>
                <?php foreach ($mainSlides as $index => $slide): ?>
                    <?php
                        $badge = isset($slide['badge']) ? (string)$slide['badge'] : '';
                        $title = isset($slide['title']) ? (string)$slide['title'] : '';
                        $subtitle = isset($slide['subtitle']) ? (string)$slide['subtitle'] : '';
                        $ctaText = isset($slide['cta_text']) ? (string)$slide['cta_text'] : '';
                        $ctaUrl = isset($slide['cta_url']) ? (string)$slide['cta_url'] : '';
                        $image = isset($slide['image']) ? (string)$slide['image'] : '';
                        $mediaImage = isset($slide['media_image']) ? (string)$slide['media_image'] : '';
                        $footerLogo = isset($slide['footer_logo']) ? (string)$slide['footer_logo'] : '';
                    ?>
                    <article class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>" data-hero-slide>
                        <?php if ($image !== ''): ?>
                            <span class="hero-slide__background" style="background-image: url('<?= htmlspecialchars($image) ?>');" aria-hidden="true"></span>
                        <?php endif; ?>
                        <div class="hero-slide__content">
                            <?php if ($badge !== ''): ?>
                                <span class="hero-slide__badge"><?= htmlspecialchars($badge) ?></span>
                            <?php endif; ?>
                            <?php if ($title !== ''): ?>
                                <h2><?= htmlspecialchars($title) ?></h2>
                            <?php endif; ?>
                            <?php if ($subtitle !== ''): ?>
                                <p><?= htmlspecialchars($subtitle) ?></p>
                            <?php endif; ?>
                            <div class="hero-slide__footer">
                                <?php if ($ctaText !== '' && $ctaUrl !== ''): ?>
                                    <a class="hero-slide__cta btn btn-primary" href="<?= htmlspecialchars($ctaUrl) ?>"><?= htmlspecialchars($ctaText) ?></a>
                                <?php endif; ?>
                                <?php if ($footerLogo !== ''): ?>
                                    <img class="hero-slide__logo" src="<?= htmlspecialchars($footerLogo) ?>" alt="">
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($mediaImage !== ''): ?>
                            <span class="hero-slide__media">
                                <img src="<?= htmlspecialchars($mediaImage) ?>" alt="<?= htmlspecialchars($title !== '' ? $title : 'Slide media') ?>">
                            </span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (count($mainSlides) > 1): ?>
                <button type="button" class="hero-slider__nav hero-slider__nav--prev" data-hero-prev aria-label="Önceki slider">
                    <span class="material-icons" aria-hidden="true">chevron_left</span>
                </button>
                <button type="button" class="hero-slider__nav hero-slider__nav--next" data-hero-next aria-label="Sonraki slider">
                    <span class="material-icons" aria-hidden="true">chevron_right</span>
                </button>
                <div class="hero-slider__dots" data-hero-dots>
                    <?php foreach ($mainSlides as $index => $slide): ?>
                        <button type="button" class="hero-slider__dot<?= $index === 0 ? ' is-active' : '' ?>" data-hero-dot="<?= (int)$index ?>" aria-label="Slide <?= (int)$index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($sideBanners)): ?>
            <div class="hero__aside">
                <?php foreach ($sideBanners as $banner): ?>
                    <?php
                        $bannerTitle = isset($banner['title']) ? (string)$banner['title'] : '';
                        $bannerSubtitle = isset($banner['subtitle']) ? (string)$banner['subtitle'] : '';
                        $bannerImage = isset($banner['image']) ? (string)$banner['image'] : '';
                        $bannerCtaText = isset($banner['cta_text']) ? (string)$banner['cta_text'] : '';
                        $bannerCtaUrl = isset($banner['cta_url']) ? (string)$banner['cta_url'] : '';
                        $bannerLogo = isset($banner['footer_logo']) ? (string)$banner['footer_logo'] : '';
                    ?>
                    <article class="hero-aside-card">
                        <?php if ($bannerImage !== ''): ?>
                            <span class="hero-aside-card__background" style="background-image: url('<?= htmlspecialchars($bannerImage) ?>');" aria-hidden="true"></span>
                        <?php endif; ?>
                        <div class="hero-aside-card__content">
                            <?php if ($bannerTitle !== ''): ?>
                                <h3><?= htmlspecialchars($bannerTitle) ?></h3>
                            <?php endif; ?>
                            <?php if ($bannerSubtitle !== ''): ?>
                                <p><?= htmlspecialchars($bannerSubtitle) ?></p>
                            <?php endif; ?>
                            <div class="hero-aside-card__footer">
                                <?php if ($bannerCtaText !== '' && $bannerCtaUrl !== ''): ?>
                                    <a class="hero-aside-card__cta" href="<?= htmlspecialchars($bannerCtaUrl) ?>"><?= htmlspecialchars($bannerCtaText) ?></a>
                                <?php endif; ?>
                                <?php if ($bannerLogo !== ''): ?>
                                    <img class="hero-aside-card__logo" src="<?= htmlspecialchars($bannerLogo) ?>" alt="">
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
        $instagramBg = isset($instagramBlock['background']) ? trim((string)$instagramBlock['background']) : '';
        $instagramHeadline = isset($instagramBlock['headline']) ? (string)$instagramBlock['headline'] : '';
        $instagramSubhead = isset($instagramBlock['subhead']) ? (string)$instagramBlock['subhead'] : '';
        $instagramHandle = isset($instagramBlock['handle']) ? (string)$instagramBlock['handle'] : '';
        $instagramHandleUrl = isset($instagramBlock['handle_url']) ? (string)$instagramBlock['handle_url'] : '';
        $instagramCtaText = isset($instagramBlock['cta_text']) ? (string)$instagramBlock['cta_text'] : '';
        $instagramCtaUrl = isset($instagramBlock['cta_url']) ? (string)$instagramBlock['cta_url'] : '';
    ?>
    <?php if ($instagramHeadline !== '' || $instagramHandle !== ''): ?>
        <div class="hero-instagram" style="<?= $instagramBg !== '' ? 'background:' . htmlspecialchars($instagramBg) : '' ?>">
            <div class="hero-instagram__left">
                <span class="hero-instagram__icon">
                    <span class="iconify" data-icon="mdi:instagram" aria-hidden="true"></span>
                </span>
                <div>
                    <?php if ($instagramHeadline !== ''): ?>
                        <strong><?= htmlspecialchars($instagramHeadline) ?></strong>
                    <?php endif; ?>
                    <?php if ($instagramSubhead !== ''): ?>
                        <span><?= htmlspecialchars($instagramSubhead) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-instagram__right">
                <?php if ($instagramHandle !== ''): ?>
                    <a class="hero-instagram__handle" href="<?= htmlspecialchars($instagramHandleUrl !== '' ? $instagramHandleUrl : '#') ?>" target="_blank" rel="noopener">
                        <span class="iconify" data-icon="mdi:instagram" aria-hidden="true"></span>
                        <span><?= htmlspecialchars($instagramHandle) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($instagramCtaText !== ''): ?>
                    <a class="hero-instagram__cta" href="<?= htmlspecialchars($instagramCtaUrl !== '' ? $instagramCtaUrl : '#') ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($instagramCtaText) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($sliderCategories)): ?>
        <div class="hero-categories">
            <?php foreach ($sliderCategories as $category): ?>
                <?php
                    $cardTitle = isset($category['title']) ? (string)$category['title'] : '';
                    $cardSubtitle = isset($category['subtitle']) ? (string)$category['subtitle'] : '';
                    $cardImage = isset($category['image']) ? (string)$category['image'] : '';
                    $cardLink = isset($category['link']) ? (string)$category['link'] : '#';
                    $cardLogo = isset($category['footer_logo']) ? (string)$category['footer_logo'] : '';
                ?>
                <a class="hero-category-card" href="<?= htmlspecialchars($cardLink !== '' ? $cardLink : '#') ?>">
                    <?php if ($cardImage !== ''): ?>
                        <span class="hero-category-card__media">
                            <img src="<?= htmlspecialchars($cardImage) ?>" alt="<?= htmlspecialchars($cardTitle !== '' ? $cardTitle : 'Kategori') ?>">
                        </span>
                    <?php endif; ?>
                    <div class="hero-category-card__body">
                        <?php if ($cardTitle !== ''): ?>
                            <strong><?= htmlspecialchars($cardTitle) ?></strong>
                        <?php endif; ?>
                        <?php if ($cardSubtitle !== ''): ?>
                            <span><?= htmlspecialchars($cardSubtitle) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($cardLogo !== ''): ?>
                        <span class="hero-category-card__logo">
                            <img src="<?= htmlspecialchars($cardLogo) ?>" alt="">
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-header">
        <div>
            <h2>Gündemdeki Ürünler</h2>
            <small>Topluluğun en çok tercih ettiği paketler</small>
        </div>
        <a class="section-link" href="<?= htmlspecialchars(Helpers::categoryUrl(''), ENT_QUOTES, 'UTF-8') ?>">Tümünü Gör</a>
    </div>
    <div class="section-carousel">
        <?php foreach ($featuredProducts ?? [] as $product): ?>
            <?php
                $productName = isset($product['name']) ? (string)$product['name'] : 'Product';
                $categoryName = isset($product['category_name']) ? (string)$product['category_name'] : '';
                $summary = isset($product['summary']) && $product['summary'] !== '' ? (string)$product['summary'] : (isset($product['description']) ? (string)$product['description'] : '');
                $stockLabel = isset($product['stock_label']) ? (string)$product['stock_label'] : '';
                $stockText = $stockLabel !== '' ? $stockLabel : (!empty($product['in_stock']) ? 'In stock' : 'Out of stock');
                $priceDisplay = isset($product['price_formatted']) ? (string)$product['price_formatted'] : (isset($product['price']) ? \App\Helpers::formatCurrency((float)$product['price']) : '$0.00');
                $imageSource = isset($product['image']) && $product['image'] !== '' ? $product['image'] : '/theme/assets/images/placeholder.png';
            ?>
            <article class="product-card product-card--feature<?= !empty($product['in_stock']) ? '' : ' product-card--out' ?>" data-product-card>
                <div class="product-card__media">
                    <img src="<?= htmlspecialchars($imageSource) ?>" alt="<?= htmlspecialchars($productName) ?>">
                </div>
                <div class="product-card__body">
                    <div class="product-card__meta">
                        <h3><?= htmlspecialchars($productName) ?></h3>
                        <?php if ($categoryName !== ''): ?>
                            <p class="product-card__category"><?= htmlspecialchars($categoryName) ?></p>
                        <?php endif; ?>
                        <?php if ($summary !== ''): ?>
                            <p class="product-card__summary"><?= htmlspecialchars($summary) ?></p>
                        <?php endif; ?>
                        <div class="product-card__rating" aria-label="Customer rating">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <span class="material-icons" aria-hidden="true">star_border</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="product-card__info">
                        <span class="product-card__stock<?= !empty($product['in_stock']) ? ' product-card__stock--in' : ' product-card__stock--out' ?>">
                            <span class="material-icons" aria-hidden="true"><?= !empty($product['in_stock']) ? 'check_circle' : 'cancel' ?></span>
                            <?= htmlspecialchars($stockText) ?>
                        </span>
                    </div>
                    <div class="product-card__footer">
                        <span class="product-card__price"><?= htmlspecialchars($priceDisplay) ?></span>
                        <?php if ($canShop): ?>
                            <button
                                type="button"
                                class="product-card__button<?= !empty($product['in_stock']) ? '' : ' is-disabled' ?>"
                                data-add-to-cart
                                data-product-id="<?= (int)$product['id'] ?>"
                                data-product-name="<?= htmlspecialchars($productName) ?>"
                                <?= !empty($product['in_stock']) ? '' : 'disabled' ?>
                            >
                                Sepete Ekle
                            </button>
                        <?php else: ?>
                            <a class="product-card__button product-card__button--ghost" href="/login.php">Giris Yap</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php foreach ($sections ?? [] as $section): ?>
    <section class="section" id="<?= htmlspecialchars($section['id']) ?>">
        <div class="section-header">
            <div>
                <h2><?= htmlspecialchars($section['title']) ?></h2>
                <small>Exclusive deals updated daily</small>
            </div>
            <?php
                $sectionPath = isset($section['id']) ? (string)$section['id'] : '';
                $sectionUrl = $sectionPath !== '' ? Helpers::categoryUrl($sectionPath) : Helpers::categoryUrl('');
            ?>
            <a class="section-link" href="<?= htmlspecialchars($sectionUrl, ENT_QUOTES, 'UTF-8') ?>">Tümünü Gör</a>
        </div>
        <div class="section-carousel">
                        <?php foreach ($section['products'] as $product): ?>
                <?php
                    $productName = isset($product['name']) ? (string)$product['name'] : 'Product';
                    $categoryName = isset($product['category_name']) ? (string)$product['category_name'] : '';
                    $summary = isset($product['summary']) && $product['summary'] !== '' ? (string)$product['summary'] : (isset($product['description']) ? (string)$product['description'] : '');
                    $stockLabel = isset($product['stock_label']) ? (string)$product['stock_label'] : '';
                    $stockText = $stockLabel !== '' ? $stockLabel : (!empty($product['in_stock']) ? 'In stock' : 'Out of stock');
                    $priceDisplay = isset($product['price_formatted']) ? (string)$product['price_formatted'] : (isset($product['price']) ? \App\Helpers::formatCurrency((float)$product['price']) : '$0.00');
                    $imageSource = isset($product['image']) && $product['image'] !== '' ? $product['image'] : '/theme/assets/images/placeholder.png';
                ?>
                <article class="product-card product-card--accent<?= !empty($product['in_stock']) ? '' : ' product-card--out' ?>" style="--accent-color: <?= htmlspecialchars($section['accent']) ?>;" data-product-card>
                    <div class="product-card__media">
                        <img src="<?= htmlspecialchars($imageSource) ?>" alt="<?= htmlspecialchars($productName) ?>">
                    </div>
                    <div class="product-card__body">
                        <?php if (!empty($product['tag'])): ?>
                            <span class="product-card__badge"><?= htmlspecialchars($product['tag']) ?></span>
                        <?php endif; ?>
                        <div class="product-card__meta">
                            <h3><?= htmlspecialchars($productName) ?></h3>
                            <?php if ($categoryName !== ''): ?>
                                <p class="product-card__category"><?= htmlspecialchars($categoryName) ?></p>
                            <?php endif; ?>
                            <?php if ($summary !== ''): ?>
                                <p class="product-card__summary"><?= htmlspecialchars($summary) ?></p>
                            <?php endif; ?>
                            <div class="product-card__rating" aria-label="Customer rating">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <span class="material-icons" aria-hidden="true">star_border</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="product-card__info">
                            <span class="product-card__stock<?= !empty($product['in_stock']) ? ' product-card__stock--in' : ' product-card__stock--out' ?>">
                                <span class="material-icons" aria-hidden="true"><?= !empty($product['in_stock']) ? 'check_circle' : 'cancel' ?></span>
                                <?= htmlspecialchars($stockText) ?>
                            </span>
                        </div>
                        <div class="product-card__footer">
                            <span class="product-card__price"><?= htmlspecialchars($priceDisplay) ?></span>
                            <?php if ($canShop): ?>
                                <button
                                    type="button"
                                    class="product-card__button<?= !empty($product['in_stock']) ? '' : ' is-disabled' ?>"
                                    data-add-to-cart
                                    data-product-id="<?= (int)$product['id'] ?>"
                                    data-product-name="<?= htmlspecialchars($productName) ?>"
                                    <?= !empty($product['in_stock']) ? '' : 'disabled' ?>
                                >
                                    Sepete Ekle
                                </button>
                            <?php else: ?>
                                <a class="product-card__button product-card__button--ghost" href="/login.php">Giris Yap</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<section class="section blog">
    <div class="section-header">
        <div>
            <h2>Blog</h2>
            <small>Guides and industry news</small>
        </div>
        <a class="section-link" href="/blog">Visit blog</a>
    </div>
    <div class="blog__grid">
        <?php foreach ($blogPosts ?? [] as $post): ?>
            <article class="blog-card">
                <?php if (!empty($post['image'])): ?>
                    <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
                <?php endif; ?>
                <small><?= htmlspecialchars($post['date']) ?></small>
                <h3><?= htmlspecialchars($post['title']) ?></h3>
                <p><?= htmlspecialchars($post['excerpt']) ?></p>
                <a href="#">Read more</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php
use App\Helpers;

$categoryData = isset($categoryPage['category']) && is_array($categoryPage['category']) ? $categoryPage['category'] : array();
$categoryName = isset($categoryData['name']) && $categoryData['name'] !== '' ? (string)$categoryData['name'] : 'Kategoriler';
$categoryDescription = isset($categoryData['description']) ? trim((string)$categoryData['description']) : '';
$categoryAccent = isset($categoryData['accent']) && $categoryData['accent'] !== '' ? (string)$categoryData['accent'] : '#6366f1';
$categoryImage = isset($categoryData['image']) && $categoryData['image'] !== '' ? (string)$categoryData['image'] : '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp';
$categoryUrl = isset($categoryData['url']) && $categoryData['url'] !== '' ? (string)$categoryData['url'] : Helpers::categoryUrl(isset($categoryData['path']) ? $categoryData['path'] : '');

$breadcrumbs = isset($categoryPage['breadcrumbs']) && is_array($categoryPage['breadcrumbs']) ? $categoryPage['breadcrumbs'] : array();
$children = isset($categoryPage['children']) && is_array($categoryPage['children']) ? $categoryPage['children'] : array();
$products = isset($categoryPage['products']) && is_array($categoryPage['products']) ? $categoryPage['products'] : array();
$productCount = isset($categoryPage['productCount']) ? (int)$categoryPage['productCount'] : count($products);
$canShop = !empty($isLoggedIn);
$hasCategory = isset($categoryData['id']) && $categoryData['id'] !== null;
?>

<section class="section category-header">
    <div class="section-header" style="border-left: 4px solid <?= htmlspecialchars($categoryAccent) ?>;">
        <div>
            <h1><?= htmlspecialchars($categoryName) ?></h1>
            <?php if ($categoryDescription !== ''): ?>
                <p class="text-muted"><?= htmlspecialchars($categoryDescription) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($hasCategory && $productCount > 0): ?>
            <span class="badge badge-pill"><?= htmlspecialchars($productCount) ?> ürün</span>
        <?php endif; ?>
    </div>
    <?php if ($breadcrumbs): ?>
        <nav class="breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php
                    $label = isset($crumb['label']) ? (string)$crumb['label'] : '';
                    $url = isset($crumb['url']) ? (string)$crumb['url'] : '';
                ?>
                <?php if ($label === '') { continue; } ?>
                <?php if ($url !== '' && ($index + 1) < count($breadcrumbs)): ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a>
                <?php else: ?>
                    <span><?= htmlspecialchars($label) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</section>

<?php if ($children): ?>
    <section class="section category-subcategories">
        <div class="section-header">
            <div>
                <h2>Alt Kategoriler</h2>
                <small>Aradığınız ürüne hızlıca ulaşın</small>
            </div>
        </div>
        <div class="catalog__grid category-subcategories__grid">
            <?php foreach ($children as $child): ?>
                <?php
                    $childName = isset($child['name']) ? (string)$child['name'] : 'Kategori';
                    $childUrl = isset($child['url']) && $child['url'] !== '' ? (string)$child['url'] : Helpers::categoryUrl(isset($child['path']) ? $child['path'] : (isset($child['slug']) ? $child['slug'] : ''));
                    $childCount = isset($child['productCount']) ? (int)$child['productCount'] : null;
                ?>
                <article class="product-card product-card--category">
                    <div class="product-card__body">
                        <div class="product-card__meta">
                            <h3><a href="<?= htmlspecialchars($childUrl) ?>"><?= htmlspecialchars($childName) ?></a></h3>
                            <?php if ($childCount !== null): ?>
                                <p class="product-card__summary"><?= htmlspecialchars($childCount) ?> ürün</p>
                            <?php endif; ?>
                        </div>
                        <div class="product-card__footer">
                            <a class="product-card__button product-card__button--ghost" href="<?= htmlspecialchars($childUrl) ?>">İncele</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($products): ?>
    <section class="catalog">
        <header class="section-header">
            <h2><?= htmlspecialchars($categoryName) ?> Ürünleri</h2>
            <p class="text-muted">Satın alabileceğiniz ürünler listeleniyor.</p>
        </header>
        <div class="catalog__grid">
            <?php foreach ($products as $product): ?>
                <?php
                    $inStock = !empty($product['in_stock']);
                    $stockLabel = isset($product['stock_label']) ? (string)$product['stock_label'] : '';
                    $stockText = $stockLabel !== '' ? $stockLabel : ($inStock ? 'Stokta' : 'Stokta Yok');
                    $price = isset($product['price_formatted']) ? (string)$product['price_formatted'] : (isset($product['price']) ? Helpers::formatCurrency((float)$product['price']) : '0.00');
                    $slug = isset($product['slug']) ? (string)$product['slug'] : '';
                    $id = isset($product['id']) ? (int)$product['id'] : 0;
                    $detailSlug = $slug !== '' ? $slug : ($id > 0 ? 'product-' . $id : '');
                    $detailUrl = $detailSlug !== '' ? Helpers::productUrl($detailSlug) : '#';
                    $summary = isset($product['summary']) && $product['summary'] !== '' ? (string)$product['summary'] : (isset($product['description']) ? (string)$product['description'] : '');
                    $categoryNameLabel = isset($product['category_name']) ? (string)$product['category_name'] : '';
                    $categoryLink = isset($product['category_url']) && $product['category_url'] !== '' ? (string)$product['category_url'] : (isset($product['category_path']) ? Helpers::categoryUrl($product['category_path']) : $categoryUrl);
                ?>
                <article class="product-card<?= $inStock ? '' : ' product-card--out' ?>" data-product-card>
                    <div class="product-card__media">
                        <a href="<?= htmlspecialchars($detailUrl) ?>" class="product-card__link" aria-label="<?= htmlspecialchars(($product['name'] ?? 'Ürün') . ' detayını görüntüle') ?>">
                            <img src="<?= htmlspecialchars($product['image'] ?? $categoryImage) ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Ürün') ?>">
                        </a>
                    </div>
                    <div class="product-card__body">
                        <div class="product-card__meta">
                            <h3>
                                <a href="<?= htmlspecialchars($detailUrl) ?>"><?= htmlspecialchars($product['name'] ?? 'Ürün') ?></a>
                            </h3>
                            <?php if ($categoryNameLabel !== ''): ?>
                                <p class="product-card__category">
                                    <a href="<?= htmlspecialchars($categoryLink) ?>"><?= htmlspecialchars($categoryNameLabel) ?></a>
                                </p>
                            <?php endif; ?>
                            <?php if ($summary !== ''): ?>
                                <p class="product-card__summary"><?= htmlspecialchars($summary) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="product-card__info">
                            <span class="product-card__stock<?= $inStock ? ' product-card__stock--in' : ' product-card__stock--out' ?>">
                                <span class="material-icons" aria-hidden="true"><?= $inStock ? 'check_circle' : 'cancel' ?></span>
                                <?= htmlspecialchars($stockText) ?>
                            </span>
                        </div>
                        <div class="product-card__footer">
                            <span class="product-card__price"><?= htmlspecialchars($price) ?></span>
                            <?php if ($canShop): ?>
                                <button
                                    type="button"
                                    class="product-card__button<?= $inStock ? '' : ' is-disabled' ?>"
                                    data-add-to-cart
                                    data-product-id="<?= (int)($product['id'] ?? 0) ?>"
                                    data-product-name="<?= htmlspecialchars($product['name'] ?? 'Ürün') ?>"
                                    <?= $inStock ? '' : 'disabled' ?>
                                >
                                    Sepete Ekle
                                </button>
                            <?php else: ?>
                                <a class="product-card__button product-card__button--ghost" href="/login.php">Giriş Yap</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="section">
        <div class="empty-state">
            <h3>Bu kategoride ürün bulunamadı.</h3>
            <p>Yakında yeni ürünler eklenebilir, lütfen daha sonra tekrar ziyaret edin.</p>
            <a class="btn btn-primary" href="<?= htmlspecialchars(Helpers::categoryUrl('')) ?>">Tüm kategorileri görüntüle</a>
        </div>
    </section>
<?php endif; ?>

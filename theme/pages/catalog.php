<section class="catalog">
    <header class="section-header">
        <h1>Catalog</h1>
        <p class="text-muted">Browse digital goods, subscriptions and game credits.</p>
    </header>
    <div class="catalog__grid">
        <?php $canShop = !empty($isLoggedIn); ?>
        <?php foreach ($products ?? [] as $product): ?>
            <?php
                $inStock = !empty($product['in_stock']);
                $stockLabel = isset($product['stock_label']) ? (string)$product['stock_label'] : '';
                $stockText = $stockLabel !== '' ? $stockLabel : ($inStock ? 'In stock' : 'Out of stock');
                $price = isset($product['price_formatted']) ? (string)$product['price_formatted'] : (isset($product['price']) ? \App\Helpers::formatCurrency((float)$product['price']) : '$0.00');
                $slug = isset($product['slug']) ? (string)$product['slug'] : '';
                $id = isset($product['id']) ? (int)$product['id'] : 0;
                $detailSlug = $slug !== '' ? $slug : ($id > 0 ? 'product-' . $id : '');
                $detailUrl = $detailSlug !== '' ? \App\Helpers::productUrl($detailSlug) : '#';
                $summary = isset($product['summary']) && $product['summary'] !== '' ? (string)$product['summary'] : (isset($product['description']) ? (string)$product['description'] : '');
                $categoryName = isset($product['category_name']) ? (string)$product['category_name'] : '';
            ?>
            <article class="product-card product-card--wide<?= $inStock ? '' : ' product-card--out' ?>" data-product-card>
                <div class="product-card__media">
                    <a href="<?= htmlspecialchars($detailUrl) ?>" class="product-card__link" aria-label="<?= htmlspecialchars('View ' . ($product['name'] ?? 'Product')) ?>">
                        <img src="<?= htmlspecialchars($product['image'] ?? '/theme/assets/images/placeholder.png') ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Product') ?>">
                    </a>
                </div>
                <div class="product-card__body">
                    <div class="product-card__meta">
                        <h3>
                            <a href="<?= htmlspecialchars($detailUrl) ?>"><?= htmlspecialchars($product['name'] ?? 'Product') ?></a>
                        </h3>
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
                                data-product-name="<?= htmlspecialchars($product['name'] ?? 'Product') ?>"
                                <?= $inStock ? '' : 'disabled' ?>
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

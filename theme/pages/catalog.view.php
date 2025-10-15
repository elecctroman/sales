<div class="catalog">
    <h2>Featured Products</h2>
    <div class="catalog__grid">
        <?php foreach ($products ?? [] as $product): ?>
            <?php
                $inStock = !empty($product['in_stock']);
                $stockLabel = isset($product['stock_label']) ? $product['stock_label'] : ($inStock ? 'In stock' : 'Out of stock');
                $price = isset($product['price_formatted']) ? $product['price_formatted'] : '$0.00';
                $slug = isset($product['slug']) ? (string)$product['slug'] : '';
                $id = isset($product['id']) ? (int)$product['id'] : 0;
                $detailSlug = $slug !== '' ? $slug : ($id > 0 ? 'product-' . $id : '');
                $detailUrl = $detailSlug !== '' ? \App\Helpers::productUrl($detailSlug) : '#';
                $summary = isset($product['summary']) && $product['summary'] !== '' ? (string)$product['summary'] : (isset($product['description']) ? (string)$product['description'] : '');
                $categoryName = isset($product['category_name']) ? (string)$product['category_name'] : '';
            ?>
            <article class="product-card<?= $inStock ? '' : ' product-card--out' ?>" data-product-card>
                <div class="product-card__media">
                    <a href="<?= htmlspecialchars($detailUrl) ?>" class="product-card__link" aria-label="<?= htmlspecialchars('View ' . ($product['name'] ?? 'Product')) ?>">
                        <img src="<?= htmlspecialchars($product['image'] ?? '/theme/assets/images/placeholder.png') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    </a>
                </div>
                <div class="product-card__body">
                    <div class="product-card__meta">
                        <h3>
                            <a href="<?= htmlspecialchars($detailUrl) ?>"><?= htmlspecialchars($product['name']) ?></a>
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
                        <button
                            type="button"
                            class="product-card__button<?= $inStock ? '' : ' is-disabled' ?>"
                            data-add-to-cart
                            data-product-id="<?= (int)($product['id'] ?? 0) ?>"
                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                            <?= $inStock ? '' : 'disabled' ?>
                        >
                            Sepete Ekle
                        </button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

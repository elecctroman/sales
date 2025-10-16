<?php
use App\Helpers;

$cartData = isset($cart) ? $cart : array();
$cartItems = isset($cartData['items']) && is_array($cartData['items']) ? $cartData['items'] : array();
$cartTotals = isset($cartData['totals']) && is_array($cartData['totals']) ? $cartData['totals'] : array();
$hasItems = !empty($cartItems);
$subtotalFormatted = isset($cartTotals['subtotal_formatted']) ? $cartTotals['subtotal_formatted'] : Helpers::formatCurrency(0, 'TRY');
$itemCount = isset($cartTotals['total_items']) ? (int)$cartTotals['total_items'] : 0;
$quantityCount = isset($cartTotals['total_quantity']) ? (int)$cartTotals['total_quantity'] : 0;
$userData = isset($user) && is_array($user) ? $user : array();

$fullName = isset($userData['name']) ? trim((string)$userData['name']) : '';
$nameParts = $fullName !== '' ? preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY) : array();
$firstName = $nameParts ? array_shift($nameParts) : '';
$lastName = $nameParts ? implode(' ', $nameParts) : '';
$email = isset($userData['email']) ? (string)$userData['email'] : '';
$balanceValue = isset($userData['balance']) ? (float)$userData['balance'] : 0.0;
$balanceFormatted = Helpers::formatCurrency($balanceValue, 'TRY');
$discountValue = isset($cartTotals['discount_value']) ? (float)$cartTotals['discount_value'] : 0.0;
$discountFormatted = isset($cartTotals['discount_formatted']) ? $cartTotals['discount_formatted'] : Helpers::formatCurrency(0, Helpers::activeCurrency());
$grandTotalFormatted = isset($cartTotals['grand_total_formatted']) ? $cartTotals['grand_total_formatted'] : $subtotalFormatted;
$appliedCoupon = isset($cartTotals['coupon']) && is_array($cartTotals['coupon']) ? $cartTotals['coupon'] : null;
$couponNotice = Helpers::getFlash('cart_notice', '');
$isLoggedInCart = isset($isLoggedIn) ? (bool)$isLoggedIn : false;

$paymentMethods = array(
    'card' => 'Kredi / Banka Karti',
    'balance' => 'Bakiye ile Ode (' . $balanceFormatted . ')',
    'eft' => 'Banka / EFT',
    'crypto' => 'Kripto Odeme',
);
?>

<section class="cart" data-cart-page>
    <header class="cart__header">
        <h1>Sepetim</h1>
        <p>Sepetinizdeki urunleri goruntuleyin, miktarlari duzenleyin ve odeme adimina gecin.</p>
    </header>

    <div class="cart__grid">
        <div class="cart__panel cart__panel--items">
            <div class="cart__panel-header">
                <h2>Sepetteki Urunler</h2>
                <?php if ($hasItems): ?>
                    <button class="cart__clear" type="button" data-cart-clear>Sepeti Temizle</button>
                <?php endif; ?>
            </div>

            <?php if ($hasItems): ?>
                <div class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                            $productId = isset($item['id']) ? (int)$item['id'] : 0;
                            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                            $lineTotal = isset($item['line_total_formatted']) ? $item['line_total_formatted'] : Helpers::formatCurrency(0, 'TRY');
                            $unitPrice = isset($item['price_formatted']) ? $item['price_formatted'] : Helpers::formatCurrency(0, 'TRY');
                            $productName = isset($item['name']) ? $item['name'] : 'Urun';
                            $productImage = isset($item['image']) && $item['image'] !== '' ? $item['image'] : '/theme/assets/images/placeholder.png';
                        ?>
                        <article class="cart-item" data-cart-item data-product-id="<?= $productId ?>" data-quantity="<?= $quantity ?>">
                            <div class="cart-item__media">
                                <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($productName) ?>">
                            </div>
                            <div class="cart-item__details">
                                <div class="cart-item__top">
                                    <h3><?= htmlspecialchars($productName) ?></h3>
                                    <button class="cart-item__remove" type="button" data-cart-remove data-product-id="<?= $productId ?>" aria-label="Urunu kaldir">
                                        <span class="material-icons" aria-hidden="true">delete_outline</span>
                                    </button>
                                </div>
                                <div class="cart-item__meta">
                                    <span class="cart-item__unit"><?= htmlspecialchars($unitPrice) ?></span>
                                    <div class="cart-item__quantity">
                                        <button class="cart-item__qty-btn" type="button" data-cart-step data-product-id="<?= $productId ?>" data-delta="-1" aria-label="Adeti azalt">
                                            <span class="material-icons" aria-hidden="true">remove</span>
                                        </button>
                                        <span class="cart-item__qty" data-cart-quantity><?= $quantity ?></span>
                                        <button class="cart-item__qty-btn" type="button" data-cart-step data-product-id="<?= $productId ?>" data-delta="1" aria-label="Adeti artir">
                                            <span class="material-icons" aria-hidden="true">add</span>
                                        </button>
                                    </div>
                                    <span class="cart-item__total" data-cart-line-total><?= htmlspecialchars($lineTotal) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="cart-empty">
                    <p>Sepetinizde ürün bulunmuyor.</p>
                    <a class="cart-empty__link" href="/kategori/">Alışverişe Devam Et</a>
                </div>
            <?php endif; ?>
        </div>

        <aside class="cart__panel cart__panel--summary">
            <div class="cart__panel-header">
                <h2>Sepet Ozeti</h2>
            </div>
            <?php if ($couponNotice !== ''): ?>
                <div class="cart-alert">
                    <?= htmlspecialchars($couponNotice) ?>
                </div>
            <?php endif; ?>
            <dl class="cart-summary">
                <div>
                    <dt>Urun Sayisi</dt>
                    <dd data-cart-total-items><?= $itemCount ?></dd>
                </div>
                <div>
                    <dt>Toplam Adet</dt>
                    <dd data-cart-total-quantity><?= $quantityCount ?></dd>
                </div>
                <div class="cart-summary__subtotal">
                    <dt>Ara Toplam</dt>
                    <dd data-cart-subtotal><?= htmlspecialchars($subtotalFormatted) ?></dd>
                </div>
                <?php if ($appliedCoupon): ?>
                    <div class="cart-summary__discount">
                        <dt>Kupon (<?= htmlspecialchars($appliedCoupon['code']) ?>)</dt>
                        <dd>-<?= htmlspecialchars($discountFormatted) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="cart-summary__total">
                    <dt>Odenecek Tutar</dt>
                    <dd data-cart-grand-total><?= htmlspecialchars($grandTotalFormatted) ?></dd>
                </div>
            </dl>

            <div class="cart-coupon">
                <?php if ($appliedCoupon): ?>
                    <div class="cart-coupon__applied">
                        <div class="cart-coupon__details">
                            <span class="cart-coupon__code"><?= htmlspecialchars($appliedCoupon['code']) ?></span>
                            <?php if (!empty($appliedCoupon['label'])): ?>
                                <span class="cart-coupon__label"><?= htmlspecialchars($appliedCoupon['label']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="cart-coupon__remove" data-cart-coupon-remove>Kuponu Kaldir</button>
                    </div>
                <?php else: ?>
                    <form class="cart-coupon__form" data-cart-coupon-form>
                        <label class="cart-coupon__input">
                            <span class="visually-hidden">Kupon kodu</span>
                            <input type="text" name="coupon_code" placeholder="Kupon kodu" required<?= $isLoggedInCart ? '' : ' disabled' ?>>
                        </label>
                        <button type="submit" class="cart-coupon__apply"<?= $isLoggedInCart ? '' : ' disabled' ?>>Kuponu Uygula</button>
                    </form>
                    <?php if (!$isLoggedInCart): ?>
                        <p class="cart-coupon__hint">Kupon kullanmak icin giris yapmalisiniz.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="cart-summary__actions">
                <button type="button" class="cart-summary__button cart-summary__button--primary" data-checkout-trigger data-method="card"<?= $hasItems ? '' : ' disabled' ?>>
                    Odemeye Devam Et
                </button>
            </div>

            <div class="cart-summary__footer">
                <a href="/kategori/" class="cart-summary__link">Alışverişe Devam Et</a>
            </div>
        </aside>
    </div>
</section>

<div class="cart-modal" data-checkout-modal hidden>
    <div class="cart-modal__overlay" data-checkout-dismiss></div>
    <div class="cart-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="checkoutModalTitle">
        <button type="button" class="cart-modal__close" data-checkout-dismiss aria-label="Pencereyi kapat">
            <span class="material-icons" aria-hidden="true">close</span>
        </button>
        <h2 id="checkoutModalTitle">Odeme Bilgileri</h2>
        <p class="cart-modal__hint">Odeme yonteminizi secin ve talep edilen bilgileri tamamlayin.</p>
        <form class="cart-modal__form" data-checkout-form>
            <input type="hidden" name="payment_method" value="card" data-checkout-method-field>
            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
            <div class="cart-modal__grid">
                <label>
                    <span>Ad</span>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($firstName) ?>" required>
                </label>
                <label>
                    <span>Soyad</span>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($lastName) ?>" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </label>
                <label>
                    <span>Telefon</span>
                    <input type="tel" name="phone" value="">
                </label>
                <label class="cart-modal__full">
                    <span>Odeme Yontemi</span>
                    <select name="payment_option" required>
                        <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
                            <option value="<?= htmlspecialchars($methodKey) ?>"><?= htmlspecialchars($methodLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button type="submit" class="cart-modal__submit">Odemeye Gec</button>
        </form>
    </div>
</div>

<?php
use App\Helpers;

$context = isset($payment) && is_array($payment) ? $payment : array();
$method = isset($context['method']) ? $context['method'] : 'card';
$reference = isset($context['reference']) ? $context['reference'] : '';
$orders = isset($context['orders']) && is_array($context['orders']) ? $context['orders'] : array();
$totalFormatted = isset($context['total_formatted']) ? $context['total_formatted'] : Helpers::formatCurrency(0, Helpers::activeCurrency());
$notification = isset($context['notification']) && is_array($context['notification']) ? $context['notification'] : array();
$notifyErrors = isset($notification['errors']) && is_array($notification['errors']) ? $notification['errors'] : array();
$notifySuccess = isset($notification['success']) ? $notification['success'] : '';
$bankAccounts = isset($context['bankAccounts']) && is_array($context['bankAccounts']) ? $context['bankAccounts'] : array();
$remainingBalance = isset($context['remaining_balance']) ? (float)$context['remaining_balance'] : null;
$couponCode = isset($context['coupon_code']) ? (string)$context['coupon_code'] : '';
$couponDiscountFormatted = isset($context['coupon_discount_formatted']) ? (string)$context['coupon_discount_formatted'] : '';

$methodLabels = array(
    'card' => 'Kredi / Banka Karti',
    'balance' => 'Bakiye ile Odeme',
    'eft' => 'Banka Havale / EFT',
    'crypto' => 'Kripto Odeme',
);
$methodLabel = isset($methodLabels[$method]) ? $methodLabels[$method] : 'Odeme';
?>

<section class="payment-success" data-payment-success>
    <header class="payment-success__header">
        <h1>Odeme Durumu</h1>
        <p>Siparisiniz alindi. Asagidaki adimlari takip ederek isleminizi tamamlayabilirsiniz.</p>
    </header>

    <div class="payment-success__summary">
        <div>
            <span class="payment-success__summary-label">Odeme Yontemi</span>
            <strong><?= htmlspecialchars($methodLabel) ?></strong>
        </div>
        <div>
            <span class="payment-success__summary-label">Toplam Tutar</span>
            <strong><?= htmlspecialchars($totalFormatted) ?></strong>
        </div>
        <div>
            <span class="payment-success__summary-label">Siparis Referansi</span>
            <strong><?= $reference !== '' ? htmlspecialchars($reference) : '-' ?></strong>
        </div>
        <?php if ($couponCode !== ''): ?>
            <div>
                <span class="payment-success__summary-label">Kupon</span>
                <strong>
                    <?= htmlspecialchars($couponCode) ?>
                    <?php if ($couponDiscountFormatted !== ''): ?>
                        <span class="payment-success__coupon-amount">-<?= htmlspecialchars($couponDiscountFormatted) ?></span>
                    <?php endif; ?>
                </strong>
            </div>
        <?php endif; ?>
        <?php if ($remainingBalance !== null): ?>
            <div>
                <span class="payment-success__summary-label">Guncel Bakiyeniz</span>
                <strong><?= Helpers::formatCurrency($remainingBalance, Helpers::activeCurrency()) ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($orders): ?>
        <div class="payment-success__orders">
            <h2>Siparis Ozeti</h2>
            <ul>
                <?php foreach ($orders as $order): ?>
                    <li>
                        <div>
                            <strong><?= htmlspecialchars($order['product_name']) ?></strong>
                            <?php if (!empty($order['product_sku'])): ?>
                                <span class="payment-success__sku">SKU: <?= htmlspecialchars($order['product_sku']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="payment-success__order-meta">
                            <span>Adet: <?= (int)$order['quantity'] ?></span>
                            <span>Tutar: <?= htmlspecialchars($order['price_formatted']) ?></span>
                            <span>Durum: <?= htmlspecialchars(ucfirst($order['status'])) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="payment-success__next">
        <?php if ($method === 'balance'): ?>
            <div class="payment-success__notice payment-success__notice--success">
                <span class="material-icons" aria-hidden="true">check_circle</span>
                <div>
                    <h3>Bakiye odemeniz tamamlandi</h3>
                    <p>Siparisiniz isleme alinmistir. Teslimat suresi urune gore degisiklik gosterebilir. Destek ihtiyaciniz olursa lutfen bizimle iletisime gecebilirsiniz.</p>
                </div>
            </div>
        <?php elseif ($method === 'card'): ?>
            <div class="payment-success__notice">
                <span class="material-icons" aria-hidden="true">credit_card</span>
                <div>
                    <h3>Kart odemeniz icin yonlendiriliyoruz</h3>
                    <p>Odeme saglayicisi ekraninda islemi tamamlayiniz. Odeme tamamlandiktan sonra bu sayfaya geri yonlendirileceksiniz.</p>
                </div>
            </div>
        <?php elseif ($method === 'crypto'): ?>
            <div class="payment-success__notice">
                <span class="material-icons" aria-hidden="true">currency_bitcoin</span>
                <div>
                    <h3>Kripto odeme talimati</h3>
                    <p>Kripto odeme saglayicisina yonlendirildiniz. Transferi tamamladiktan sonra isleminiz otomatik olarak guncellenecektir.</p>
                </div>
            </div>
        <?php elseif ($method === 'eft'): ?>
            <div class="payment-success__notice">
                <span class="material-icons" aria-hidden="true">account_balance</span>
                <div>
                    <h3>Banka transferi gerekli</h3>
                    <p>Asagidaki banka bilgilerimizden birine transfer yaparak odemenizi tamamlayin. Islemi tamamladiktan sonra asagidaki form ile bize bildirim gondermeyi unutmayin.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($method === 'eft'): ?>
        <section class="payment-success__banks">
            <h2>Banka Bilgileri</h2>
            <?php if ($bankAccounts): ?>
                <div class="payment-success__bank-grid">
                    <?php foreach ($bankAccounts as $bank): ?>
                        <article class="payment-bank-card">
                            <h3><?= htmlspecialchars($bank['bank_name']) ?></h3>
                            <dl>
                                <div>
                                    <dt>Hesap Sahibi</dt>
                                    <dd><?= htmlspecialchars($bank['account_holder']) ?></dd>
                                </div>
                                <div>
                                    <dt>IBAN</dt>
                                    <dd><code><?= htmlspecialchars($bank['iban']) ?></code></dd>
                                </div>
                                <?php if (!empty($bank['branch'])): ?>
                                    <div>
                                        <dt>Sube</dt>
                                        <dd><?= htmlspecialchars($bank['branch']) ?></dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($bank['description'])): ?>
                                    <div>
                                        <dt>Aciklama</dt>
                                        <dd><?= htmlspecialchars($bank['description']) ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Aktif banka bilgisi bulunmuyor. Lutfen destek ekibiyle iletisime geciniz.</p>
            <?php endif; ?>
        </section>

        <section class="payment-success__notify" id="payment-notify">
            <h2>Odeme Bildir</h2>

            <?php if ($notifySuccess !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($notifySuccess) ?></div>
            <?php endif; ?>

            <?php if ($notifyErrors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($notifyErrors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="payment-notify-form">
                <input type="hidden" name="action" value="bank_transfer_notify">
                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">

                <label>
                    <span>Banka secin</span>
                    <select name="bank_account_id" required>
                        <option value="">Banka seciniz</option>
                        <?php foreach ($bankAccounts as $bank): ?>
                            <option value="<?= (int)$bank['id'] ?>"><?= htmlspecialchars($bank['bank_name']) ?> - <?= htmlspecialchars($bank['account_holder']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Gonderilen tutar</span>
                    <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
                </label>

                <label>
                    <span>Transfer tarihi ve saati</span>
                    <input type="datetime-local" name="transfer_datetime" required>
                </label>

                <label>
                    <span>Not (opsiyonel)</span>
                    <textarea name="note" rows="3" placeholder="Ek bilgi veya dekont numarasi"></textarea>
                </label>

                <label>
                    <span>Dekont (opsiyonel)</span>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
                </label>

                <button type="submit" class="btn btn-primary">Odeme Bildir</button>
            </form>
        </section>
    <?php endif; ?>

    <footer class="payment-success__footer">
        <a class="btn btn-secondary" href="/kategori/">Alışverişe Devam Et</a>
        <a class="btn btn-ghost" href="/account">Siparişlerimi Görüntüle</a>
    </footer>
</section>

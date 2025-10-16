<?php
use App\Helpers;

$activeTab = isset($accountFeedback['activeTab']) ? $accountFeedback['activeTab'] : 'profile';
$tabMessages = isset($accountFeedback['messages']) && is_array($accountFeedback['messages']) ? $accountFeedback['messages'] : array();
$csrfToken = Helpers::csrfToken();
$accountUser = isset($account['user']) ? $account['user'] : array();
$orders = isset($account['orders']) ? $account['orders'] : array();
$transactions = isset($account['transactions']) ? $account['transactions'] : array();
$balanceRequests = isset($account['balanceRequests']) ? $account['balanceRequests'] : array();
$tickets = isset($account['tickets']) ? $account['tickets'] : array();
$ticketMessages = isset($account['ticketMessages']) ? $account['ticketMessages'] : array();
$sessions = isset($account['sessions']) ? $account['sessions'] : array();
$balanceTotal = isset($account['balance']) ? (float)$account['balance'] : 0.0;
$apiData = isset($account['api']) && is_array($account['api']) ? $account['api'] : array();
$apiBaseUrl = isset($apiData['base_url']) ? (string)$apiData['base_url'] : Helpers::absoluteUrl('/api/v1/');
if ($apiBaseUrl !== '' && substr($apiBaseUrl, -1) !== '/') {
    $apiBaseUrl .= '/';
}
$apiDocsUrl = isset($apiData['docs_url']) && $apiData['docs_url'] !== '' ? Helpers::absoluteUrl((string)$apiData['docs_url']) : Helpers::absoluteUrl(Helpers::pageUrl('api-dokumantasyon'));
$apiTokenValue = isset($apiData['token']) ? (string)$apiData['token'] : '';
$apiHasToken = !empty($apiData['has_token']) || $apiTokenValue !== '';
$apiLabel = isset($apiData['label']) ? (string)$apiData['label'] : '';
$apiWebhook = isset($apiData['webhook_url']) ? (string)$apiData['webhook_url'] : '';
$apiCreatedAt = isset($apiData['created_at']) ? $apiData['created_at'] : null;
$apiLastUsed = isset($apiData['last_used_at']) ? $apiData['last_used_at'] : null;
$apiTokenPreview = $apiHasToken ? substr($apiTokenValue, 0, 4) . str_repeat('•', max(0, strlen($apiTokenValue) - 4)) : '';

$renderAlerts = function ($bag) {
    $bag = is_array($bag) ? $bag : array();
    $success = isset($bag['success']) ? trim((string)$bag['success']) : '';
    $errors = isset($bag['errors']) && is_array($bag['errors']) ? $bag['errors'] : array();

    if ($success !== '') {
        echo '<div class="account-alert account-alert--success">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    if ($errors) {
        echo '<div class="account-alert account-alert--error"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div>';
    }
};

$orderStatusLabels = array(
    'pending' => 'Beklemede',
    'processing' => 'Isleniyor',
    'completed' => 'Tamamlandi',
    'cancelled' => 'Iptal edildi',
    'paid' => 'Odendi',
);

$ticketStatusLabels = array(
    'open' => 'Acik',
    'answered' => 'Yanitlandi',
    'closed' => 'Kapali',
);

$ticketPriorityLabels = array(
    'low' => 'Dusuk',
    'normal' => 'Normal',
    'high' => 'Yuksek',
);
?>

<section class="account" data-account-wrapper data-account-active="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>">
    <header class="account__header">
        <h1>Hesabim</h1>
        <p>Bilgilerinizi, siparislerinizi ve destek taleplerinizi tek ekrandan yonetin.</p>
    </header>

    <nav class="account-tabs" data-account-tabs role="tablist" aria-label="Hesap sekmeleri">
        <?php foreach ($accountTabs as $tabKey): ?>
            <?php
                $isActive = $tabKey === $activeTab;
                $labels = array(
                    'profile' => 'Profil',
                    'password' => 'Parola',
                    'orders' => 'Siparislerim',
                    'balance' => 'Bakiyem',
                    'support' => 'Destek Taleplerim',
                    'sessions' => 'Son Oturumlar',
                    'api' => 'API Entegrasyon',
                );
                $label = isset($labels[$tabKey]) ? $labels[$tabKey] : ucfirst($tabKey);
            ?>
            <a class="account-tabs__item<?= $isActive ? ' is-active' : '' ?>"
               data-account-tab="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
               href="/account?tab=<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>"
               role="tab"
               aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="account-panels">
        <?php
            $profileMessages = isset($tabMessages['profile']) ? $tabMessages['profile'] : array();
            $passwordMessages = isset($tabMessages['password']) ? $tabMessages['password'] : array();
            $orderMessages = isset($tabMessages['orders']) ? $tabMessages['orders'] : array();
            $balanceMessages = isset($tabMessages['balance']) ? $tabMessages['balance'] : array();
            $supportMessages = isset($tabMessages['support']) ? $tabMessages['support'] : array();
            $sessionMessages = isset($tabMessages['sessions']) ? $tabMessages['sessions'] : array();
            $apiMessages = isset($tabMessages['api']) ? $tabMessages['api'] : array();
        ?>

        <section class="account-panel<?= $activeTab === 'profile' ? ' is-active' : '' ?>" data-account-panel="profile" role="tabpanel">
            <?php $renderAlerts($profileMessages); ?>
            <div class="account-card">
                <h2>Profil Bilgileri</h2>
                <p class="account-card__hint">Kimlik ve iletisim bilgilerinizi burada guncelleyebilirsiniz.</p>
                <form method="post" class="account-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="tab" value="profile">
                    <div class="account-form__grid">
                        <label>
                            <span>Ad Soyad</span>
                            <input type="text" name="name" value="<?= htmlspecialchars(isset($accountUser['name']) ? $accountUser['name'] : '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        <label>
                            <span>E-posta</span>
                            <input type="email" name="email" value="<?= htmlspecialchars(isset($accountUser['email']) ? $accountUser['email'] : '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                    </div>
                    <div class="account-form__actions">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="account-panel<?= $activeTab === 'password' ? ' is-active' : '' ?>" data-account-panel="password" role="tabpanel">
            <?php $renderAlerts($passwordMessages); ?>
            <div class="account-card">
                <h2>Parola Degistir</h2>
                <p class="account-card__hint">Guclu bir parola hesap guvenliginizi artirir.</p>
                <form method="post" class="account-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="tab" value="password">
                    <div class="account-form__grid">
                        <label>
                            <span>Mevcut Parola</span>
                            <input type="password" name="current_password" required>
                        </label>
                        <label>
                            <span>Yeni Parola</span>
                            <input type="password" name="new_password" required>
                        </label>
                        <label>
                            <span>Yeni Parola (Tekrar)</span>
                            <input type="password" name="new_password_confirmation" required>
                        </label>
                    </div>
                    <div class="account-form__actions">
                        <button type="submit" class="btn btn-primary">Parolami Guncelle</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="account-panel<?= $activeTab === 'orders' ? ' is-active' : '' ?>" data-account-panel="orders" role="tabpanel">
            <?php $renderAlerts($orderMessages); ?>
            <div class="account-card">
                <h2>Siparislerim</h2>
                <p class="account-card__hint">Gecmiste verdiginiz siparisleri ve durumlarini buradan takip edin.</p>
                <?php if ($orders): ?>
                    <div class="account-table__wrapper">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Kod</th>
                                    <th>Urun</th>
                                    <th>Adet</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                        $orderId = isset($order['id']) ? (int)$order['id'] : 0;
                                        $statusKey = isset($order['status']) ? (string)$order['status'] : 'pending';
                                        $statusLabel = isset($orderStatusLabels[$statusKey]) ? $orderStatusLabels[$statusKey] : ucfirst($statusKey);
                                        $priceValue = isset($order['price']) ? (float)$order['price'] : 0.0;
                                        $quantityValue = isset($order['quantity']) ? (int)$order['quantity'] : 1;
                                        $createdAt = isset($order['created_at']) ? $order['created_at'] : '';
                                        $updatedAt = isset($order['updated_at']) ? $order['updated_at'] : '';
                                        $note = isset($order['note']) ? trim((string)$order['note']) : '';
                                        $adminNote = isset($order['admin_note']) ? trim((string)$order['admin_note']) : '';
                                        $source = isset($order['source']) ? trim((string)$order['source']) : '';
                                        $externalReference = isset($order['external_reference']) ? trim((string)$order['external_reference']) : '';
                                        $metadataRaw = isset($order['external_metadata']) ? (string)$order['external_metadata'] : '';
                                        $metadataItems = array();

                                        if ($metadataRaw !== '') {
                                            $decodedMetadata = json_decode($metadataRaw, true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMetadata)) {
                                                foreach ($decodedMetadata as $metaKey => $metaValue) {
                                                    $format = 'text';
                                                    if (is_array($metaValue) || is_object($metaValue)) {
                                                        $metaValue = json_encode($metaValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                                                        $format = 'code';
                                                    } elseif ($metaValue === null || $metaValue === '') {
                                                        $metaValue = '-';
                                                    }
                                                    $metadataItems[] = array(
                                                        'label' => (string)$metaKey,
                                                        'value' => (string)$metaValue,
                                                        'format' => $format,
                                                    );
                                                }
                                            } else {
                                                $metadataItems[] = array(
                                                    'label' => 'Veri',
                                                    'value' => $metadataRaw,
                                                    'format' => 'text',
                                                );
                                            }
                                        }

                                        $hasDetailContent = ($note !== '') || ($adminNote !== '') || ($source !== '') || ($externalReference !== '') || !empty($metadataItems);
                                        $detailsRowId = 'order-details-' . $orderId;
                                    ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars((string)$orderId, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(isset($order['product_name']) ? $order['product_name'] : (isset($order['product_id']) ? 'Urun #' . $order['product_id'] : 'Urun'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)$quantityValue, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(number_format($priceValue, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> TL</td>
                                        <td><span class="account-badge"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td>
                                            <div class="account-order__date">
                                                <time><?= htmlspecialchars($createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '-', ENT_QUOTES, 'UTF-8') ?></time>
                                                <button type="button"
                                                        class="account-order__toggle"
                                                        data-order-toggle="<?= htmlspecialchars($detailsRowId, ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-expanded="false"
                                                        aria-controls="<?= htmlspecialchars($detailsRowId, ENT_QUOTES, 'UTF-8') ?>">
                                                    Goruntule
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="account-order-details" id="<?= htmlspecialchars($detailsRowId, ENT_QUOTES, 'UTF-8') ?>" data-order-details>
                                        <td colspan="6">
                                            <?php if ($hasDetailContent || $metadataItems): ?>
                                                <div class="account-order-details__body">
                                                    <?php if ($note !== ''): ?>
                                                        <div class="account-order-details__item">
                                                            <span class="account-order-details__label">Sipariş Notu</span>
                                                            <div class="account-order-details__value"><?= nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8')) ?></div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($adminNote !== ''): ?>
                                                        <div class="account-order-details__item">
                                                            <span class="account-order-details__label">Yönetici Notu</span>
                                                            <div class="account-order-details__value"><?= nl2br(htmlspecialchars($adminNote, ENT_QUOTES, 'UTF-8')) ?></div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($source !== ''): ?>
                                                        <div class="account-order-details__item">
                                                            <span class="account-order-details__label">Kaynak</span>
                                                            <div class="account-order-details__value"><?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?></div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($externalReference !== ''): ?>
                                                        <div class="account-order-details__item">
                                                            <span class="account-order-details__label">Dış Referans</span>
                                                            <div class="account-order-details__value"><?= htmlspecialchars($externalReference, ENT_QUOTES, 'UTF-8') ?></div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="account-order-details__item">
                                                        <span class="account-order-details__label">Oluşturulma</span>
                                                        <div class="account-order-details__value"><?= htmlspecialchars($createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                    </div>

                                                    <div class="account-order-details__item">
                                                        <span class="account-order-details__label">Güncellenme</span>
                                                        <div class="account-order-details__value"><?= htmlspecialchars($updatedAt ? date('d.m.Y H:i', strtotime($updatedAt)) : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                    </div>

                                                    <?php if ($metadataItems): ?>
                                                        <div class="account-order-details__item account-order-details__item--full">
                                                            <span class="account-order-details__label">Ek Bilgiler</span>
                                                            <ul class="account-order-details__list">
                                                                <?php foreach ($metadataItems as $metaItem): ?>
                                                                    <li>
                                                                        <span class="account-order-details__meta-key"><?= htmlspecialchars($metaItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                        <?php
                                                                            $metaValue = isset($metaItem['value']) ? (string)$metaItem['value'] : '';
                                                                            $metaFormat = isset($metaItem['format']) ? $metaItem['format'] : 'text';
                                                                            if ($metaFormat === 'code') {
                                                                                echo '<pre class="account-order-details__meta-value account-order-details__meta-value--code">' . htmlspecialchars($metaValue, ENT_QUOTES, 'UTF-8') . '</pre>';
                                                                            } else {
                                                                                echo '<span class="account-order-details__meta-value">' . htmlspecialchars($metaValue, ENT_QUOTES, 'UTF-8') . '</span>';
                                                                            }
                                                                        ?>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                         </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="account-order-details__empty">Bu sipariş için ek bilgi bulunmuyor.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="account-empty">Henuz tamamlanmis siparisiniz bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="account-panel<?= $activeTab === 'balance' ? ' is-active' : '' ?>" data-account-panel="balance" role="tabpanel">
            <?php $renderAlerts($balanceMessages); ?>
            <div class="account-balance">
                <div class="account-card account-balance__summary">
                    <h2>Bakiyem</h2>
                    <p class="account-balance__value"><?= htmlspecialchars(number_format($balanceTotal, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> TL</p>
                </div>
                <div class="account-balance__layout">
                    <div class="account-card">
                        <h3>Bakiye Yukle</h3>
                        <form method="post" class="account-form account-form--stacked">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="create_balance_request">
                            <input type="hidden" name="tab" value="balance">
                            <label>
                                <span>Tutar (TL)</span>
                                <input type="number" name="amount" min="1" step="0.01" placeholder="100.00" required>
                            </label>
                            <label>
                                <span>Odeme Yontemi</span>
                                <input type="text" name="payment_method" placeholder="Havale, kredi karti vb." required>
                            </label>
                            <label>
                                <span>Not</span>
                                <textarea name="notes" rows="3" placeholder="Opsiyonel aciklama"></textarea>
                            </label>
                            <div class="account-form__actions">
                                <button type="submit" class="btn btn-primary">Talep Olustur</button>
                            </div>
                        </form>
                    </div>
                    <div class="account-card">
                        <h3>Islem Gecmisi</h3>
                        <?php if ($transactions): ?>
                            <ul class="account-timeline">
                                <?php foreach ($transactions as $transaction): ?>
                                    <?php
                                        $sign = isset($transaction['type']) && $transaction['type'] === 'debit' ? '-' : '+';
                                        $amount = isset($transaction['amount']) ? (float)$transaction['amount'] : 0.0;
                                        $label = isset($transaction['description']) ? $transaction['description'] : '';
                                        $createdAt = isset($transaction['created_at']) ? $transaction['created_at'] : '';
                                    ?>
                                    <li>
                                        <div class="account-timeline__line"></div>
                                        <div class="account-timeline__content">
                                            <strong><?= htmlspecialchars($sign . number_format($amount, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> TL</strong>
                                            <span><?= htmlspecialchars($label !== '' ? $label : 'Islem', ENT_QUOTES, 'UTF-8') ?></span>
                                            <time><?= htmlspecialchars($createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '-', ENT_QUOTES, 'UTF-8') ?></time>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="account-empty">Henuz bakiye hareketi bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                    <div class="account-card">
                        <h3>Bekleyen Talepler</h3>
                        <?php if ($balanceRequests): ?>
                            <ul class="account-list">
                                <?php foreach ($balanceRequests as $request): ?>
                                    <?php
                                        $status = isset($request['status']) ? (string)$request['status'] : 'pending';
                                        $statusText = $status === 'approved' ? 'Onaylandi' : ($status === 'rejected' ? 'Reddedildi' : 'Beklemede');
                                        $createdAt = isset($request['created_at']) ? $request['created_at'] : '';
                                    ?>
                                    <li>
                                        <div>
                                            <strong><?= htmlspecialchars(number_format(isset($request['amount']) ? (float)$request['amount'] : 0.0, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> TL</strong>
                                            <span><?= htmlspecialchars(isset($request['payment_method']) ? $request['payment_method'] : '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="account-list__meta">
                                            <span class="account-badge"><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></span>
                                            <time><?= htmlspecialchars($createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '-', ENT_QUOTES, 'UTF-8') ?></time>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="account-empty">Bekleyen bakiye talebiniz bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="account-panel<?= $activeTab === 'support' ? ' is-active' : '' ?>" data-account-panel="support" role="tabpanel">
            <?php $renderAlerts($supportMessages); ?>
            <div class="account-card">
                <h2>Destek Taleplerim</h2>
                <p class="account-card__hint">Sorularinizi iletin, destek ekibi en kisa surede yanitlasin.</p>
                <?php if ($tickets): ?>
                    <div class="account-ticket-list">
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                                $ticketId = isset($ticket['id']) ? (int)$ticket['id'] : 0;
                                $status = isset($ticket['status']) ? (string)$ticket['status'] : 'open';
                                $priority = isset($ticket['priority']) ? (string)$ticket['priority'] : 'normal';
                                $statusLabel = isset($ticketStatusLabels[$status]) ? $ticketStatusLabels[$status] : ucfirst($status);
                                $priorityLabel = isset($ticketPriorityLabels[$priority]) ? $ticketPriorityLabels[$priority] : ucfirst($priority);
                                $createdAt = isset($ticket['created_at']) ? $ticket['created_at'] : '';
                                $messagesForTicket = isset($ticketMessages[$ticketId]) ? $ticketMessages[$ticketId] : array();
                            ?>
                            <article class="account-ticket">
                                <header class="account-ticket__header">
                                    <div>
                                        <h3><?= htmlspecialchars(isset($ticket['subject']) ? $ticket['subject'] : 'Destek Talebi #' . $ticketId, ENT_QUOTES, 'UTF-8') ?></h3>
                                        <div class="account-ticket__meta">
                                            <span class="account-badge account-badge--muted"><?= htmlspecialchars($priorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="account-badge"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <time><?= htmlspecialchars($createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '-', ENT_QUOTES, 'UTF-8') ?></time>
                                        </div>
                                    </div>
                                </header>
                                <?php if ($messagesForTicket): ?>
                                    <div class="account-ticket__messages">
                                        <?php foreach ($messagesForTicket as $message): ?>
                                            <?php
                                                $author = isset($message['author_name']) ? $message['author_name'] : (isset($message['user_id']) ? 'Kullanici #' . $message['user_id'] : 'Sistem');
                                                if (isset($message['author_role']) && $message['author_role']) {
                                                    $author .= ' (' . $message['author_role'] . ')';
                                                }
                                                $messageAt = isset($message['created_at']) ? $message['created_at'] : '';
                                            ?>
                                            <div class="account-ticket__message">
                                                <div class="account-ticket__message-header">
                                                    <strong><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <time><?= htmlspecialchars($messageAt ? date('d.m.Y H:i', strtotime($messageAt)) : '-', ENT_QUOTES, 'UTF-8') ?></time>
                                                </div>
                                                <p><?= nl2br(htmlspecialchars(isset($message['message']) ? $message['message'] : '', ENT_QUOTES, 'UTF-8')) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <form method="post" class="account-form account-form--inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="tab" value="support">
                                    <input type="hidden" name="ticket_id" value="<?= htmlspecialchars((string)$ticketId, ENT_QUOTES, 'UTF-8') ?>">
                                    <label>
                                        <span>Yanıtiniz</span>
                                        <textarea name="message" rows="3" placeholder="Mesajinizi yazin" required></textarea>
                                    </label>
                                    <div class="account-form__actions">
                                        <button type="submit" class="btn btn-secondary">Yanıt Gonder</button>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="account-empty">Henuz olusturulmus bir destek talebiniz bulunmuyor.</p>
                <?php endif; ?>
            </div>

            <div class="account-card">
                <h3>Yeni Destek Talebi Olustur</h3>
                <form method="post" class="account-form account-form--stacked">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create_ticket">
                    <input type="hidden" name="tab" value="support">
                    <label>
                        <span>Konu</span>
                        <input type="text" name="subject" placeholder="Talep konusunu girin" required>
                    </label>
                    <label>
                        <span>Oncelik</span>
                        <select name="priority">
                            <option value="low">Dusuk</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">Yuksek</option>
                        </select>
                    </label>
                    <label>
                        <span>Mesajiniz</span>
                        <textarea name="message" rows="4" placeholder="Destek talebinizi aciklayin" required></textarea>
                    </label>
                    <div class="account-form__actions">
                        <button type="submit" class="btn btn-primary">Talep Gonder</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="account-panel<?= $activeTab === 'sessions' ? ' is-active' : '' ?>" data-account-panel="sessions" role="tabpanel">
            <?php $renderAlerts($sessionMessages); ?>
            <div class="account-card">
                <h2>Son Oturumlar</h2>
                <p class="account-card__hint">Hesabinizdaki oturum hareketlerinin kaydi.</p>
                <?php if ($sessions): ?>
                    <div class="account-table__wrapper">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Platform</th>
                                    <th>Tarayici</th>
                                    <th>IP Adresi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(isset($session['created_at']) ? date('d.m.Y H:i', strtotime($session['created_at'])) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(isset($session['platform']) && $session['platform'] !== '' ? $session['platform'] : 'Bilinmiyor', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(isset($session['browser']) && $session['browser'] !== '' ? $session['browser'] : 'Bilinmiyor', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(isset($session['ip_address']) ? $session['ip_address'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="account-empty">Oturum kaydi bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="account-panel<?= $activeTab === 'api' ? ' is-active' : '' ?>" data-account-panel="api" role="tabpanel">
            <?php $renderAlerts($apiMessages); ?>
            <div class="account-card">
                <h2>API Entegrasyon</h2>
                <p class="account-card__hint">Mağazanızı REST API ile bağlayarak stok ve siparişlerinizi otomatik olarak senkronize edin.</p>

                <div class="account-api">
                    <div class="account-api__row">
                        <span class="account-api__label">Temel URL</span>
                        <div class="account-api__value">
                            <input type="text" id="account-api-base" value="<?= htmlspecialchars($apiBaseUrl, ENT_QUOTES, 'UTF-8') ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary btn-sm account-api__copy" data-copy-target="#account-api-base" data-copy-success="Kopyalandı!">Kopyala</button>
                        </div>
                    </div>
                    <div class="account-api__row">
                        <span class="account-api__label">Dokümantasyon</span>
                        <div class="account-api__value">
                            <a class="account-api__link" href="<?= htmlspecialchars($apiDocsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">API dokümantasyonunu görüntüle</a>
                        </div>
                    </div>
                    <?php if ($apiHasToken): ?>
                        <div class="account-api__row account-api__row--token">
                            <span class="account-api__label">API Anahtarı</span>
                            <div class="account-api__value">
                                <input type="text" id="account-api-token" value="<?= htmlspecialchars($apiTokenValue, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                <button type="button" class="btn btn-outline-secondary btn-sm account-api__copy" data-copy-target="#account-api-token" data-copy-success="Kopyalandı!">Kopyala</button>
                            </div>
                        </div>
                        <div class="account-api__meta">
                            <?php if ($apiCreatedAt): ?>
                                <span>Oluşturma: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($apiCreatedAt)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($apiLastUsed): ?>
                                <span>Son kullanım: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($apiLastUsed)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span>Son kullanım: Henüz kullanılmadı</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$apiHasToken): ?>
                    <form method="post" class="account-form account-api__form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="generate_api_token">
                        <input type="hidden" name="tab" value="api">
                        <div class="account-form__grid account-form__grid--single">
                            <label>
                                <span>Entegrasyon Etiketi (Opsiyonel)</span>
                                <input type="text" name="label" placeholder="WooCommerce Entegrasyonu">
                            </label>
                        </div>
                        <div class="account-form__actions">
                            <button type="submit" class="btn btn-primary">API Anahtarı Oluştur</button>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="post" class="account-form account-api__form account-api__form--settings">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="save_api_settings">
                        <input type="hidden" name="tab" value="api">
                        <div class="account-form__grid account-form__grid--single">
                            <label>
                                <span>API Etiketi</span>
                                <input type="text" name="label" value="<?= htmlspecialchars($apiLabel, ENT_QUOTES, 'UTF-8') ?>" placeholder="WooCommerce Entegrasyonu">
                            </label>
                            <label>
                                <span>Webhook URL (Opsiyonel)</span>
                                <input type="url" name="webhook_url" value="<?= htmlspecialchars($apiWebhook, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://magazaniz.com/api/webhook">
                            </label>
                        </div>
                        <div class="account-form__actions">
                            <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                        </div>
                    </form>

                    <form method="post" class="account-form account-api__form account-api__form--regenerate" onsubmit="return confirm('Mevcut API anahtarınız geçersiz hale gelecek. Devam etmek istiyor musunuz?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="regenerate_api_token">
                        <input type="hidden" name="tab" value="api">
                        <div class="account-form__grid account-form__grid--single">
                            <label>
                                <span>Yeni Etiket (Opsiyonel)</span>
                                <input type="text" name="label" placeholder="Yeni entegrasyon etiketi">
                            </label>
                        </div>
                        <div class="account-form__actions">
                            <button type="submit" class="btn btn-outline-danger">API Anahtarını Yenile</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>

<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$pdo = Database::connection();
$currentUser = $_SESSION['user'];

$providerDefinitions = array(
    'paytr' => array(
        'label' => 'PayTR',
        'webhook' => '/webhooks/paytr.php',
        'description' => 'PayTR sanal pos ile tahsilat yapin. Uygulama anahtarlarinizi PayTR panelinden alabilirsiniz.',
        'fields' => array(
            'paytr_merchant_id' => array('label' => 'Merchant ID', 'required' => true),
            'paytr_merchant_key' => array('label' => 'Merchant Key', 'required' => true),
            'paytr_merchant_salt' => array('label' => 'Merchant Salt', 'required' => true),
            'paytr_callback_url' => array('label' => 'Callback URL (opsiyonel)', 'required' => false),
        ),
    ),
    'iyzico' => array(
        'label' => 'Iyzico',
        'webhook' => '/webhooks/iyzico.php',
        'description' => 'Iyzico ile guvenli tahsilat. API bilgilerinizi Iyzico Merchant panelinden alabilirsiniz.',
        'fields' => array(
            'iyzico_api_key' => array('label' => 'API Key', 'required' => true),
            'iyzico_secret_key' => array('label' => 'Secret Key', 'required' => true),
            'iyzico_base_url' => array('label' => 'API Base URL', 'required' => false, 'default' => 'https://api.iyzipay.com'),
        ),
    ),
    'shopier' => array(
        'label' => 'Shopier',
        'webhook' => '/webhooks/shopier.php',
        'description' => 'Shopier odemelerini entegre edin. API anahtarlarinizi Shopier panelinden kopyalayin.',
        'fields' => array(
            'shopier_api_key' => array('label' => 'API Key', 'required' => true),
            'shopier_secret_key' => array('label' => 'Secret Key', 'required' => true),
            'shopier_callback_url' => array('label' => 'Callback URL (opsiyonel)', 'required' => false),
        ),
    ),
    'cryptomus' => array(
        'label' => 'Cryptomus',
        'webhook' => '/webhooks/cryptomus.php',
        'description' => 'Cryptomus ile kripto odemeleri kabul edin. Merchant ve API bilgilerinizi giriniz.',
        'fields' => array(
            'cryptomus_merchant_uuid' => array('label' => 'Merchant UUID', 'required' => true),
            'cryptomus_api_key' => array('label' => 'API Key', 'required' => true),
            'cryptomus_base_url' => array('label' => 'API Base URL', 'required' => false, 'default' => 'https://api.cryptomus.com/v1'),
            'cryptomus_description' => array('label' => 'Odeme Aciklamasi (opsiyonel)', 'required' => false),
        ),
    ),
    'paypal' => array(
        'label' => 'PayPal',
        'webhook' => '/webhooks/paypal.php',
        'description' => 'PayPal ile odeme almak icin REST API bilgilerini giriniz.',
        'fields' => array(
            'paypal_client_id' => array('label' => 'Client ID', 'required' => true),
            'paypal_client_secret' => array('label' => 'Client Secret', 'required' => true),
            'paypal_mode' => array('label' => 'Mod (live / sandbox)', 'required' => false, 'default' => 'sandbox'),
        ),
    ),
    'stripe' => array(
        'label' => 'Stripe',
        'webhook' => '/webhooks/stripe.php',
        'description' => 'Stripe ile odeme almak icin anahtarlarinizi giriniz ve webhook url\'sini Stripe paneline ekleyiniz.',
        'fields' => array(
            'stripe_publishable_key' => array('label' => 'Publishable Key', 'required' => true),
            'stripe_secret_key' => array('label' => 'Secret Key', 'required' => true),
            'stripe_webhook_secret' => array('label' => 'Webhook Secret', 'required' => false),
        ),
    ),
    'bank_transfer' => array(
        'label' => 'Banka Havale / EFT',
        'webhook' => null,
        'description' => 'Musterileriniz banka transferi ile odeme yapip dekont gonderebilir. Aktif banka hesaplarini asagida yonetin.',
        'fields' => array(
            'bank_transfer_instructions' => array('label' => 'Genel Talimat (opsiyonel)', 'required' => false),
        ),
    ),
);

$settingKeys = array('payment_test_mode');
foreach ($providerDefinitions as $providerKey => $definition) {
    $settingKeys[] = $providerKey . '_enabled';
    foreach ($definition['fields'] as $fieldKey => $fieldConfig) {
        $settingKeys[] = $fieldKey;
    }
}

$currentValues = Settings::getMany($settingKeys);
$providerFeedback = array('errors' => array(), 'success' => '');
$bankFeedback = array('errors' => array(), 'success' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save_settings';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        if ($action === 'save_settings') {
            $providerFeedback['errors'][] = 'Gecersiz istek. Lutfen sayfayi yenileyip tekrar deneyin.';
        } else {
            $bankFeedback['errors'][] = 'Gecersiz istek. Lutfen sayfayi yenileyip tekrar deneyin.';
        }
    } else {
        if ($action === 'save_settings') {
            $testMode = isset($_POST['payment_test_mode']) ? '1' : '0';
            Settings::set('payment_test_mode', $testMode);

            foreach ($providerDefinitions as $providerKey => $definition) {
                $enabledKey = $providerKey . '_enabled';
                $isEnabled = isset($_POST[$enabledKey]) ? '1' : '0';

                foreach ($definition['fields'] as $fieldKey => $fieldConfig) {
                    $value = isset($_POST[$fieldKey]) ? trim((string)$_POST[$fieldKey]) : '';
                    if ($fieldConfig['required'] && $isEnabled === '1' && $value === '') {
                        $providerFeedback['errors'][] = sprintf('%s icin %s alanini doldurunuz.', $definition['label'], $fieldConfig['label']);
                    }
                }
            }

            if (!$providerFeedback['errors']) {
                foreach ($providerDefinitions as $providerKey => $definition) {
                    $enabledKey = $providerKey . '_enabled';
                    $isEnabled = isset($_POST[$enabledKey]) ? '1' : '0';
                    Settings::set($enabledKey, $isEnabled);

                    foreach ($definition['fields'] as $fieldKey => $fieldConfig) {
                        $value = isset($_POST[$fieldKey]) ? trim((string)$_POST[$fieldKey]) : '';
                        if ($value === '' && isset($fieldConfig['default'])) {
                            $value = $fieldConfig['default'];
                        }
                        Settings::set($fieldKey, $value !== '' ? $value : null);
                    }
                }

                $providerFeedback['success'] = 'Odeme saglayici ayarlari guncellendi.';
                AuditLog::record(
                    $currentUser['id'],
                    'settings.payments.providers',
                    'settings',
                    null,
                    'Odeme saglayici ayarlari guncellendi'
                );
                $currentValues = Settings::getMany($settingKeys);
            }
        } elseif ($action === 'create_bank_account') {
            $bankName = isset($_POST['bank_name']) ? trim((string)$_POST['bank_name']) : '';
            $accountHolder = isset($_POST['account_holder']) ? trim((string)$_POST['account_holder']) : '';
            $iban = isset($_POST['iban']) ? trim((string)$_POST['iban']) : '';
            $branch = isset($_POST['branch']) ? trim((string)$_POST['branch']) : '';
            $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
            $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($bankName === '' || $accountHolder === '' || $iban === '') {
                $bankFeedback['errors'][] = 'Banka adi, hesap sahibi ve IBAN alanlari zorunludur.';
            }

            if (!$bankFeedback['errors']) {
                $stmt = $pdo->prepare('INSERT INTO bank_accounts (bank_name, account_holder, iban, branch, description, is_active, sort_order, created_at) VALUES (:bank_name, :account_holder, :iban, :branch, :description, :is_active, :sort_order, NOW())');
                $stmt->execute(array(
                    'bank_name' => $bankName,
                    'account_holder' => $accountHolder,
                    'iban' => $iban,
                    'branch' => $branch !== '' ? $branch : null,
                    'description' => $description !== '' ? $description : null,
                    'is_active' => $isActive,
                    'sort_order' => $sortOrder,
                ));

                $bankFeedback['success'] = 'Banka hesabi eklendi.';
                AuditLog::record(
                    $currentUser['id'],
                    'settings.payments.bank.create',
                    'bank_account',
                    (int)$pdo->lastInsertId(),
                    sprintf('Banka hesabı eklendi: %s', $bankName)
                );
            }
        } elseif ($action === 'update_bank_account') {
            $bankId = isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : 0;
            $bankName = isset($_POST['bank_name']) ? trim((string)$_POST['bank_name']) : '';
            $accountHolder = isset($_POST['account_holder']) ? trim((string)$_POST['account_holder']) : '';
            $iban = isset($_POST['iban']) ? trim((string)$_POST['iban']) : '';
            $branch = isset($_POST['branch']) ? trim((string)$_POST['branch']) : '';
            $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
            $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($bankId <= 0) {
                $bankFeedback['errors'][] = 'Guncellenecek banka hesabi secilemedi.';
            }
            if ($bankName === '' || $accountHolder === '' || $iban === '') {
                $bankFeedback['errors'][] = 'Banka adi, hesap sahibi ve IBAN alanlari zorunludur.';
            }

            if (!$bankFeedback['errors']) {
                $stmt = $pdo->prepare('UPDATE bank_accounts SET bank_name = :bank_name, account_holder = :account_holder, iban = :iban, branch = :branch, description = :description, is_active = :is_active, sort_order = :sort_order, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $bankId,
                    'bank_name' => $bankName,
                    'account_holder' => $accountHolder,
                    'iban' => $iban,
                    'branch' => $branch !== '' ? $branch : null,
                    'description' => $description !== '' ? $description : null,
                    'is_active' => $isActive,
                    'sort_order' => $sortOrder,
                ));

                $bankFeedback['success'] = 'Banka hesabi guncellendi.';
                AuditLog::record(
                    $currentUser['id'],
                    'settings.payments.bank.update',
                    'bank_account',
                    $bankId,
                    sprintf('Banka hesabı guncellendi: %s', $bankName)
                );
            }
        } elseif ($action === 'delete_bank_account') {
            $bankId = isset($_POST['bank_id']) ? (int)$_POST['bank_id'] : 0;
            if ($bankId <= 0) {
                $bankFeedback['errors'][] = 'Silinecek banka hesabi secilemedi.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM bank_accounts WHERE id = :id');
                $stmt->execute(array('id' => $bankId));
                $bankFeedback['success'] = 'Banka hesabi silindi.';
                AuditLog::record(
                    $currentUser['id'],
                    'settings.payments.bank.delete',
                    'bank_account',
                    $bankId,
                    'Banka hesabı silindi'
                );
            }
        }
    }
}

$bankAccounts = $pdo->query('SELECT id, bank_name, account_holder, iban, branch, description, is_active, sort_order, created_at, updated_at FROM bank_accounts ORDER BY sort_order ASC, bank_name ASC')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Odeme Ayarlari';
include __DIR__ . '/templates/header.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'example.com');
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Odeme Saglayicilari</h5>
                <span class="badge bg-secondary">Test modu: <?= isset($currentValues['payment_test_mode']) && $currentValues['payment_test_mode'] === '1' ? 'Acik' : 'Kapali' ?></span>
            </div>
            <div class="card-body">
                <?php if ($providerFeedback['errors']): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($providerFeedback['errors'] as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($providerFeedback['success']): ?>
                    <div class="alert alert-success mb-3"><?= Helpers::sanitize($providerFeedback['success']) ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-4">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="paymentTestMode" name="payment_test_mode" <?= isset($currentValues['payment_test_mode']) && $currentValues['payment_test_mode'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="paymentTestMode">Test modunu aktif et</label>
                    </div>

                    <?php foreach ($providerDefinitions as $providerKey => $definition): ?>
                        <section class="payment-provider-card border rounded-3 p-4">
                            <header class="payment-provider-card__header d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?= Helpers::sanitize($definition['label']) ?></h6>
                                    <p class="text-muted mb-0 small"><?= Helpers::sanitize($definition['description']) ?></p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="<?= Helpers::sanitize($providerKey) ?>Enabled" name="<?= Helpers::sanitize($providerKey) ?>_enabled" <?= isset($currentValues[$providerKey . '_enabled']) && $currentValues[$providerKey . '_enabled'] === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= Helpers::sanitize($providerKey) ?>Enabled">Aktif</label>
                                </div>
                            </header>

                            <?php if (!empty($definition['webhook'])): ?>
                                <div class="alert alert-info small">
                                    <strong>Bildirim URL:</strong>
                                    <code><?= Helpers::sanitize($baseUrl . $definition['webhook']) ?></code>
                                    <br>Odeme saglayici panelinde callback / webhook adresi olarak tanimlayiniz.
                                </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <?php foreach ($definition['fields'] as $fieldKey => $fieldConfig): ?>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= Helpers::sanitize($fieldConfig['label']) ?><?= $fieldConfig['required'] ? ' *' : '' ?></label>
                                        <?php if (substr($fieldKey, -12) === 'instructions'): ?>
                                            <textarea name="<?= Helpers::sanitize($fieldKey) ?>" class="form-control" rows="3"><?= Helpers::sanitize(isset($currentValues[$fieldKey]) ? $currentValues[$fieldKey] : '') ?></textarea>
                                        <?php else: ?>
                                            <input type="text" name="<?= Helpers::sanitize($fieldKey) ?>" class="form-control" value="<?= Helpers::sanitize(isset($currentValues[$fieldKey]) ? $currentValues[$fieldKey] : (isset($fieldConfig['default']) ? $fieldConfig['default'] : '')) ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Ayarlari Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Banka Hesaplari</h5>
                <?php if ($bankFeedback['success']): ?>
                    <span class="badge bg-success"><?= Helpers::sanitize($bankFeedback['success']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($bankFeedback['errors']): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($bankFeedback['errors'] as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="bank-account-form vstack gap-3 mb-4">
                    <h6 class="mb-0">Yeni banka hesabi ekle</h6>
                    <input type="hidden" name="action" value="create_bank_account">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Banka Adi *</label>
                            <input type="text" name="bank_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hesap Sahibi *</label>
                            <input type="text" name="account_holder" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">IBAN *</label>
                            <input type="text" name="iban" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sube (opsiyonel)</label>
                            <input type="text" name="branch" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Aciklama (opsiyonel)</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sira</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="bankCreateActive" name="is_active" checked>
                                <label class="form-check-label" for="bankCreateActive">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Banka Hesabi Ekle</button>
                </form>

                <?php if ($bankAccounts): ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Banka</th>
                                    <th>Hesap Sahibi</th>
                                    <th>IBAN</th>
                                    <th>Durum</th>
                                    <th class="text-end">Islemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bankAccounts as $bank): ?>
                                    <tr>
                                        <td><?= (int)$bank['id'] ?></td>
                                        <td><?= Helpers::sanitize($bank['bank_name']) ?></td>
                                        <td><?= Helpers::sanitize($bank['account_holder']) ?></td>
                                        <td><code><?= Helpers::sanitize($bank['iban']) ?></code></td>
                                        <td>
                                            <?php if ($bank['is_active']): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBank<?= (int)$bank['id'] ?>">Duzenle</button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Banka hesabini silmek istediginize emin misiniz?');">
                                                <input type="hidden" name="action" value="delete_bank_account">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <input type="hidden" name="bank_id" value="<?= (int)$bank['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editBank<?= (int)$bank['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Banka Hesabi Duzenle</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_bank_account">
                                                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                        <input type="hidden" name="bank_id" value="<?= (int)$bank['id'] ?>">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Banka Adi</label>
                                                                <input type="text" name="bank_name" class="form-control" value="<?= Helpers::sanitize($bank['bank_name']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Hesap Sahibi</label>
                                                                <input type="text" name="account_holder" class="form-control" value="<?= Helpers::sanitize($bank['account_holder']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">IBAN</label>
                                                                <input type="text" name="iban" class="form-control" value="<?= Helpers::sanitize($bank['iban']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Sube</label>
                                                                <input type="text" name="branch" class="form-control" value="<?= Helpers::sanitize(isset($bank['branch']) ? $bank['branch'] : '') ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Sira</label>
                                                                <input type="number" name="sort_order" class="form-control" value="<?= Helpers::sanitize((int)$bank['sort_order']) ?>">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Aciklama</label>
                                                                <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize(isset($bank['description']) ? $bank['description'] : '') ?></textarea>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="bankActive<?= (int)$bank['id'] ?>" name="is_active" <?= $bank['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="bankActive<?= (int)$bank['id'] ?>">Aktif</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                                        <button type="submit" class="btn btn-primary">Kaydet</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henuz banka hesabi eklenmedi.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\Settings;
use App\Services\PlayselClient;
use App\Services\PlayselProductImporter;

Auth::requireRoles(array('super_admin', 'admin', 'support'));

$pdo = Database::connection();
$errors = array();
$success = '';
$testResult = null;
$syncStats = null;

$storedBaseUrl = Settings::get('playsel_api_url');
$storedEmail = Settings::get('playsel_email');
$storedRegion = Settings::get('playsel_region_code');
$storedToken = Settings::get('playsel_token');
$storedTokenExpiry = Settings::get('playsel_token_expires_at');
$storedPasswordEncoded = Settings::get('playsel_password');
$lastSyncAt = Settings::get('playsel_last_sync_at');

$storedPassword = playselDecodeSecret($storedPasswordEncoded);
$tokenExpiryReadable = $storedTokenExpiry ? date('Y-m-d H:i:s', (int)$storedTokenExpiry) : null;

$formValues = array(
    'baseUrl' => $storedBaseUrl ? $storedBaseUrl : 'https://api.playsel.com',
    'email' => $storedEmail ? $storedEmail : '',
    'region' => $storedRegion ? $storedRegion : '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Gecersiz istek. Lutfen sayfayi yenileyip tekrar deneyin.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : 'save';
        $baseUrlInput = isset($_POST['playsel_api_url']) ? trim((string)$_POST['playsel_api_url']) : '';
        $emailInput = isset($_POST['playsel_email']) ? trim((string)$_POST['playsel_email']) : '';
        $regionInput = isset($_POST['playsel_region_code']) ? trim((string)$_POST['playsel_region_code']) : '';
        $passwordInput = isset($_POST['playsel_password']) ? (string)$_POST['playsel_password'] : '';

        if ($baseUrlInput === '') {
            $errors[] = 'Playsel API adresi zorunludur.';
        }

        if ($emailInput === '' || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Gecerli bir Playsel giris e-postasi giriniz.';
        }

        $resolvedPassword = $passwordInput !== '' ? $passwordInput : $storedPassword;
        if ($resolvedPassword === '') {
            $errors[] = 'Playsel sifresi zorunludur.';
        }

        if (!$errors) {
            if (!preg_match('~^https?://~i', $baseUrlInput)) {
                $baseUrlInput = 'https://' . ltrim($baseUrlInput, '/');
            }

            if (!filter_var($baseUrlInput, FILTER_VALIDATE_URL)) {
                $errors[] = 'Playsel API adresi gecersiz.';
            }
        }

        if (!$errors) {
            $normalizedUrl = rtrim($baseUrlInput, '/');
            Settings::set('playsel_api_url', $normalizedUrl);
            Settings::set('playsel_email', $emailInput);
            Settings::set('playsel_region_code', $regionInput !== '' ? $regionInput : null);

            if ($passwordInput !== '') {
                Settings::set('playsel_password', playselEncodeSecret($passwordInput));
                $storedPassword = $resolvedPassword = $passwordInput;
            }

            $formValues['baseUrl'] = $normalizedUrl;
            $formValues['email'] = $emailInput;
            $formValues['region'] = $regionInput;

            try {
                list($tokenValue, $expiryTimestamp) = playselEnsureToken($normalizedUrl, $emailInput, $resolvedPassword, $action === 'save');
                $storedToken = $tokenValue;
                $storedTokenExpiry = (string)$expiryTimestamp;
                $tokenExpiryReadable = $expiryTimestamp ? date('Y-m-d H:i:s', $expiryTimestamp) : null;

                $client = new PlayselClient($normalizedUrl, $tokenValue, $regionInput !== '' ? $regionInput : null);

                if ($action === 'test_connection') {
                    $customer = $client->getCustomer();
                    $credit = isset($customer['credit']) ? (string)$customer['credit'] : '0';
                    $name = isset($customer['nameSurname']) ? (string)$customer['nameSurname'] : (isset($customer['name']) ? (string)$customer['name'] : '-');
                    $testResult = array(
                        'type' => 'success',
                        'message' => sprintf('Baglanti basarili. Musteri: %s, Bakiye: %s', $name, $credit),
                    );
                } elseif ($action === 'sync_products') {
                    $allProducts = array();
                    $page = 1;
                    $pageSize = 100;
                    $maxPages = 50;

                    while ($page <= $maxPages) {
                        $batch = $client->listProducts($page, $pageSize, true);
                        if (!$batch) {
                            break;
                        }
                        $allProducts = array_merge($allProducts, $batch);
                        if (count($batch) < $pageSize) {
                            break;
                        }
                        $page++;
                    }

                    if (!$allProducts) {
                        $success = 'Playsel urun listesi bos dondu.';
                    } else {
                        $syncStats = PlayselProductImporter::sync($pdo, $allProducts);
                        $success = sprintf(
                            'Playsel urun senkronizasyonu tamamlandi. Yeni: %d, Guncellenen: %d, Atlanan: %d',
                            (int)$syncStats['imported'],
                            (int)$syncStats['updated'],
                            (int)$syncStats['skipped']
                        );
                        $timestamp = date('Y-m-d H:i:s');
                        Settings::set('playsel_last_sync_at', $timestamp);
                        $lastSyncAt = $timestamp;
                    }
                } else {
                    $success = 'Playsel entegrasyon ayarlari kaydedildi.';
                }
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

include __DIR__ . '/templates/header.php';
?>
<div class="container-xl provider-integrations">
    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-8">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= Helpers::sanitize($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
            <?php endif; ?>

            <?php if ($testResult && $testResult['type'] === 'success'): ?>
                <div class="alert alert-info"><?= Helpers::sanitize($testResult['message']) ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Playsel Ayarlari</h5>
                    <div class="text-end small text-muted">
                        <?php if ($tokenExpiryReadable): ?>
                            <div>Token Sonlanma: <?= Helpers::sanitize($tokenExpiryReadable) ?></div>
                        <?php endif; ?>
                        <?php if ($lastSyncAt): ?>
                            <div>Son Senkronizasyon: <?= Helpers::sanitize($lastSyncAt) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-4">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="playsel-api-url">API Adresi</label>
                                <input
                                    type="text"
                                    id="playsel-api-url"
                                    name="playsel_api_url"
                                    class="form-control"
                                    placeholder="https://api.playsel.com"
                                    value="<?= Helpers::sanitize($formValues['baseUrl']) ?>"
                                    required
                                >
                                <small class="text-muted">Varsayilan adres https://api.playsel.com olarak kullanilabilir.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="playsel-email">Giris E-postasi</label>
                                <input
                                    type="email"
                                    id="playsel-email"
                                    name="playsel_email"
                                    class="form-control"
                                    value="<?= Helpers::sanitize($formValues['email']) ?>"
                                    required
                                >
                                <small class="text-muted">Playsel paneline giris yaptiginiz e-posta adresi.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="playsel-password">API Sifresi</label>
                                <input
                                    type="password"
                                    id="playsel-password"
                                    name="playsel_password"
                                    class="form-control"
                                    placeholder="<?= $storedPassword !== '' ? 'Mevcut sifre korunacak' : '' ?>"
                                >
                                <small class="text-muted">Sifreyi degistirmek icin yeni degeri girin; bos birakirsaniz mevcut sifre korunur.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="playsel-region">Region Kodu (opsiyonel)</label>
                                <input
                                    type="text"
                                    id="playsel-region"
                                    name="playsel_region_code"
                                    class="form-control"
                                    value="<?= Helpers::sanitize($formValues['region']) ?>"
                                >
                                <small class="text-muted">Region kisitlamasi gerekiyorsa Playsel tarafindan bildirilen kodu girin.</small>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="action" value="save" class="btn btn-primary">
                                Ayarlari Kaydet
                            </button>
                            <button type="submit" name="action" value="test_connection" class="btn btn-outline-primary">
                                Baglantiyi Test Et
                            </button>
                            <button type="submit" name="action" value="sync_products" class="btn btn-outline-success">
                                Urunleri Senkronize Et
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Senkronizasyon Notlari</h5>
                </div>
                <div class="card-body">
                    <ul class="text-muted small ps-3 mb-0">
                        <li>Token omru doldugunda sistem otomatik olarak yeni token olusturur; sorun yasarsaniz sifreyi yeniden kaydedin.</li>
                        <li>Urunler <code>Playsel</code> kategorisi altinda olusturulur veya guncellenir; SKU degeri <code>playsel-{ID}</code> formatinda atanir.</li>
                        <li>Satis fiyati olarak Playsel <em>salePrice</em> alanindaki TRY degeri kullanilir ve paneldeki komisyon oranlarina gore satis fiyati hesaplanir.</li>
                        <li>Region kodu gereken servislerde <code>h-region-code</code> header'i otomatik gonderilir.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Token Durumu</h5>
                </div>
                <div class="card-body text-muted small">
                    <p class="mb-2"><strong>Token Kaydedildi:</strong> <?= $storedToken ? 'Evet' : 'Hayir' ?></p>
                    <p class="mb-2"><strong>Son Yenileme:</strong> <?= $tokenExpiryReadable ? Helpers::sanitize($tokenExpiryReadable) : 'Bilinmiyor' ?></p>
                    <p class="mb-0">Token omru dolmus ise "Ayarlari Kaydet" ya da "Baglantiyi Test Et" butonu ile otomatik yenilenir.</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Postman Koleksiyonu</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Playsel API icin hazirlanan Postman koleksiyonunu indirip email ve sifrenizle token alarak hizlica test edebilirsiniz.
                    </p>
                    <a href="/integrations/playsel/playsel.postman_collection.json" class="btn btn-primary w-100 mb-3">
                        Koleksiyonu Indir
                    </a>
                    <ol class="text-muted small ps-3 mb-0">
                        <li>Postman'de <em>Import</em> secenegini acin ve JSON dosyasini yukleyin.</li>
                        <li><code>baseUrl</code>, <code>email</code>, <code>password</code> ortam degiskenlerini tanimlayin.</li>
                        <li>"Generate Token" istegini calistirin ve yanitta donen <code>token</code> degerini diger isteklere otomatik aktarabilirsiniz.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

/**
 * @param string|null $value
 * @return string
 */
function playselDecodeSecret($value)
{
    if ($value === null || $value === '') {
        return '';
    }

    $decoded = base64_decode((string)$value, true);
    return $decoded !== false ? $decoded : '';
}

/**
 * @param string $value
 * @return string
 */
function playselEncodeSecret($value)
{
    return base64_encode((string)$value);
}

/**
 * @param string $baseUrl
 * @param string $email
 * @param string $password
 * @param bool   $forceRefresh
 * @return array{0:string,1:int}
 */
function playselEnsureToken($baseUrl, $email, $password, $forceRefresh = false)
{
    $currentToken = Settings::get('playsel_token');
    $expiresValue = Settings::get('playsel_token_expires_at');
    $expiresTimestamp = $expiresValue !== null ? (int)$expiresValue : 0;

    $now = time();
    $shouldRefresh = $forceRefresh || !$currentToken;
    if (!$shouldRefresh && $expiresTimestamp > 0 && $expiresTimestamp <= $now + 300) {
        $shouldRefresh = true;
    }

    if ($shouldRefresh) {
        $auth = PlayselClient::authenticate($baseUrl, $email, $password);
        $currentToken = $auth['token'];

        $hours = isset($auth['expires_in_hours']) ? (int)$auth['expires_in_hours'] : 0;
        if ($hours <= 0) {
            $hours = 1;
        }
        $expiresTimestamp = $now + ($hours * 3600);

        Settings::set('playsel_token', $currentToken);
        Settings::set('playsel_token_expires_at', (string)$expiresTimestamp);

        if (!empty($auth['customer_api_key'])) {
            Settings::set('playsel_customer_api_key', (string)$auth['customer_api_key']);
        }
    }

    if (!$currentToken) {
        throw new RuntimeException('Playsel token alinamadi.');
    }

    return array($currentToken, $expiresTimestamp);
}

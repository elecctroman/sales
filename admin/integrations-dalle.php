<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Settings;
use App\Services\DalleService;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$errors = array();
$successMessages = array();
$syncReport = array();

$settings = Settings::getMany(array(
    'integration_dalle_enabled',
    'integration_dalle_api_key',
    'integration_dalle_model',
    'integration_dalle_size',
    'integration_dalle_prompt',
    'integration_dalle_template_path',
    'integration_dalle_last_sync_at',
));

$defaultPrompt = DalleService::defaultPromptTemplate();
$promptValue = isset($settings['integration_dalle_prompt']) && trim((string)$settings['integration_dalle_prompt']) !== ''
    ? $settings['integration_dalle_prompt']
    : $defaultPrompt;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : 'save';

        if ($action === 'sync_products') {
            try {
                $limit = isset($_POST['sync_limit']) ? (int)$_POST['sync_limit'] : 5;
                $syncReport = DalleService::syncMissingProductImages($limit);
                if (!empty($syncReport['generated'])) {
                    $successMessages[] = 'Eksik ürün görselleri için üretim tamamlandı. (' . (int)$syncReport['generated'] . ' görsel)';
                } else {
                    $successMessages[] = 'Eksik ürün görseli bulunamadı.';
                }

                AuditLog::record(
                    $currentUser['id'],
                    'integrations.dalle.sync',
                    'integrations',
                    null,
                    'DALL-E ile ürün görselleri senkronize edildi'
                );
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        } elseif ($action === 'remove_template') {
            $templatePath = isset($settings['integration_dalle_template_path']) ? $settings['integration_dalle_template_path'] : null;
            if ($templatePath) {
                DalleService::deleteStoredFile($templatePath);
                Settings::set('integration_dalle_template_path', null);
                $settings['integration_dalle_template_path'] = null;
                $successMessages[] = 'Yüklenen şablon kaldırıldı.';
            }
        } else {
            $enabled = isset($_POST['enabled']);
            $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
            $model = isset($_POST['model']) ? trim($_POST['model']) : 'dall-e-3';
            $size = isset($_POST['size']) ? trim($_POST['size']) : '1024x1024';
            $prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : $defaultPrompt;

            Settings::set('integration_dalle_enabled', $enabled ? '1' : '0');
            Settings::set('integration_dalle_api_key', $apiKey !== '' ? $apiKey : null);
            Settings::set('integration_dalle_model', $model !== '' ? $model : 'dall-e-3');
            Settings::set('integration_dalle_size', $size !== '' ? $size : '1024x1024');
            Settings::set('integration_dalle_prompt', $prompt !== '' ? $prompt : $defaultPrompt);

            if (isset($_FILES['template_file']) && is_array($_FILES['template_file'])) {
                $fileError = isset($_FILES['template_file']['error']) ? (int)$_FILES['template_file']['error'] : UPLOAD_ERR_NO_FILE;
                if ($fileError !== UPLOAD_ERR_NO_FILE) {
                    if ($fileError !== UPLOAD_ERR_OK) {
                        $errors[] = 'Şablon yüklenirken bir hata oluştu (kod: ' . $fileError . ').';
                    } else {
                        $tmpPath = $_FILES['template_file']['tmp_name'];
                        $fileInfo = @getimagesize($tmpPath);
                        if (!$fileInfo || ($fileInfo[2] !== IMAGETYPE_PNG)) {
                            $errors[] = 'Şablon yalnızca PNG formatında olmalıdır.';
                        } else {
                            $targetDir = dirname(__DIR__) . '/assets/uploads/dalle/templates';
                            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                                $errors[] = 'Şablon dizini oluşturulamadı.';
                            } else {
                                $filename = 'template-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
                                $destination = $targetDir . '/' . $filename;
                                if (!move_uploaded_file($tmpPath, $destination)) {
                                    $errors[] = 'Şablon kaydedilemedi.';
                                } else {
                                    $publicPath = '/assets/uploads/dalle/templates/' . $filename;
                                    $previousTemplate = isset($settings['integration_dalle_template_path']) ? $settings['integration_dalle_template_path'] : null;
                                    Settings::set('integration_dalle_template_path', $publicPath);
                                    $settings['integration_dalle_template_path'] = $publicPath;
                                    if ($previousTemplate && $previousTemplate !== $publicPath) {
                                        DalleService::deleteStoredFile($previousTemplate);
                                    }
                                    $successMessages[] = 'Yeni PNG şablonu başarıyla yüklendi.';
                                }
                            }
                        }
                    }
                }
            }

            if (!$errors) {
                AuditLog::record(
                    $currentUser['id'],
                    'integrations.dalle.update',
                    'integrations',
                    null,
                    'DALL-E entegrasyon ayarları güncellendi'
                );
                $successMessages[] = 'DALL-E entegrasyonu ayarları kaydedildi.';
            }
        }

        $settings = Settings::getMany(array(
            'integration_dalle_enabled',
            'integration_dalle_api_key',
            'integration_dalle_model',
            'integration_dalle_size',
            'integration_dalle_prompt',
            'integration_dalle_template_path',
            'integration_dalle_last_sync_at',
        ));

        $promptValue = isset($settings['integration_dalle_prompt']) && trim((string)$settings['integration_dalle_prompt']) !== ''
            ? $settings['integration_dalle_prompt']
            : $defaultPrompt;
    }
}

$pageTitle = 'Dall-e Yapay Zeka';
$csrfToken = Helpers::csrfToken();
$templatePath = isset($settings['integration_dalle_template_path']) ? $settings['integration_dalle_template_path'] : null;
$usage = DalleService::usageSummary($templatePath);

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">DALL-E Entegrasyonu</h5>
                <span class="badge bg-primary-subtle text-primary">Ürün görsel otomasyonu</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    OpenAI DALL-E API anahtarınızı tanımlayarak ürünlerinize otomatik, şablon uyumlu görseller oluşturabilirsiniz.
                    Şablonda <code>{{product_name}}</code>, <code>{{category_name}}</code> gibi yer tutucular desteklenir.
                </p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successMessages): ?>
                    <div class="alert alert-success">
                        <ul class="mb-0">
                            <?php foreach ($successMessages as $message): ?>
                                <li><?= Helpers::sanitize($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($syncReport['errors'])): ?>
                    <div class="alert alert-warning">
                        <ul class="mb-0">
                            <?php foreach ($syncReport['errors'] as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="vstack gap-4" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="save">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dalleEnabled" name="enabled" <?= !empty($settings['integration_dalle_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dalleEnabled">DALL-E entegrasyonunu etkinleştir</label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">API Anahtarı</label>
                            <input type="text" name="api_key" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_dalle_api_key']) ? $settings['integration_dalle_api_key'] : '') ?>" placeholder="sk-...">
                            <small class="text-muted">OpenAI hesabınızdan <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a> ekranı üzerinden alın.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Model</label>
                            <?php $selectedModel = isset($settings['integration_dalle_model']) ? $settings['integration_dalle_model'] : 'dall-e-3'; ?>
                            <select name="model" class="form-select">
                                <?php foreach (array('dall-e-3' => 'DALL-E 3', 'dall-e-2' => 'DALL-E 2 (şablon uyumlu)') as $value => $label): ?>
                                    <option value="<?= Helpers::sanitize($value) ?>" <?= $value === $selectedModel ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Çıkış Boyutu</label>
                            <?php $selectedSize = isset($settings['integration_dalle_size']) ? $settings['integration_dalle_size'] : '1024x1024'; ?>
                            <select name="size" class="form-select">
                                <?php foreach (array('1024x1024', '512x512', '256x256') as $sizeOption): ?>
                                    <option value="<?= Helpers::sanitize($sizeOption) ?>" <?= $sizeOption === $selectedSize ? 'selected' : '' ?>><?= Helpers::sanitize($sizeOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Varsayılan Prompt Şablonu</label>
                        <textarea name="prompt" class="form-control" rows="5"><?= Helpers::sanitize($promptValue) ?></textarea>
                        <small class="text-muted">Kullanılabilir yer tutucular: <code>{{product_name}}</code>, <code>{{category_name}}</code>, <code>{{product_description}}</code>, <code>{{product_short_description}}</code>.</small>
                    </div>

                    <div class="border rounded p-3 bg-light">
                        <label class="form-label">PNG Şablon Görseli</label>
                        <input type="file" name="template_file" accept="image/png" class="form-control">
                        <small class="text-muted">Şablon dosyası, ürün adı ve metin alanları prompt üzerinden güncellenecek. DALL-E 2 modeli ile en iyi sonucu verir.</small>
                        <?php if ($templatePath): ?>
                            <div class="d-flex align-items-center gap-3 mt-3">
                                <img src="<?= Helpers::sanitize($templatePath) ?>" alt="Şablon önizleme" style="max-width: 140px; border-radius: 0.75rem; border: 1px solid rgba(15,23,42,.1);">
                                <div class="flex-grow-1">
                                    <p class="mb-1 small text-muted">Aktif şablon: <?= Helpers::sanitize($templatePath) ?></p>
                                    <button type="submit" name="action" value="remove_template" class="btn btn-outline-danger btn-sm">Şablonu Kaldır</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Ürünleri Senkronize Et</h6>
                <span class="badge bg-info-subtle text-info">Eksik görselleri tamamla</span>
            </div>
            <div class="card-body">
                <p class="text-muted">Ürünlerde eksik görsel bulunması halinde otomatik olarak DALL-E üzerinden yeni görseller üretir ve kaydeder.</p>
                <form method="post" class="row g-3 align-items-center">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="sync_products">
                    <div class="col-sm-4 col-md-3">
                        <label class="form-label">En fazla ürün</label>
                        <input type="number" name="sync_limit" class="form-control" min="1" max="50" value="<?= isset($_POST['sync_limit']) ? (int)$_POST['sync_limit'] : 5 ?>">
                    </div>
                    <div class="col-sm-8 col-md-6">
                        <label class="form-label">Tahmini maliyet</label>
                        <div class="form-control-plaintext fw-semibold">
                            ≈ $<?= number_format((float)$usage['estimated_cost'] * ((int)(isset($_POST['sync_limit']) ? (int)$_POST['sync_limit'] : 5)), 2) ?>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Eksik Görselleri Üret</button>
                    </div>
                </form>

                <?php if (!empty($syncReport['items'])): ?>
                    <div class="mt-4">
                        <h6>Oluşturulan Görseller</h6>
                        <ul class="small text-muted mb-0">
                            <?php foreach ($syncReport['items'] as $item): ?>
                                <li>#<?= (int)$item['id'] ?> → <?= Helpers::sanitize($item['path']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">Kullanım ve Bakiye Özeti</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-6 text-muted">Aktif model</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(ucwords(str_replace('-', ' ', $usage['model']))) ?></dd>
                    <dt class="col-6 text-muted">Çıkış boyutu</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize($usage['size']) ?></dd>
                    <dt class="col-6 text-muted">Tahmini maliyet</dt>
                    <dd class="col-6 text-end">$<?= number_format((float)$usage['estimated_cost'], 2) ?> / görsel</dd>
                    <?php if (isset($usage['balance'])): ?>
                        <dt class="col-6 text-muted">Kalan bakiye</dt>
                        <dd class="col-6 text-end">$<?= number_format((float)$usage['balance'], 2) ?></dd>
                    <?php endif; ?>
                    <?php if (isset($usage['spent'])): ?>
                        <dt class="col-6 text-muted">Harcanan toplam</dt>
                        <dd class="col-6 text-end">$<?= number_format((float)$usage['spent'], 2) ?></dd>
                    <?php endif; ?>
                    <?php if (isset($usage['expires_at'])): ?>
                        <dt class="col-6 text-muted">Bakiye son kullanma</dt>
                        <dd class="col-6 text-end"><?= Helpers::sanitize(date('d M Y', (int)$usage['expires_at'])) ?></dd>
                    <?php endif; ?>
                    <dt class="col-6 text-muted">Son senkronizasyon</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(isset($usage['last_sync']) && $usage['last_sync'] ? $usage['last_sync'] : 'Henüz yok') ?></dd>
                </dl>
                <?php if (isset($usage['billing_error'])): ?>
                    <div class="alert alert-warning mt-3 mb-0 small">Bakiye bilgisi alınamadı: <?= Helpers::sanitize($usage['billing_error']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Başlarken</h6>
            </div>
            <div class="card-body small text-muted">
                <ol class="ps-3 mb-3">
                    <li>OpenAI hesabınızdan bir <strong>billing</strong> planı oluşturun ve API anahtarınızı alın.</li>
                    <li>Burada API anahtarı, model ve çıkış boyutunu belirleyin.</li>
                    <li>Ürününüze özel PNG şablonu yükleyin. (Şeffaf bölgeler maskesiz otomatik doldurulur.)</li>
                    <li>"Eksik Görselleri Üret" butonu ile geçmiş ürünleri tamamlayın veya yeni ürün eklerken otomatik üretimden yararlanın.</li>
                </ol>
                <p class="mb-2"><strong>Not:</strong> Şablonlu üretim için <code>DALL-E 2</code> modeli önerilir. DALL-E 3 modeli sadece metinden görsel üretir.</p>
                <p class="mb-0">Ortalama maliyet tablosu: 1024x1024 DALL-E 3 ≈ $0.08, DALL-E 2 düzenleme ≈ $0.03. Bütçenizi planlarken göz önünde bulundurun.</p>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

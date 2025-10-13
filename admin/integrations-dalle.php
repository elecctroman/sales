<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$errors = array();
$success = '';

$settings = Settings::getMany(array(
    'integration_dalle_enabled',
    'integration_dalle_api_key',
    'integration_dalle_model',
    'integration_dalle_size',
    'integration_dalle_prompt',
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum dogrulamasi basarisiz oldu. Lütfen tekrar deneyin.';
    } else {
        $enabled = isset($_POST['enabled']);
        $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
        $model = isset($_POST['model']) ? trim($_POST['model']) : 'dall-e-3';
        $size = isset($_POST['size']) ? trim($_POST['size']) : '1024x1024';
        $prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';

        Settings::set('integration_dalle_enabled', $enabled ? '1' : '0');
        Settings::set('integration_dalle_api_key', $apiKey !== '' ? $apiKey : null);
        Settings::set('integration_dalle_model', $model !== '' ? $model : 'dall-e-3');
        Settings::set('integration_dalle_size', $size !== '' ? $size : '1024x1024');
        Settings::set('integration_dalle_prompt', $prompt !== '' ? $prompt : null);

        $settings = Settings::getMany(array(
            'integration_dalle_enabled',
            'integration_dalle_api_key',
            'integration_dalle_model',
            'integration_dalle_size',
            'integration_dalle_prompt',
        ));

        AuditLog::record(
            $currentUser['id'],
            'integrations.dalle.update',
            'integrations',
            null,
            'DALL-E entegrasyon ayarlari guncellendi'
        );

        $success = 'DALL-E entegrasyonu ayarlari kaydedildi.';
    }
}

$pageTitle = 'Dall-e Yapay Zeka';
$csrfToken = Helpers::csrfToken();

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">DALL-E Entegrasyonu</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">OpenAI DALL-E API ile otomatik gorsel uretimi saglamak icin gerekli bilgileri girin.</p>

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

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dalleEnabled" name="enabled" <?= !empty($settings['integration_dalle_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dalleEnabled">Entegrasyonu etkinlestir</label>
                    </div>
                    <div>
                        <label class="form-label">API Anahtari</label>
                        <input type="text" name="api_key" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_dalle_api_key']) ? $settings['integration_dalle_api_key'] : '') ?>" placeholder="sk-...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <select name="model" class="form-select">
                                <?php
                                    $modelOptions = array('dall-e-3' => 'DALL-E 3', 'dall-e-2' => 'DALL-E 2');
                                    $selectedModel = isset($settings['integration_dalle_model']) ? $settings['integration_dalle_model'] : 'dall-e-3';
                                ?>
                                <?php foreach ($modelOptions as $value => $label): ?>
                                    <option value="<?= Helpers::sanitize($value) ?>" <?= $value === $selectedModel ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cikis Boyutu</label>
                            <?php $selectedSize = isset($settings['integration_dalle_size']) ? $settings['integration_dalle_size'] : '1024x1024'; ?>
                            <select name="size" class="form-select">
                                <?php foreach (array('1024x1024', '512x512', '256x256') as $sizeOption): ?>
                                    <option value="<?= Helpers::sanitize($sizeOption) ?>" <?= $sizeOption === $selectedSize ? 'selected' : '' ?>><?= Helpers::sanitize($sizeOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Varsayilan Prompt ??ablonu</label>
                        <textarea name="prompt" class="form-control" rows="4" placeholder="Ornek: DigiPin markasina uygun, parlak neon renklerde oyun temali bir banner tasarla."><?= Helpers::sanitize(isset($settings['integration_dalle_prompt']) ? $settings['integration_dalle_prompt'] : '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Ayarlar?? Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6>Dikkat Edilmesi Gerekenler</h6>
                <ul class="small text-muted ps-3">
                    <li>API anahtariniz gizli tutulmalidir. Yetkisiz kisilerle paylasmayin.</li>
                    <li>Model secimi maliyet ve kaliteyi etkiler. DALL-E 3 daha net sonuclar sunar.</li>
                    <li>Prompt ??ablonu, panelden gorsel olustururken otomatik olarak eklenecektir.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

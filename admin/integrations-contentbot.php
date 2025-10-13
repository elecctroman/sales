
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
    'integration_contentbot_enabled',
    'integration_contentbot_endpoint',
    'integration_contentbot_api_key',
    'integration_contentbot_language',
    'integration_contentbot_tone',
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum dogrulamasi basarisiz oldu. Lutfen tekrar deneyin.';
    } else {
        $enabled = isset($_POST['enabled']);
        $endpoint = isset($_POST['endpoint']) ? trim($_POST['endpoint']) : '';
        $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
        $language = isset($_POST['language']) ? trim($_POST['language']) : 'tr';
        $tone = isset($_POST['tone']) ? trim($_POST['tone']) : 'neutral';

        Settings::set('integration_contentbot_enabled', $enabled ? '1' : '0');
        Settings::set('integration_contentbot_endpoint', $endpoint !== '' ? $endpoint : null);
        Settings::set('integration_contentbot_api_key', $apiKey !== '' ? $apiKey : null);
        Settings::set('integration_contentbot_language', $language !== '' ? $language : 'tr');
        Settings::set('integration_contentbot_tone', $tone !== '' ? $tone : 'neutral');

        $settings = Settings::getMany(array(
            'integration_contentbot_enabled',
            'integration_contentbot_endpoint',
            'integration_contentbot_api_key',
            'integration_contentbot_language',
            'integration_contentbot_tone',
        ));

        AuditLog::record(
            $currentUser['id'],
            'integrations.contentbot.update',
            'integrations',
            null,
            'Makale ve yorum botu entegrasyon ayarlari guncellendi'
        );

        $success = 'Makale & Yorum Botu ayarlari kaydedildi.';
    }
}

$pageTitle = 'Makale ve Yorum Botu';
$csrfToken = Helpers::csrfToken();

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Makale ve Yorum Botu</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Otomatik icerik olusturma servisini platformunuza baglayin. Blog yazilari, urun aciklamalari veya yorum taslaklari icin kullanabilirsiniz.</p>

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
                        <input class="form-check-input" type="checkbox" id="contentBotEnabled" name="enabled" <?= !empty($settings['integration_contentbot_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="contentBotEnabled">Servisi etkinlestir</label>
                    </div>
                    <div>
                        <label class="form-label">API Uc Noktasi</label>
                        <input type="url" name="endpoint" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_contentbot_endpoint']) ? $settings['integration_contentbot_endpoint'] : '') ?>" placeholder="https://api.example.com/generate" required>
                    </div>
                    <div>
                        <label class="form-label">API Anahtari</label>
                        <input type="text" name="api_key" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_contentbot_api_key']) ? $settings['integration_contentbot_api_key'] : '') ?>" placeholder="cb-live-...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Icerik Dili</label>
                            <?php $selectedLanguage = isset($settings['integration_contentbot_language']) ? $settings['integration_contentbot_language'] : 'tr'; ?>
                            <select name="language" class="form-select">
                                <?php foreach (array('tr' => 'Turkce', 'en' => 'Ingilizce', 'de' => 'Almanca') as $langCode => $label): ?>
                                    <option value="<?= Helpers::sanitize($langCode) ?>" <?= $langCode === $selectedLanguage ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ton</label>
                            <?php $selectedTone = isset($settings['integration_contentbot_tone']) ? $settings['integration_contentbot_tone'] : 'neutral'; ?>
                            <select name="tone" class="form-select">
                                <?php foreach (array('neutral' => 'Tarafsız', 'friendly' => 'Samimi', 'formal' => 'Resmi', 'enthusiastic' => 'Heyecanlı') as $toneValue => $toneLabel): ?>
                                    <option value="<?= Helpers::sanitize($toneValue) ?>" <?= $toneValue === $selectedTone ? 'selected' : '' ?>><?= Helpers::sanitize($toneLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6>Kullanim Senaryolari</h6>
                <ul class="small text-muted ps-3">
                    <li>Yeni urun eklediginde otomatik blog yazisi veya aciklama olustur.</li>
                    <li>Musteri yorumlarini yanitlamak icin taslak yanitlar uret.</li>
                    <li>Farkli ton ve dillerde icerik olusturup yayinlamadan once duzenleyin.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

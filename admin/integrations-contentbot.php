<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Helpers;
use App\Settings;
use App\Services\ContentAutomationService;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$errors = array();
$successMessages = array();
$descriptionReport = array();
$commentReport = array();
$articleReport = array();

$settings = Settings::getMany(array(
    'integration_contentbot_enabled',
    'integration_contentbot_endpoint',
    'integration_contentbot_api_key',
    'integration_contentbot_language',
    'integration_contentbot_tone',
    'integration_contentbot_model',
    'integration_contentbot_descriptions_enabled',
    'integration_contentbot_comments_enabled',
    'integration_contentbot_articles_enabled',
    'integration_contentbot_comment_interval_minutes',
    'integration_contentbot_article_interval_minutes',
    'integration_contentbot_keywords',
    'integration_contentbot_default_product_prompt',
    'integration_contentbot_default_comment_prompt',
    'integration_contentbot_default_article_prompt',
    'integration_contentbot_last_description_sync',
    'integration_contentbot_last_comment_run',
    'integration_contentbot_last_article_run',
    'integration_contentbot_next_comment_at',
    'integration_contentbot_next_article_at',
));

$keywordsArray = ContentAutomationService::keywords();
$keywordsText = $keywordsArray ? implode("\n", $keywordsArray) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : 'save';

        if ($action === 'generate_descriptions') {
            try {
                $limit = isset($_POST['description_limit']) ? (int)$_POST['description_limit'] : 10;
                $descriptionReport = ContentAutomationService::generateMissingProductDescriptions($limit);
                $successMessages[] = 'Ürün açıklamaları güncellendi. (' . (int)$descriptionReport['updated'] . ' ürün)';
                AuditLog::record($currentUser['id'], 'integrations.contentbot.descriptions', 'integrations', null, 'İçerik botu ürün açıklamalarını güncelledi');
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        } elseif ($action === 'run_comment') {
            try {
                $commentReport = ContentAutomationService::processCommentCycle();
                if (isset($commentReport['status']) && $commentReport['status'] === 'created') {
                    $successMessages[] = 'Yeni yorum oluşturuldu: #' . (int)$commentReport['product_id'] . ' (' . $commentReport['product_name'] . ')';
                } else {
                    $successMessages[] = isset($commentReport['message']) ? $commentReport['message'] : 'Yorum döngüsü tamamlandı.';
                }
                AuditLog::record($currentUser['id'], 'integrations.contentbot.comments', 'integrations', null, 'Yorum botu çalıştırıldı');
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        } elseif ($action === 'publish_articles') {
            try {
                $limit = isset($_POST['article_limit']) ? (int)$_POST['article_limit'] : 2;
                $articleReport = ContentAutomationService::publishArticles($limit);
                $createdCount = isset($articleReport['created']) ? count($articleReport['created']) : 0;
                $successMessages[] = $createdCount > 0
                    ? $createdCount . ' yeni makale yayınlandı.'
                    : 'Yeni makale oluşturulmadı.';
                AuditLog::record($currentUser['id'], 'integrations.contentbot.articles', 'integrations', null, 'Makale botu içerik yayınladı');
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        } else {
            $previousDescriptionsEnabled = !empty($settings['integration_contentbot_descriptions_enabled']);
            $previousCommentsEnabled = !empty($settings['integration_contentbot_comments_enabled']);
            $previousArticlesEnabled = !empty($settings['integration_contentbot_articles_enabled']);

            $enabled = isset($_POST['enabled']);
            $endpoint = isset($_POST['endpoint']) ? trim($_POST['endpoint']) : '';
            $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
            $language = isset($_POST['language']) ? trim($_POST['language']) : 'tr';
            $tone = isset($_POST['tone']) ? trim($_POST['tone']) : 'neutral';
            $model = isset($_POST['model']) ? trim($_POST['model']) : 'gpt-4o-mini';
            $descriptionsEnabled = isset($_POST['descriptions_enabled']);
            $commentsEnabled = isset($_POST['comments_enabled']);
            $articlesEnabled = isset($_POST['articles_enabled']);
            $commentInterval = isset($_POST['comment_interval']) ? max(1, (int)$_POST['comment_interval']) : 5;
            $articleInterval = isset($_POST['article_interval']) ? max(30, (int)$_POST['article_interval']) : 120;
            $productPrompt = isset($_POST['product_prompt']) ? trim($_POST['product_prompt']) : '';
            $commentPrompt = isset($_POST['comment_prompt']) ? trim($_POST['comment_prompt']) : '';
            $articlePrompt = isset($_POST['article_prompt']) ? trim($_POST['article_prompt']) : '';
            $keywordsInput = isset($_POST['keywords']) ? trim($_POST['keywords']) : '';

            $keywordList = array();
            if ($keywordsInput !== '') {
                $parts = preg_split('/[\n,]+/', $keywordsInput);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $keywordList[] = $part;
                        }
                    }
                }
            }

            if ($commentsEnabled && $articlesEnabled) {
                $articlesEnabled = false;
                $errors[] = 'Yorum botu aktifken makale botu pasif olmalıdır. Makale botu devre dışı bırakıldı.';
            }

            Settings::set('integration_contentbot_enabled', $enabled ? '1' : '0');
            Settings::set('integration_contentbot_endpoint', $endpoint !== '' ? $endpoint : null);
            Settings::set('integration_contentbot_api_key', $apiKey !== '' ? $apiKey : null);
            Settings::set('integration_contentbot_language', $language !== '' ? $language : 'tr');
            Settings::set('integration_contentbot_tone', $tone !== '' ? $tone : 'neutral');
            Settings::set('integration_contentbot_model', $model !== '' ? $model : 'gpt-4o-mini');
            Settings::set('integration_contentbot_descriptions_enabled', $descriptionsEnabled ? '1' : '0');
            Settings::set('integration_contentbot_comments_enabled', $commentsEnabled ? '1' : '0');
            Settings::set('integration_contentbot_articles_enabled', $articlesEnabled ? '1' : '0');
            Settings::set('integration_contentbot_comment_interval_minutes', (string)$commentInterval);
            Settings::set('integration_contentbot_article_interval_minutes', (string)$articleInterval);
            Settings::set('integration_contentbot_default_product_prompt', $productPrompt !== '' ? $productPrompt : null);
            Settings::set('integration_contentbot_default_comment_prompt', $commentPrompt !== '' ? $commentPrompt : null);
            Settings::set('integration_contentbot_default_article_prompt', $articlePrompt !== '' ? $articlePrompt : null);
            Settings::set('integration_contentbot_keywords', $keywordList ? json_encode($keywordList) : null);

            $settings = Settings::getMany(array(
                'integration_contentbot_enabled',
                'integration_contentbot_endpoint',
                'integration_contentbot_api_key',
                'integration_contentbot_language',
                'integration_contentbot_tone',
                'integration_contentbot_model',
                'integration_contentbot_descriptions_enabled',
                'integration_contentbot_comments_enabled',
                'integration_contentbot_articles_enabled',
                'integration_contentbot_comment_interval_minutes',
                'integration_contentbot_article_interval_minutes',
                'integration_contentbot_keywords',
                'integration_contentbot_default_product_prompt',
                'integration_contentbot_default_comment_prompt',
                'integration_contentbot_default_article_prompt',
                'integration_contentbot_last_description_sync',
                'integration_contentbot_last_comment_run',
                'integration_contentbot_last_article_run',
                'integration_contentbot_next_comment_at',
                'integration_contentbot_next_article_at',
            ));

            $keywordsArray = ContentAutomationService::keywords();
            $keywordsText = $keywordsArray ? implode("\n", $keywordsArray) : '';

            if (!$errors) {
                AuditLog::record($currentUser['id'], 'integrations.contentbot.update', 'integrations', null, 'Makale ve Yorum botu ayarları güncellendi');
                $successMessages[] = 'Makale & Yorum Botu ayarları kaydedildi.';

                if ($descriptionsEnabled && !$previousDescriptionsEnabled) {
                    // Already updated settings, so manually run once.
                    try {
                        $descriptionReport = ContentAutomationService::generateMissingProductDescriptions(10);
                        $successMessages[] = 'Aktifleştirme sonrası ilk ürün açıklaması senkronizasyonu başlatıldı.';
                    } catch (\Throwable $exception) {
                        $errors[] = 'Başlangıç açıklama senkronizasyonu başarısız: ' . $exception->getMessage();
                    }
                }

                if ($commentsEnabled && !$previousCommentsEnabled) {
                    $nextComment = date('Y-m-d H:i:s', time() + ($commentInterval * 60));
                    Settings::set('integration_contentbot_next_comment_at', $nextComment);
                    $settings['integration_contentbot_next_comment_at'] = $nextComment;
                    $successMessages[] = 'Yorum botu ilk çalışma için planlandı: ' . $nextComment;
                } elseif (!$commentsEnabled) {
                    Settings::set('integration_contentbot_next_comment_at', null);
                    $settings['integration_contentbot_next_comment_at'] = null;
                }

                if ($articlesEnabled && !$previousArticlesEnabled) {
                    $nextArticle = date('Y-m-d H:i:s', time() + ($articleInterval * 60));
                    Settings::set('integration_contentbot_next_article_at', $nextArticle);
                    $settings['integration_contentbot_next_article_at'] = $nextArticle;
                    $successMessages[] = 'Makale botu ilk yayın için planlandı: ' . $nextArticle;
                } elseif (!$articlesEnabled) {
                    Settings::set('integration_contentbot_next_article_at', null);
                    $settings['integration_contentbot_next_article_at'] = null;
                }
            }
        }
    }
}

$keywordsArray = ContentAutomationService::keywords();
$keywordsText = $keywordsArray ? implode("\n", $keywordsArray) : '';

$pageTitle = 'Makale ve Yorum Botu';
$csrfToken = Helpers::csrfToken();

$modelCosts = array(
    'gpt-4o-mini' => array('description' => 0.002, 'article' => 0.012),
    'gpt-4o' => array('description' => 0.018, 'article' => 0.09),
    'gpt-3.5-turbo' => array('description' => 0.0015, 'article' => 0.008),
);
$activeModel = isset($settings['integration_contentbot_model']) && $settings['integration_contentbot_model'] !== ''
    ? $settings['integration_contentbot_model']
    : 'gpt-4o-mini';
$costSummary = isset($modelCosts[$activeModel]) ? $modelCosts[$activeModel] : $modelCosts['gpt-4o-mini'];

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Makale ve Yorum Botu</h5>
                <span class="badge bg-success-subtle text-success">İçerik otomasyonu</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Ürün açıklamaları, SEO uyumlu blog içerikleri ve gerçekçi yorumlar için OpenAI uyumlu metin üretim servislerini bağlayın.</p>

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

                <form method="post" class="vstack gap-4">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="save">

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="contentBotEnabled" name="enabled" <?= !empty($settings['integration_contentbot_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="contentBotEnabled">API bağlantısını genel olarak aktif et</label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">API Uç Noktası</label>
                            <input type="url" name="endpoint" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_contentbot_endpoint']) ? $settings['integration_contentbot_endpoint'] : '') ?>" placeholder="https://api.openai.com/v1/chat/completions" required>
                            <small class="text-muted">OpenAI için <code>https://api.openai.com/v1/chat/completions</code> adresini kullanın.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">API Anahtarı</label>
                            <input type="text" name="api_key" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_contentbot_api_key']) ? $settings['integration_contentbot_api_key'] : '') ?>" placeholder="sk-...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Model</label>
                            <?php $selectedModel = isset($settings['integration_contentbot_model']) ? $settings['integration_contentbot_model'] : 'gpt-4o-mini'; ?>
                            <select name="model" class="form-select">
                                <?php foreach (array('gpt-4o-mini' => 'GPT-4o mini', 'gpt-4o' => 'GPT-4o', 'gpt-3.5-turbo' => 'GPT-3.5 Turbo') as $value => $label): ?>
                                    <option value="<?= Helpers::sanitize($value) ?>" <?= $value === $selectedModel ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Dil</label>
                            <?php $selectedLanguage = isset($settings['integration_contentbot_language']) ? $settings['integration_contentbot_language'] : 'tr'; ?>
                            <select name="language" class="form-select">
                                <?php foreach (array('tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch') as $code => $label): ?>
                                    <option value="<?= Helpers::sanitize($code) ?>" <?= $code === $selectedLanguage ? 'selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ton</label>
                            <?php $selectedTone = isset($settings['integration_contentbot_tone']) ? $settings['integration_contentbot_tone'] : 'neutral'; ?>
                            <select name="tone" class="form-select">
                                <?php foreach (array('neutral' => 'Tarafsız', 'friendly' => 'Samimi', 'formal' => 'Resmi', 'enthusiastic' => 'Heyecanlı') as $toneValue => $toneLabel): ?>
                                    <option value="<?= Helpers::sanitize($toneValue) ?>" <?= $toneValue === $selectedTone ? 'selected' : '' ?>><?= Helpers::sanitize($toneLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Anahtar Kelimeler</label>
                            <textarea name="keywords" class="form-control" rows="3" placeholder="her satıra veya virgülle ayırarak"><?= Helpers::sanitize($keywordsText) ?></textarea>
                            <small class="text-muted">Makale üretiminde hedeflenecek anahtar kelimeleri girin.</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="descriptionsEnabled" name="descriptions_enabled" <?= !empty($settings['integration_contentbot_descriptions_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="descriptionsEnabled">Ürün açıklamalarını otomatik doldur</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="commentsEnabled" name="comments_enabled" <?= !empty($settings['integration_contentbot_comments_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="commentsEnabled">Yorum botunu etkinleştir</label>
                            </div>
                            <div class="mt-2">
                                <label class="form-label mb-0">Yorum aralığı (dakika)</label>
                                <input type="number" min="1" name="comment_interval" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_contentbot_comment_interval_minutes']) ? $settings['integration_contentbot_comment_interval_minutes'] : '5') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="articlesEnabled" name="articles_enabled" <?= !empty($settings['integration_contentbot_articles_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="articlesEnabled">Makale botunu etkinleştir</label>
                            </div>
                            <div class="mt-2">
                                <label class="form-label mb-0">Makale paylaşım aralığı (dakika)</label>
                                <input type="number" min="30" name="article_interval" class="form-control" value="<?= Helpers::sanitize(isset($settings['integration_contentbot_article_interval_minutes']) ? $settings['integration_contentbot_article_interval_minutes'] : '120') ?>">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Ürün Açıklaması Promptu</label>
                        <textarea name="product_prompt" class="form-control" rows="4" placeholder="Özel promptunuzu girin"><?= Helpers::sanitize(isset($settings['integration_contentbot_default_product_prompt']) ? $settings['integration_contentbot_default_product_prompt'] : '') ?></textarea>
                        <small class="text-muted">Varsayılan prompt: ürün adı, kategori ve anahtar kelimeleri otomatik olarak yerleştirir.</small>
                    </div>

                    <div>
                        <label class="form-label">Yorum Promptu</label>
                        <textarea name="comment_prompt" class="form-control" rows="3"><?= Helpers::sanitize(isset($settings['integration_contentbot_default_comment_prompt']) ? $settings['integration_contentbot_default_comment_prompt'] : '') ?></textarea>
                    </div>

                    <div>
                        <label class="form-label">Makale Promptu</label>
                        <textarea name="article_prompt" class="form-control" rows="5"><?= Helpers::sanitize(isset($settings['integration_contentbot_default_article_prompt']) ? $settings['integration_contentbot_default_article_prompt'] : '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Elle Çalıştırma &amp; Senkronizasyon</h6>
                <span class="badge bg-warning-subtle text-warning">Planlı görevleri tetikle</span>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <form method="post" class="card card-body border-0 shadow-sm">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="generate_descriptions">
                            <label class="form-label">Eksik ürün açıklamalarını tamamla</label>
                            <div class="d-flex gap-2">
                                <input type="number" min="1" max="50" name="description_limit" class="form-control" value="10">
                                <button type="submit" class="btn btn-outline-primary">Çalıştır</button>
                            </div>
                            <small class="text-muted">Tahmini maliyet ≈ $<?= number_format($costSummary['description'], 3) ?> / ürün</small>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" class="card card-body border-0 shadow-sm">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="run_comment">
                            <label class="form-label">Bir yorum döngüsü başlat</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-outline-success flex-grow-1">Şimdi yorum üret</button>
                            </div>
                            <small class="text-muted">Her yorum ≈ $<?= number_format($costSummary['description'] / 2, 3) ?></small>
                        </form>
                    </div>
                </div>

                <div class="row g-3 align-items-end mt-3">
                    <div class="col-12">
                        <form method="post" class="card card-body border-0 shadow-sm">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="publish_articles">
                            <label class="form-label">SEO makalesi üret ve yayınla</label>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <input type="number" min="1" max="5" name="article_limit" class="form-control" value="2">
                                </div>
                                <div class="col-sm-8">
                                    <button type="submit" class="btn btn-outline-danger w-100">Makale üretimini başlat</button>
                                </div>
                            </div>
                            <small class="text-muted">Tahmini maliyet ≈ $<?= number_format($costSummary['article'], 2) ?> / makale</small>
                        </form>
                    </div>
                </div>

                <?php if (!empty($descriptionReport)): ?>
                    <div class="alert alert-info mt-4">
                        <strong>Ürün açıklamaları:</strong> <?= (int)$descriptionReport['updated'] ?> güncellendi, <?= (int)$descriptionReport['processed'] ?> ürün işlendi.
                    </div>
                <?php endif; ?>

                <?php if (!empty($commentReport)): ?>
                    <div class="alert alert-info mt-3">
                        <strong>Yorum botu:</strong>
                        <?php if (isset($commentReport['product_name'])): ?>
                            <?= Helpers::sanitize($commentReport['product_name']) ?> için yorum üretildi. Sonraki yorum: <?= Helpers::sanitize(isset($commentReport['next_comment_at']) ? $commentReport['next_comment_at'] : '-') ?>
                        <?php else: ?>
                            <?= Helpers::sanitize(isset($commentReport['message']) ? $commentReport['message'] : 'Döngü tamamlandı.') ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($articleReport)): ?>
                    <div class="alert alert-info mt-3">
                        <strong>Makale botu:</strong>
                        <?php if (!empty($articleReport['created'])): ?>
                            <?php foreach ($articleReport['created'] as $item): ?>
                                <div><?= Helpers::sanitize($item['title']) ?> → <code><?= Helpers::sanitize($item['slug']) ?></code></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($articleReport['errors'])): ?>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($articleReport['errors'] as $error): ?>
                                    <li><?= Helpers::sanitize($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (isset($articleReport['next_article_at'])): ?>
                            <div class="mt-2 small text-muted">Sonraki planlanan paylaşım: <?= Helpers::sanitize($articleReport['next_article_at']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">Zamanlama ve Durum</h6>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6 text-muted">Son açıklama senk.</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(isset($settings['integration_contentbot_last_description_sync']) ? $settings['integration_contentbot_last_description_sync'] : 'Henüz yok') ?></dd>
                    <dt class="col-6 text-muted">Son yorum</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(isset($settings['integration_contentbot_last_comment_run']) ? $settings['integration_contentbot_last_comment_run'] : 'Henüz yok') ?></dd>
                    <dt class="col-6 text-muted">Son makale</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(isset($settings['integration_contentbot_last_article_run']) ? $settings['integration_contentbot_last_article_run'] : 'Henüz yok') ?></dd>
                    <dt class="col-6 text-muted">Sıradaki yorum</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(isset($settings['integration_contentbot_next_comment_at']) ? $settings['integration_contentbot_next_comment_at'] : 'Planlanmadı') ?></dd>
                    <dt class="col-6 text-muted">Sıradaki makale</dt>
                    <dd class="col-6 text-end"><?= Helpers::sanitize(isset($settings['integration_contentbot_next_article_at']) ? $settings['integration_contentbot_next_article_at'] : 'Planlanmadı') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Başlarken</h6>
            </div>
            <div class="card-body small text-muted">
                <ol class="ps-3 mb-3">
                    <li>OpenAI veya uyumlu sağlayıcıdan API anahtarınızı alın ve faturalandırmayı aktif edin.</li>
                    <li>Model, dil ve ton ayarlarını markanıza göre düzenleyin.</li>
                    <li>Makale ve yorum botlarını ihtiyaçlarınıza göre sırayla aktif edin (aynı anda sadece biri çalışır).</li>
                    <li>Planlanan aralıklarla otomatik tetiklenen görevler için sunucunuzun cron/job planlayıcısına bu sayfayı POST isteğiyle çağıran görevler ekleyin (örn. <code>curl -X POST https://alanadiniz.com/admin/integrations-contentbot.php</code>) veya bu panelden manuel tetikleyin.</li>
                </ol>
                <p class="mb-2">Yaklaşık maliyet: açıklama başına $<?= number_format($costSummary['description'], 3) ?>, makale başına $<?= number_format($costSummary['article'], 2) ?>.</p>
                <p class="mb-0">SEO uyumlu içerikler için meta başlık ve açıklamalar otomatik doldurulur; yayınlanan blog yazıları <strong>blog_posts</strong> tablosuna kaydedilir.</p>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

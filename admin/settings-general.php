<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Currency;
use App\FeatureToggle;
use App\Helpers;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$errors = array();
$success = '';

$current = Settings::getMany(array(
    'site_name',
    'site_tagline',
    'seo_meta_description',
    'seo_meta_keywords',
    'pricing_commission_rate',
));

$featureLabels = array(
    'products' => 'Product catalog & purchasing',
    'orders' => 'Order history',
    'balance' => 'Customer wallet',
    'support' => 'Support tickets',
    'packages' => 'Subscription plans',
    'api' => 'API access',
);

$featureStates = FeatureToggle::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save_general';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Session token could not be verified. Please refresh the page and try again.';
    } elseif ($action === 'refresh_rate') {
        $rate = Currency::refreshRate('TRY', 'USD');
        if ($rate > 0) {
            $success = 'Exchange rate refreshed successfully.';
        } else {
            $errors[] = 'Exchange rate service could not be reached.';
        }
    } else {
        $siteName = isset($_POST['site_name']) ? trim($_POST['site_name']) : '';
        $siteTagline = isset($_POST['site_tagline']) ? trim($_POST['site_tagline']) : '';
        $metaDescription = isset($_POST['seo_meta_description']) ? trim($_POST['seo_meta_description']) : '';
        $metaKeywords = isset($_POST['seo_meta_keywords']) ? trim($_POST['seo_meta_keywords']) : '';
        $commissionInput = isset($_POST['pricing_commission_rate']) ? str_replace(',', '.', trim($_POST['pricing_commission_rate'])) : '0';

        if ($siteName === '') {
            $errors[] = 'Site name is required.';
        }

        $commissionRate = (float)$commissionInput;
        if ($commissionRate < 0) {
            $commissionRate = 0.0;
        }

        if (!$errors) {
            Settings::set('site_name', $siteName);
            Settings::set('site_tagline', $siteTagline !== '' ? $siteTagline : null);
            Settings::set('seo_meta_description', $metaDescription !== '' ? $metaDescription : null);
            Settings::set('seo_meta_keywords', $metaKeywords !== '' ? $metaKeywords : null);
            Settings::set('pricing_commission_rate', (string)$commissionRate);

            foreach ($featureLabels as $key => $label) {
                $enabled = isset($_POST['features'][$key]);
                FeatureToggle::setEnabled($key, $enabled);
                $featureStates[$key] = $enabled;
            }

            $success = 'General settings have been saved.';

            AuditLog::record(
                $currentUser['id'],
                'settings.general.update',
                'settings',
                null,
                'General settings updated'
            );

            $current = Settings::getMany(array(
                'site_name',
                'site_tagline',
                'seo_meta_description',
                'seo_meta_keywords',
                'pricing_commission_rate',
            ));
        }
    }
}

$rate = Currency::getRate('TRY', 'USD');
$tryPerUsd = $rate > 0 ? 1 / $rate : null;
$rateUpdatedAt = Settings::get('currency_rate_TRY_USD_updated');

Helpers::setPageTitle('General Settings');

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">General Settings</h5>
            </div>
            <div class="card-body">
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
                    <div class="alert alert-success">
                        <?= Helpers::sanitize($success) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Site Name</label>
                            <input type="text" name="site_name" class="form-control" value="<?= Helpers::sanitize(isset($current['site_name']) ? $current['site_name'] : Helpers::siteName()) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site Tagline</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= Helpers::sanitize(isset($current['site_tagline']) ? $current['site_tagline'] : '') ?>" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">SEO Description</label>
                            <textarea name="seo_meta_description" class="form-control" rows="3" placeholder="Short description for search engines"><?= Helpers::sanitize(isset($current['seo_meta_description']) ? $current['seo_meta_description'] : '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SEO Keywords</label>
                            <input type="text" name="seo_meta_keywords" class="form-control" value="<?= Helpers::sanitize(isset($current['seo_meta_keywords']) ? $current['seo_meta_keywords'] : '') ?>" placeholder="Comma separated">
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Platform Commission (%)</label>
                            <input type="number" name="pricing_commission_rate" step="0.01" min="0" class="form-control" value="<?= Helpers::sanitize(isset($current['pricing_commission_rate']) ? $current['pricing_commission_rate'] : '0') ?>">
                        </div>
                        <div class="col-md-8">
                            <div class="currency-card p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Live Rate</strong>
                                        <div class="text-muted small">
                                            1 USD ≈ <?= $tryPerUsd ? Helpers::sanitize(number_format($tryPerUsd, 4, '.', ',')) : '-' ?> TRY
                                        </div>
                                        <div class="text-muted small">Last update: <?= $rateUpdatedAt ? Helpers::sanitize(date('d.m.Y H:i', (int)$rateUpdatedAt)) : '-' ?></div>
                                    </div>
                                    <button type="submit" name="action" value="refresh_rate" class="btn btn-outline-primary btn-sm">Refresh</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div>
                        <h6>Feature Toggles</h6>
                        <div class="row g-3">
                            <?php foreach ($featureLabels as $key => $label): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="feature<?= Helpers::sanitize($key) ?>" name="features[<?= Helpers::sanitize($key) ?>]" <?= !empty($featureStates[$key]) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="feature<?= Helpers::sanitize($key) ?>"><?= Helpers::sanitize($label) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

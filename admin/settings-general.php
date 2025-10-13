
$settingKeys = array(
    'site_name',
    'site_tagline',
    'seo_meta_description',
    'seo_meta_keywords',
    'pricing_commission_rate',

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


    } else {
        switch ($action) {
            case 'refresh_rate':
                $rate = Currency::refreshRate('TRY', 'USD');
                if ($rate > 0) {
                    $success = 'Exchange rate refreshed successfully.';
                } else {
                    $errors[] = 'Exchange rate service could not be reached.';
                }
                break;


            case 'save_general':
            default:
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

                    $current = Settings::getMany($settingKeys);
                }
                break;
        }
    }
}

$rate = Currency::getRate('TRY', 'USD');
$tryPerUsd = $rate > 0 ? 1 / $rate : null;
$rateUpdatedAt = Settings::get('currency_rate_TRY_USD_updated');


                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>


            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

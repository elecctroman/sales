<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Notification;
use App\Settings;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$notificationErrors = array();
$notificationSuccess = '';
$lastAction = null;

$settingKeys = array(
    'notifications_enabled',
    'notifications_in_app_enabled',
    'notifications_email_enabled',
    'notifications_sms_enabled',
    'notifications_webhook_enabled',
    'notifications_default_status',
    'notifications_default_scope',
    'notifications_auto_archive_days',
    'notifications_digest_enabled',
    'notifications_digest_time',
);

$current = Settings::getMany($settingKeys);

Notification::ensureTables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save_notification_settings';
    $lastAction = $action;
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $notificationErrors[] = 'Oturum doğrulama anahtarı geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        switch ($action) {
            case 'save_notification_settings':
                $notificationsEnabled = isset($_POST['notifications_enabled']) ? '1' : '0';
                $inAppEnabled = isset($_POST['notifications_in_app_enabled']) ? '1' : '0';
                $emailEnabled = isset($_POST['notifications_email_enabled']) ? '1' : '0';
                $smsEnabled = isset($_POST['notifications_sms_enabled']) ? '1' : '0';
                $webhookEnabled = isset($_POST['notifications_webhook_enabled']) ? '1' : '0';
                $defaultStatusInput = isset($_POST['notifications_default_status']) ? strtolower(trim($_POST['notifications_default_status'])) : '';
                $defaultScopeInput = isset($_POST['notifications_default_scope']) ? strtolower(trim($_POST['notifications_default_scope'])) : '';
                $autoArchiveDaysInput = isset($_POST['notifications_auto_archive_days']) ? (int)$_POST['notifications_auto_archive_days'] : 0;
                $digestEnabled = isset($_POST['notifications_digest_enabled']) ? '1' : '0';
                $digestTimeInput = isset($_POST['notifications_digest_time']) ? trim($_POST['notifications_digest_time']) : '';

                if ($notificationsEnabled === '1' && $inAppEnabled === '0' && $emailEnabled === '0' && $smsEnabled === '0' && $webhookEnabled === '0') {
                    $notificationErrors[] = 'Bildirim sistemi açıkken en az bir kanal seçilmelidir.';
                }

                $autoArchiveDays = $autoArchiveDaysInput >= 0 ? $autoArchiveDaysInput : 0;
                $defaultStatus = in_array($defaultStatusInput, array('published', 'draft'), true) ? $defaultStatusInput : 'published';
                $defaultScope = in_array($defaultScopeInput, array('global', 'user'), true) ? $defaultScopeInput : 'global';

                if ($digestEnabled === '1') {
                    if ($digestTimeInput === '') {
                        $digestTimeInput = '09:00';
                    }
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $digestTimeInput)) {
                        $notificationErrors[] = 'Geçerli bir özet gönderim saati belirtin (HH:MM).';
                    }
                }

                if (!$notificationErrors) {
                    Settings::set('notifications_enabled', $notificationsEnabled);
                    Settings::set('notifications_in_app_enabled', $inAppEnabled);
                    Settings::set('notifications_email_enabled', $emailEnabled);
                    Settings::set('notifications_sms_enabled', $smsEnabled);
                    Settings::set('notifications_webhook_enabled', $webhookEnabled);
                    Settings::set('notifications_default_status', $defaultStatus);
                    Settings::set('notifications_default_scope', $defaultScope);
                    Settings::set('notifications_auto_archive_days', (string)$autoArchiveDays);
                    Settings::set('notifications_digest_enabled', $digestEnabled);
                    Settings::set('notifications_digest_time', $digestEnabled === '1' ? $digestTimeInput : null);

                    $notificationSuccess = 'Bildirim tercihleri kaydedildi.';

                    AuditLog::record(
                        $currentUser['id'],
                        'settings.notifications.update',
                        'settings',
                        null,
                        'Bildirim tercihleri güncellendi'
                    );

                    $current = Settings::getMany($settingKeys);
                }
                break;

            case 'create_notification':
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $message = isset($_POST['message']) ? trim($_POST['message']) : '';
                $link = isset($_POST['link']) ? trim($_POST['link']) : '';
                $scopeInput = isset($_POST['scope']) ? strtolower(trim($_POST['scope'])) : '';
                $userEmail = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
                $statusInput = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';
                $publishAtInput = isset($_POST['publish_at']) ? trim($_POST['publish_at']) : '';
                $expireAtInput = isset($_POST['expire_at']) ? trim($_POST['expire_at']) : '';

                if ($title === '' || $message === '') {
                    $notificationErrors[] = 'Bildirim başlığı ve mesajı zorunludur.';
                }

                $status = in_array($statusInput, array('draft', 'published'), true)
                    ? $statusInput
                    : (isset($current['notifications_default_status']) && $current['notifications_default_status'] ? $current['notifications_default_status'] : 'published');

                $scope = in_array($scopeInput, array('global', 'user'), true)
                    ? $scopeInput
                    : (isset($current['notifications_default_scope']) && $current['notifications_default_scope'] ? $current['notifications_default_scope'] : 'global');

                $userId = null;
                if ($scope === 'user') {
                    if ($userEmail === '') {
                        $notificationErrors[] = 'Hedef kullanıcı e-posta adresini belirtin.';
                    } else {
                        $pdo = Database::connection();
                        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                        $stmt->execute(array('email' => $userEmail));
                        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$targetUser) {
                            $notificationErrors[] = 'Belirtilen e-posta adresi ile eşleşen kullanıcı bulunamadı.';
                        } else {
                            $userId = (int)$targetUser['id'];
                        }
                    }
                }

                $publishAtTimestamp = time();
                if ($publishAtInput !== '') {
                    $publishAtTimestamp = strtotime($publishAtInput);
                    if ($publishAtTimestamp === false) {
                        $notificationErrors[] = 'Geçerli bir yayın tarihi girin.';
                        $publishAtTimestamp = time();
                    }
                }
                $publishAt = date('Y-m-d H:i:s', $publishAtTimestamp);

                $expireAt = null;
                if ($expireAtInput !== '') {
                    $expireTimestamp = strtotime($expireAtInput);
                    if ($expireTimestamp === false) {
                        $notificationErrors[] = 'Geçerli bir bitiş tarihi girin.';
                    } elseif ($expireTimestamp <= $publishAtTimestamp) {
                        $notificationErrors[] = 'Bitiş tarihi yayın tarihinden sonra olmalıdır.';
                    } else {
                        $expireAt = date('Y-m-d H:i:s', $expireTimestamp);
                    }
                }

                if (!$notificationErrors) {
                    Notification::create(array(
                        'title' => $title,
                        'message' => $message,
                        'link' => $link !== '' ? $link : null,
                        'scope' => $scope,
                        'user_id' => $userId,
                        'status' => $status,
                        'publish_at' => $publishAt,
                        'expire_at' => $expireAt,
                    ));

                    $notificationSuccess = 'Bildirim başarıyla oluşturuldu.';

                    AuditLog::record(
                        $currentUser['id'],
                        'notifications.create',
                        'notifications',
                        null,
                        'Yeni bildirim oluşturuldu'
                    );
                }
                break;

            case 'publish_notification':
            case 'archive_notification':
            case 'draft_notification':
                $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
                if ($notificationId <= 0) {
                    $notificationErrors[] = 'Geçersiz bildirim seçimi.';
                    break;
                }

                $statusMap = array(
                    'publish_notification' => 'published',
                    'archive_notification' => 'archived',
                    'draft_notification' => 'draft',
                );
                $status = $statusMap[$action];
                Notification::setStatus($notificationId, $status);

                $notificationSuccess = 'Bildirim durumu güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'notifications.status',
                    'notifications',
                    $notificationId,
                    'Bildirim durumu "' . $status . '" olarak güncellendi'
                );
                break;

            case 'delete_notification':
                $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
                if ($notificationId <= 0) {
                    $notificationErrors[] = 'Geçersiz bildirim seçimi.';
                    break;
                }

                Notification::delete($notificationId);
                $notificationSuccess = 'Bildirim kalıcı olarak silindi.';

                AuditLog::record(
                    $currentUser['id'],
                    'notifications.delete',
                    'notifications',
                    $notificationId,
                    'Bildirim silindi'
                );
                break;

            default:
                $notificationErrors[] = 'Geçersiz işlem isteği.';
                break;
        }
    }
}

$notificationStatusFilter = isset($_GET['notification_status']) ? strtolower(trim($_GET['notification_status'])) : 'all';
$allowedNotificationFilters = array('all', 'published', 'draft', 'archived', 'scheduled', 'expired');
if (!in_array($notificationStatusFilter, $allowedNotificationFilters, true)) {
    $notificationStatusFilter = 'all';
}

$notificationStats = Notification::stats();
$notificationsRaw = Notification::all();
$readCounts = Notification::readCounts(array_map(static function ($notification) {
    return isset($notification['id']) ? (int)$notification['id'] : 0;
}, $notificationsRaw));

$nowTimestamp = time();
$notifications = array();
foreach ($notificationsRaw as $notification) {
    $publishAt = isset($notification['publish_at']) ? $notification['publish_at'] : null;
    $expireAt = isset($notification['expire_at']) ? $notification['expire_at'] : null;
    $publishTimestamp = $publishAt ? strtotime($publishAt) : null;
    $expireTimestamp = $expireAt ? strtotime($expireAt) : null;
    $isScheduled = $publishTimestamp !== null && $publishTimestamp > $nowTimestamp;
    $isExpired = $expireTimestamp !== null && $expireTimestamp < $nowTimestamp;
    $isActive = (isset($notification['status']) && $notification['status'] === 'published') && !$isScheduled && !$isExpired;

    $include = true;
    if ($notificationStatusFilter === 'scheduled') {
        $include = $isScheduled;
    } elseif ($notificationStatusFilter === 'expired') {
        $include = $isExpired;
    } elseif ($notificationStatusFilter !== 'all') {
        $include = isset($notification['status']) && $notification['status'] === $notificationStatusFilter;
    }

    if (!$include) {
        continue;
    }

    $notificationId = isset($notification['id']) ? (int)$notification['id'] : 0;
    $notifications[] = array(
        'id' => $notificationId,
        'title' => isset($notification['title']) ? (string)$notification['title'] : '',
        'message' => isset($notification['message']) ? (string)$notification['message'] : '',
        'link' => isset($notification['link']) && $notification['link'] ? (string)$notification['link'] : '',
        'scope' => isset($notification['scope']) ? (string)$notification['scope'] : 'global',
        'status' => isset($notification['status']) ? (string)$notification['status'] : 'draft',
        'user_email' => isset($notification['user_email']) ? (string)$notification['user_email'] : '',
        'publish_at' => $publishAt,
        'expire_at' => $expireAt,
        'created_at' => isset($notification['created_at']) ? $notification['created_at'] : null,
        'is_scheduled' => $isScheduled,
        'is_expired' => $isExpired,
        'is_active' => $isActive,
        'read_count' => isset($readCounts[$notificationId]) ? (int)$readCounts[$notificationId] : 0,
    );
}

$notificationsTotal = count($notificationsRaw);
$visibleNotificationCount = count($notifications);

$preferenceValues = array(
    'enabled' => !empty($current['notifications_enabled']),
    'in_app' => !empty($current['notifications_in_app_enabled']),
    'email' => !empty($current['notifications_email_enabled']),
    'sms' => !empty($current['notifications_sms_enabled']),
    'webhook' => !empty($current['notifications_webhook_enabled']),
    'default_status' => isset($current['notifications_default_status']) ? strtolower((string)$current['notifications_default_status']) : 'published',
    'default_scope' => isset($current['notifications_default_scope']) ? strtolower((string)$current['notifications_default_scope']) : 'global',
    'auto_archive_days' => isset($current['notifications_auto_archive_days']) ? (string)$current['notifications_auto_archive_days'] : '0',
    'digest_enabled' => !empty($current['notifications_digest_enabled']),
    'digest_time' => isset($current['notifications_digest_time']) && $current['notifications_digest_time'] ? $current['notifications_digest_time'] : '09:00',
);

if ($lastAction === 'save_notification_settings' && $notificationErrors) {
    $preferenceValues['enabled'] = isset($_POST['notifications_enabled']);
    $preferenceValues['in_app'] = isset($_POST['notifications_in_app_enabled']);
    $preferenceValues['email'] = isset($_POST['notifications_email_enabled']);
    $preferenceValues['sms'] = isset($_POST['notifications_sms_enabled']);
    $preferenceValues['webhook'] = isset($_POST['notifications_webhook_enabled']);
    $preferenceValues['default_status'] = isset($_POST['notifications_default_status']) ? strtolower(trim($_POST['notifications_default_status'])) : $preferenceValues['default_status'];
    $preferenceValues['default_scope'] = isset($_POST['notifications_default_scope']) ? strtolower(trim($_POST['notifications_default_scope'])) : $preferenceValues['default_scope'];
    $preferenceValues['auto_archive_days'] = isset($_POST['notifications_auto_archive_days']) ? (string)$_POST['notifications_auto_archive_days'] : $preferenceValues['auto_archive_days'];
    $preferenceValues['digest_enabled'] = isset($_POST['notifications_digest_enabled']);
    $preferenceValues['digest_time'] = isset($_POST['notifications_digest_time']) ? trim($_POST['notifications_digest_time']) : $preferenceValues['digest_time'];
}

$defaultNotificationStatus = in_array($preferenceValues['default_status'], array('published', 'draft'), true)
    ? $preferenceValues['default_status']
    : 'published';
$defaultNotificationScope = in_array($preferenceValues['default_scope'], array('global', 'user'), true)
    ? $preferenceValues['default_scope']
    : 'global';
$defaultDigestTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $preferenceValues['digest_time'])
    ? $preferenceValues['digest_time']
    : '09:00';

$createForm = array(
    'title' => '',
    'message' => '',
    'link' => '',
    'status' => $defaultNotificationStatus,
    'scope' => $defaultNotificationScope,
    'user_email' => '',
    'publish_at' => '',
    'expire_at' => '',
);

if ($lastAction === 'create_notification' && $notificationErrors) {
    $createForm['title'] = isset($_POST['title']) ? trim($_POST['title']) : '';
    $createForm['message'] = isset($_POST['message']) ? trim($_POST['message']) : '';
    $createForm['link'] = isset($_POST['link']) ? trim($_POST['link']) : '';
    $createForm['status'] = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : $createForm['status'];
    $createForm['scope'] = isset($_POST['scope']) ? strtolower(trim($_POST['scope'])) : $createForm['scope'];
    $createForm['user_email'] = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
    $createForm['publish_at'] = isset($_POST['publish_at']) ? trim($_POST['publish_at']) : '';
    $createForm['expire_at'] = isset($_POST['expire_at']) ? trim($_POST['expire_at']) : '';
}

Helpers::setPageTitle('Bildirim Ayarları');

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h5 class="mb-0">Bildirim Ayarları</h5>
                    <small class="text-muted">Bildirim kanallarını yönetin, yeni duyurular oluşturun ve tüm geçmişi inceleyin.</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-primary">Toplam <?= (int)(isset($notificationStats['total']) ? $notificationStats['total'] : 0) ?></span>
                    <span class="badge bg-success">Aktif <?= (int)(isset($notificationStats['active']) ? $notificationStats['active'] : 0) ?></span>
                    <span class="badge bg-warning text-dark">Planlı <?= (int)(isset($notificationStats['scheduled']) ? $notificationStats['scheduled'] : 0) ?></span>
                    <span class="badge bg-secondary">Taslak <?= (int)(isset($notificationStats['draft']) ? $notificationStats['draft'] : 0) ?></span>
                    <span class="badge bg-dark">Arşiv <?= (int)(isset($notificationStats['archived']) ? $notificationStats['archived'] : 0) ?></span>
                    <span class="badge bg-info text-dark">Hedefli <?= (int)(isset($notificationStats['targeted']) ? $notificationStats['targeted'] : 0) ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($notificationSuccess): ?>
                    <div class="alert alert-success mb-4">
                        <?= Helpers::sanitize($notificationSuccess) ?>
                    </div>
                <?php endif; ?>

                <?php if ($notificationErrors): ?>
                    <div class="alert alert-danger mb-4">
                        <ul class="mb-0">
                            <?php foreach ($notificationErrors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row g-4 align-items-start">
                    <div class="col-12 col-xxl-4">
                        <div class="vstack gap-4">
                            <form method="post" class="card border-0 shadow-sm">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">Bildirim Tercihleri</h6>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                                    <input type="hidden" name="action" value="save_notification_settings">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="notificationsEnabled" name="notifications_enabled" <?= $preferenceValues['enabled'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="notificationsEnabled">Sistem bildirimlerini aktif et</label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kullanılan kanallar</label>
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="channelInApp" name="notifications_in_app_enabled" <?= $preferenceValues['in_app'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="channelInApp">Panel içi</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="channelEmail" name="notifications_email_enabled" <?= $preferenceValues['email'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="channelEmail">E-posta</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="channelSms" name="notifications_sms_enabled" <?= $preferenceValues['sms'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="channelSms">SMS</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="channelWebhook" name="notifications_webhook_enabled" <?= $preferenceValues['webhook'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="channelWebhook">Webhook</label>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted">Aktif kanallar otomatik gönderimlerde kullanılır.</small>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Varsayılan durum</label>
                                            <select name="notifications_default_status" class="form-select">
                                                <option value="published" <?= $defaultNotificationStatus === 'published' ? 'selected' : '' ?>>Yayınla</option>
                                                <option value="draft" <?= $defaultNotificationStatus === 'draft' ? 'selected' : '' ?>>Taslak</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Varsayılan hedef</label>
                                            <select name="notifications_default_scope" class="form-select">
                                                <option value="global" <?= $defaultNotificationScope === 'global' ? 'selected' : '' ?>>Tüm kullanıcılar</option>
                                                <option value="user" <?= $defaultNotificationScope === 'user' ? 'selected' : '' ?>>Belirli kullanıcı</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-1">
                                        <div class="col-md-6">
                                            <label class="form-label">Otomatik arşiv (gün)</label>
                                            <input type="number" min="0" name="notifications_auto_archive_days" class="form-control" value="<?= Helpers::sanitize($preferenceValues['auto_archive_days']) ?>">
                                            <small class="text-muted">0 değeri devre dışı bırakır.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Günlük özet</label>
                                            <div class="input-group">
                                                <div class="input-group-text">
                                                    <input class="form-check-input mt-0" type="checkbox" name="notifications_digest_enabled" value="1" <?= $preferenceValues['digest_enabled'] ? 'checked' : '' ?>>
                                                </div>
                                                <input type="time" name="notifications_digest_time" class="form-control" value="<?= Helpers::sanitize($defaultDigestTime) ?>">
                                            </div>
                                            <small class="text-muted">Aktifse seçilen saatte e-posta özeti gönderilir.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-white text-end">
                                    <button type="submit" class="btn btn-outline-primary">Tercihleri Kaydet</button>
                                </div>
                            </form>

                            <form method="post" class="card border-0 shadow-sm">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">Yeni Bildirim Oluştur</h6>
                                </div>
                                <div class="card-body vstack gap-3">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                                    <input type="hidden" name="action" value="create_notification">
                                    <div>
                                        <label class="form-label">Başlık</label>
                                        <input type="text" name="title" class="form-control" value="<?= Helpers::sanitize($createForm['title']) ?>" placeholder="Kısa ve dikkat çekici bir başlık" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Mesaj</label>
                                        <textarea name="message" class="form-control" rows="4" placeholder="Kullanıcıya iletilecek mesaj" required><?= Helpers::sanitize($createForm['message']) ?></textarea>
                                    </div>
                                    <div>
                                        <label class="form-label">Link (opsiyonel)</label>
                                        <input type="url" name="link" class="form-control" value="<?= Helpers::sanitize($createForm['link']) ?>" placeholder="https://ornek.com/detay">
                                        <small class="text-muted">Belirtilirse kullanıcı detay sayfasına yönlendirilir.</small>
                                    </div>
                                    <div>
                                        <label class="form-label d-block">Hedef</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="scope" id="scopeGlobal" value="global" <?= $createForm['scope'] === 'global' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="scopeGlobal">Tüm kullanıcılar</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="scope" id="scopeUser" value="user" <?= $createForm['scope'] === 'user' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="scopeUser">Belirli kullanıcı</label>
                                        </div>
                                        <input type="email" name="user_email" class="form-control mt-2" value="<?= Helpers::sanitize($createForm['user_email']) ?>" placeholder="Kullanıcı e-postası (yalnızca hedefli gönderimler için)">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Durum</label>
                                            <select class="form-select" name="status">
                                                <option value="published" <?= $createForm['status'] === 'published' ? 'selected' : '' ?>>Yayınla</option>
                                                <option value="draft" <?= $createForm['status'] === 'draft' ? 'selected' : '' ?>>Taslak</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Yayın tarihi</label>
                                            <input type="datetime-local" name="publish_at" class="form-control" value="<?= Helpers::sanitize($createForm['publish_at']) ?>">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Sona erme (opsiyonel)</label>
                                        <input type="datetime-local" name="expire_at" class="form-control" value="<?= Helpers::sanitize($createForm['expire_at']) ?>">
                                    </div>
                                </div>
                                <div class="card-footer bg-white text-end">
                                    <button type="submit" class="btn btn-primary">Bildirimi Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-12 col-xxl-8">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <strong>Bildirim Geçmişi</strong>
                                <div class="text-muted small">Gösterilen: <?= (int)$visibleNotificationCount ?> / <?= (int)$notificationsTotal ?> kayıt.</div>
                            </div>
                            <form method="get" class="d-flex align-items-center gap-2">
                                <label for="notificationStatusFilter" class="form-label mb-0 small text-muted">Duruma göre filtrele</label>
                                <select id="notificationStatusFilter" name="notification_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php
                                    $filterLabels = array(
                                        'all' => 'Tümü',
                                        'published' => 'Yayında',
                                        'draft' => 'Taslak',
                                        'archived' => 'Arşiv',
                                        'scheduled' => 'Planlı',
                                        'expired' => 'Süresi dolan',
                                    );
                                    foreach ($filterLabels as $filterKey => $filterLabel):
                                        $selected = $notificationStatusFilter === $filterKey ? 'selected' : '';
                                    ?>
                                        <option value="<?= Helpers::sanitize($filterKey) ?>" <?= $selected ?>><?= Helpers::sanitize($filterLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($notificationStatusFilter !== 'all'): ?>
                                    <a href="/admin/settings-notifications.php" class="btn btn-link btn-sm text-decoration-none">Sıfırla</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <?php if (!$notifications): ?>
                            <div class="text-muted">Seçilen kriterlere uygun bildirim bulunamadı.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th>Başlık</th>
                                            <th>Durum</th>
                                            <th>Hedef</th>
                                            <th>Yayın</th>
                                            <th>Okunma</th>
                                            <th class="text-end">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notifications as $notification): ?>
                                            <?php
                                            $isDraft = $notification['status'] === 'draft';
                                            $isPublished = $notification['status'] === 'published';
                                            $isArchived = $notification['status'] === 'archived';
                                            $publishLabel = $notification['publish_at'] ? date('d.m.Y H:i', strtotime($notification['publish_at'])) : '-';
                                            $expireLabel = $notification['expire_at'] ? date('d.m.Y H:i', strtotime($notification['expire_at'])) : '';
                                            ?>
                                            <tr>
                                                <td><?= (int)$notification['id'] ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= Helpers::sanitize($notification['title']) ?></div>
                                                    <div class="text-muted small mb-1"><?= Helpers::sanitize(mb_strimwidth($notification['message'], 0, 110, '…')) ?></div>
                                                    <?php if ($notification['link']): ?>
                                                        <a href="<?= Helpers::sanitize($notification['link']) ?>" class="small" target="_blank" rel="noopener noreferrer">Bağlantıyı aç</a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $isPublished ? 'success' : ($isDraft ? 'secondary' : 'dark') ?>"><?= Helpers::sanitize(ucfirst($notification['status'])) ?></span>
                                                    <?php if ($notification['is_scheduled']): ?>
                                                        <span class="badge bg-warning text-dark ms-1">Planlı</span>
                                                    <?php elseif ($notification['is_expired']): ?>
                                                        <span class="badge bg-secondary ms-1">Süresi doldu</span>
                                                    <?php elseif ($notification['is_active']): ?>
                                                        <span class="badge bg-light text-success ms-1">Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($notification['scope'] === 'user' && $notification['user_email']): ?>
                                                        <span class="badge bg-info text-dark">Hedefli</span>
                                                        <div class="small text-muted"><?= Helpers::sanitize($notification['user_email']) ?></div>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Genel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="small text-muted">Yayın: <?= Helpers::sanitize($publishLabel) ?></div>
                                                    <?php if ($expireLabel): ?>
                                                        <div class="small text-muted">Bitiş: <?= Helpers::sanitize($expireLabel) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?= (int)$notification['read_count'] ?> okunma</span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#notificationPreview<?= (int)$notification['id'] ?>">Detay</button>
                                                        <form method="post" class="d-inline-flex gap-2">
                                                            <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                                                            <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                                            <?php if (!$isPublished): ?>
                                                                <button type="submit" name="action" value="publish_notification" class="btn btn-outline-success btn-sm">Yayınla</button>
                                                            <?php endif; ?>
                                                            <?php if (!$isDraft): ?>
                                                                <button type="submit" name="action" value="draft_notification" class="btn btn-outline-secondary btn-sm">Taslağa Al</button>
                                                            <?php endif; ?>
                                                            <?php if (!$isArchived): ?>
                                                                <button type="submit" name="action" value="archive_notification" class="btn btn-outline-warning btn-sm">Arşivle</button>
                                                            <?php endif; ?>
                                                            <button type="submit" name="action" value="delete_notification" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu bildirimi silmek istiyor musunuz?');">Sil</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php foreach ($notifications as $notification): ?>
                                <div class="modal fade" id="notificationPreview<?= (int)$notification['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><?= Helpers::sanitize($notification['title']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-muted small mb-3">
                                                    <span class="me-3">Yayın: <?= Helpers::sanitize($notification['publish_at'] ? date('d.m.Y H:i', strtotime($notification['publish_at'])) : '-') ?></span>
                                                    <?php if ($notification['expire_at']): ?>
                                                        <span>Bitiş: <?= Helpers::sanitize(date('d.m.Y H:i', strtotime($notification['expire_at']))) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p><?= nl2br(Helpers::sanitize($notification['message'])) ?></p>
                                                <?php if ($notification['link']): ?>
                                                    <p class="mb-0"><strong>Bağlantı:</strong> <a href="<?= Helpers::sanitize($notification['link']) ?>" target="_blank" rel="noopener noreferrer"><?= Helpers::sanitize($notification['link']) ?></a></p>
                                                <?php endif; ?>
                                                <?php if ($notification['scope'] === 'user' && $notification['user_email']): ?>
                                                    <p class="mt-3 mb-0"><strong>Hedef Kullanıcı:</strong> <?= Helpers::sanitize($notification['user_email']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

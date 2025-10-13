
<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;
use App\Notification;

Auth::requireRoles(array('super_admin', 'admin', 'support'));
Notification::ensureTables();

$currentUser = $_SESSION['user'];
$successMessage = Helpers::getFlash('notifications_success', '');
$errorMessages = Helpers::getFlash('notifications_errors', array());
if (!is_array($errorMessages)) {
    $errorMessages = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'create';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $errors = array();

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum dogrulamasi basarisiz. Lütfen sayfayi yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';
            $scope = isset($_POST['scope']) && $_POST['scope'] === 'user' ? 'user' : 'global';
            $userEmail = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
            $status = isset($_POST['status']) && in_array($_POST['status'], array('draft', 'published'), true) ? $_POST['status'] : 'published';
            $publishAtInput = isset($_POST['publish_at']) ? trim($_POST['publish_at']) : '';
            $expireAtInput = isset($_POST['expire_at']) ? trim($_POST['expire_at']) : '';

            if ($title === '' || $message === '') {
                $errors[] = 'Baslik ve mesaj alanlari zorunludur.';
            }

            $userId = null;
            if ($scope === 'user') {
                if ($userEmail === '') {
                    $errors[] = 'Bildirim göndermek istediginiz kullanicinin e-posta adresini belirtin.';
                } else {
                    $pdo = Database::connection();
                    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
                    $stmt->execute(array('email' => $userEmail));
                    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$targetUser) {
                        $errors[] = 'Belirtilen e-posta adresi ile eslesen bir kullanıcı bulunamadı.';
                    } else {
                        $userId = (int)$targetUser['id'];
                    }
                }
            }

            $publishAt = $publishAtInput !== '' ? date('Y-m-d H:i:s', strtotime($publishAtInput)) : date('Y-m-d H:i:s');
            $expireAt = $expireAtInput !== '' ? date('Y-m-d H:i:s', strtotime($expireAtInput)) : null;

            if (!$errors) {
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

                AuditLog::record(
                    $currentUser['id'],
                    'notifications.create',
                    'notifications',
                    null,
                    'Yeni bildirim olusturuldu'
                );

                Helpers::redirectWithFlash('/admin/notifications.php', array(
                    'notifications_success' => 'Bildirim basariyla olusturuldu.',
                ));
            } else {
                Helpers::redirectWithFlash('/admin/notifications.php', array(
                    'notifications_errors' => $errors,
                ));
            }
        } elseif (in_array($action, array('publish', 'archive', 'delete'), true)) {
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            if ($notificationId <= 0) {
                $errors[] = 'Gecersiz bildirim secimi.';
            } else {
                if ($action === 'delete') {
                    Notification::delete($notificationId);
                    AuditLog::record(
                        $currentUser['id'],
                        'notifications.delete',
                        'notifications',
                        $notificationId,
                        'Bildirim silindi'
                    );
                    Helpers::redirectWithFlash('/admin/notifications.php', array(
                        'notifications_success' => 'Bildirim silindi.',
                    ));
                } else {
                    $status = $action === 'publish' ? 'published' : 'archived';
                    Notification::setStatus($notificationId, $status);
                    AuditLog::record(
                        $currentUser['id'],
                        'notifications.status',
                        'notifications',
                        $notificationId,
                        'Bildirim durumu "' . $status . '" olarak guncellendi'
                    );
                    Helpers::redirectWithFlash('/admin/notifications.php', array(
                        'notifications_success' => 'Bildirim durumu guncellendi.',
                    ));
                }
            }

            if ($errors) {
                Helpers::redirectWithFlash('/admin/notifications.php', array(
                    'notifications_errors' => $errors,
                ));
            }
        }
    }
}

$notifications = Notification::all();
$pageTitle = 'Bildirimler';
$csrfToken = Helpers::csrfToken();

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Bildirim</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Kullanıcılara anlık bildirim göndermek için mesajinizi olusturun.</p>

                <?php if ($errorMessages): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errorMessages as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($successMessage) ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="form-label">Baslik</label>
                        <input type="text" name="title" class="form-control" placeholder="Bildirim basligi" required>
                    </div>
                    <div>
                        <label class="form-label">Mesaj</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Kisa ve aciklayici bir mesaj yazin" required></textarea>
                    </div>
                    <div>
                        <label class="form-label">Link (opsiyonel)</label>
                        <input type="url" name="link" class="form-control" placeholder="https://example.com/detay">
                    </div>
                    <div>
                        <label class="form-label d-block">Hedef</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope" id="notificationScopeAll" value="global" checked>
                            <label class="form-check-label" for="notificationScopeAll">Tüm kullanicilar</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope" id="notificationScopeUser" value="user">
                            <label class="form-check-label" for="notificationScopeUser">Belirli kullanici</label>
                        </div>
                        <input type="email" name="user_email" class="form-control mt-2" placeholder="Kullanici e-postasi (yalnizca tek kullanici icin)">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <option value="published" selected>Yayinda</option>
                                <option value="draft">Taslak</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Yayin Tarihi</label>
                            <input type="datetime-local" name="publish_at" class="form-control">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Sona erme (opsiyonel)</label>
                        <input type="datetime-local" name="expire_at" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Bildirimi Olustur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Bildirim Gecmisi</h5>
            </div>
            <div class="card-body">
                <?php if (!$notifications): ?>
                    <p class="text-muted mb-0">Henuz olusturulmus bir bildirim bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th>Baslik</th>
                                    <th>Durum</th>
                                    <th>Hedef</th>
                                    <th>Yayin</th>
                                    <th class="text-end">Islemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?= (int)$notification['id'] ?></td>
                                        <td>
                                            <strong><?= Helpers::sanitize($notification['title']) ?></strong><br>
                                            <small class="text-muted"><?= Helpers::sanitize(mb_strimwidth($notification['message'], 0, 120, '...')) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $notification['status'] === 'published' ? 'success' : ($notification['status'] === 'draft' ? 'secondary' : 'dark') ?>">
                                                <?= Helpers::sanitize(ucfirst($notification['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($notification['scope'] === 'user' && !empty($notification['user_email'])): ?>
                                                <span class="badge bg-info text-dark">Kullanici</span><br>
                                                <small><?= Helpers::sanitize($notification['user_email']) ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Genel</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= Helpers::sanitize($notification['publish_at'] ? date('d.m.Y H:i', strtotime($notification['publish_at'])) : '-') ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize($csrfToken) ?>">
                                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                                <?php if ($notification['status'] !== 'published'): ?>
                                                    <button type="submit" name="action" value="publish" class="btn btn-sm btn-outline-success">Yayinla</button>
                                                <?php endif; ?>
                                                <?php if ($notification['status'] !== 'archived'): ?>
                                                    <button type="submit" name="action" value="archive" class="btn btn-sm btn-outline-secondary">Arsivle</button>
                                                <?php endif; ?>
                                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu bildirimi silmek istiyor musunuz?');">Sil</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

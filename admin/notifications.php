<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Notification;

Auth::requireRoles(array('super_admin', 'admin', 'support'));

$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } else {
        try {
            switch ($action) {
                case 'save_notification':
                    $payload = array(
                        'id' => isset($_POST['id']) ? (int)$_POST['id'] : 0,
                        'title' => isset($_POST['title']) ? $_POST['title'] : '',
                        'message' => isset($_POST['message']) ? $_POST['message'] : '',
                        'link' => isset($_POST['link']) ? $_POST['link'] : '',
                        'scope' => isset($_POST['scope']) ? $_POST['scope'] : 'global',
                        'user_id' => isset($_POST['user_id']) ? $_POST['user_id'] : null,
                        'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
                        'publish_at' => isset($_POST['publish_at']) ? $_POST['publish_at'] : '',
                        'expire_at' => isset($_POST['expire_at']) ? $_POST['expire_at'] : '',
                    );
                    $saved = Notification::save($payload);
                    $success = isset($payload['id']) && (int)$payload['id'] > 0 ? 'Bildirim güncellendi.' : 'Yeni bildirim oluşturuldu.';
                    break;

                case 'delete_notification':
                    $deleteId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    if ($deleteId <= 0) {
                        throw new \InvalidArgumentException('Silinecek bildirim bulunamadı.');
                    }
                    Notification::delete($deleteId);
                    $success = 'Bildirim silindi.';
                    break;

                case 'set_status':
                    $targetId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $status = isset($_POST['status']) ? (string)$_POST['status'] : 'draft';
                    if ($targetId <= 0) {
                        throw new \InvalidArgumentException('Bildirim seçilemedi.');
                    }
                    Notification::setStatus($targetId, $status);
                    $success = 'Bildirim durumu güncellendi.';
                    break;

                default:
                    throw new \InvalidArgumentException('Bilinmeyen işlem talep edildi.');
            }
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (\Throwable $exception) {
            $errors[] = 'İşlem gerçekleştirilemedi: ' . $exception->getMessage();
        }
    }
}

$notifications = Notification::all();
$csrfToken = Helpers::csrfToken();
$pageTitle = 'Bildirim Yönetimi';

$statusLabels = array(
    'draft' => 'Taslak',
    'published' => 'Yayında',
    'archived' => 'Arşivlendi',
);
$scopeLabels = array(
    'global' => 'Genel',
    'user' => 'Kullanıcı',
);

$modalScript = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('notificationModal');
    if (!modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var form = modal.querySelector('form');
        if (!form) {
            return;
        }

        var idInput = form.querySelector('[name="id"]');
        var titleInput = form.querySelector('[name="title"]');
        var messageInput = form.querySelector('[name="message"]');
        var linkInput = form.querySelector('[name="link"]');
        var scopeSelect = form.querySelector('[name="scope"]');
        var userField = form.querySelector('[data-scope-user]');
        var userInput = form.querySelector('[name="user_id"]');
        var statusSelect = form.querySelector('[name="status"]');
        var publishInput = form.querySelector('[name="publish_at"]');
        var expireInput = form.querySelector('[name="expire_at"]');

        var resetForm = function () {
            if (idInput) { idInput.value = ''; }
            if (titleInput) { titleInput.value = ''; }
            if (messageInput) { messageInput.value = ''; }
            if (linkInput) { linkInput.value = ''; }
            if (scopeSelect) { scopeSelect.value = 'global'; }
            if (statusSelect) { statusSelect.value = 'draft'; }
            if (publishInput) { publishInput.value = ''; }
            if (expireInput) { expireInput.value = ''; }
            if (userInput) { userInput.value = ''; }
        };

        resetForm();

        if (button && button.getAttribute('data-notification')) {
            try {
                var payload = JSON.parse(button.getAttribute('data-notification'));
                if (payload && typeof payload === 'object') {
                    if (idInput) { idInput.value = payload.id || ''; }
                    if (titleInput) { titleInput.value = payload.title || ''; }
                    if (messageInput) { messageInput.value = payload.message || ''; }
                    if (linkInput) { linkInput.value = payload.link || ''; }
                    if (scopeSelect) { scopeSelect.value = payload.scope || 'global'; }
                    if (statusSelect) { statusSelect.value = payload.status || 'draft'; }
                    if (publishInput) { publishInput.value = payload.publish_at || ''; }
                    if (expireInput) { expireInput.value = payload.expire_at || ''; }
                    if (userInput) {
                        userInput.value = payload.scope === 'user' ? (payload.user_id || '') : '';
                    }
                }
            } catch (error) {
                console.error('Bildirim verisi çözümlenemedi.', error);
            }
        }

        var toggleUserField = function () {
            if (!scopeSelect || !userField) {
                return;
            }

            if (scopeSelect.value === 'user') {
                userField.classList.remove('d-none');
            } else {
                userField.classList.add('d-none');
                if (userInput) {
                    userInput.value = '';
                }
            }
        };

        if (scopeSelect) {
            if (scopeSelect._notificationScopeHandler) {
                scopeSelect.removeEventListener('change', scopeSelect._notificationScopeHandler);
            }
            scopeSelect._notificationScopeHandler = toggleUserField;
            scopeSelect.addEventListener('change', toggleUserField);
        }

        toggleUserField();
    });
});
JS;

$GLOBALS['pageInlineScripts'][] = $modalScript;

require __DIR__ . '/templates/header.php';
?>
<div class="app-content">
    <div class="page-head">
        <h1>Bildirim Yönetimi</h1>
        <p>Kullanıcılarınıza gösterilen bildirimleri oluşturun, zamanlayın ve yönetin.</p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#notificationModal">Yeni Bildirim</button>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= Helpers::sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?= Helpers::sanitize($success) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Başlık</th>
                            <th>Durum</th>
                            <th>Kapsam</th>
                            <th>Yayın</th>
                            <th>Kullanıcı</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$notifications): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Henüz bildirim oluşturulmadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                    $rowStatus = isset($notification['status']) ? $notification['status'] : 'draft';
                                    $rowScope = isset($notification['scope']) ? $notification['scope'] : 'global';
                                    $statusLabel = isset($statusLabels[$rowStatus]) ? $statusLabels[$rowStatus] : ucfirst($rowStatus);
                                    $scopeLabel = isset($scopeLabels[$rowScope]) ? $scopeLabels[$rowScope] : ucfirst($rowScope);
                                    $publishDate = isset($notification['publish_at']) && $notification['publish_at'] ? date('d.m.Y H:i', strtotime($notification['publish_at'])) : '-';
                                    $userInfo = '';
                                    if (!empty($notification['user_name']) || !empty($notification['user_email'])) {
                                        $userInfo = trim((string)$notification['user_name']);
                                        if (!empty($notification['user_email'])) {
                                            $userInfo .= ' (' . $notification['user_email'] . ')';
                                        }
                                    }
                                    $dataPayload = json_encode(array(
                                        'id' => $notification['id'],
                                        'title' => $notification['title'],
                                        'message' => $notification['message'],
                                        'link' => $notification['link'],
                                        'scope' => $rowScope,
                                        'status' => $rowStatus,
                                        'publish_at' => $notification['publish_at'],
                                        'expire_at' => $notification['expire_at'],
                                        'user_id' => $notification['user_id'],
                                    ));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= Helpers::sanitize($notification['title']) ?></strong>
                                        <div class="text-muted small"><?= Helpers::sanitize(mb_strimwidth($notification['message'], 0, 90, '...')) ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= Helpers::sanitize($statusLabel) ?></span></td>
                                    <td><?= Helpers::sanitize($scopeLabel) ?></td>
                                    <td><?= Helpers::sanitize($publishDate) ?></td>
                                    <td><?= Helpers::sanitize($userInfo) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#notificationModal" data-notification='<?= htmlspecialchars($dataPayload, ENT_QUOTES, 'UTF-8') ?>'>Düzenle</button>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="delete_notification">
                                                <input type="hidden" name="id" value="<?= (int)$notification['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu bildirimi silmek istediğinize emin misiniz?');">Sil</button>
                                            </form>
                                        </div>
                                        <div class="btn-group mt-2" role="group">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <input type="hidden" name="id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="status" value="published">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Yayınla</button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <input type="hidden" name="id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="status" value="draft">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Taslak</button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <input type="hidden" name="id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="status" value="archived">
                                                <button type="submit" class="btn btn-sm btn-outline-dark">Arşivle</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_notification">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Bildirim Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Başlık</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bağlantı</label>
                            <input type="text" name="link" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mesaj</label>
                            <textarea name="message" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kapsam</label>
                            <select name="scope" class="form-select">
                                <option value="global">Genel</option>
                                <option value="user">Belirli Kullanıcı</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Durum</label>
                            <select name="status" class="form-select">
                                <option value="draft">Taslak</option>
                                <option value="published">Yayında</option>
                                <option value="archived">Arşiv</option>
                            </select>
                        </div>
                        <div class="col-md-4" data-scope-user class="d-none">
                            <label class="form-label">Kullanıcı ID</label>
                            <input type="number" name="user_id" class="form-control" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Yayınlanma Tarihi</label>
                            <input type="datetime-local" name="publish_at" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="datetime-local" name="expire_at" class="form-control">
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

<?php require __DIR__ . '/templates/footer.php'; ?>

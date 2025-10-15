<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$pdo = Database::connection();
$currentUser = $_SESSION['user'];
$errors = Helpers::getFlash('errors', array());
$success = Helpers::getFlash('success', '');
$currentUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/admin/payment-notifications.php';

$statusOptions = array('pending', 'approved', 'rejected');
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], $statusOptions, true) ? $_GET['status'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectPath = Helpers::normalizeRedirectPath(isset($_POST['redirect']) ? $_POST['redirect'] : '', '/admin/payment-notifications.php');
    $formErrors = array();
    $formSuccess = '';

    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Helpers::verifyCsrf($token)) {
        $formErrors[] = 'Gecersiz istek. Lutfen sayfayi yenileyip tekrar deneyin.';
    } else {
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        $newStatus = isset($_POST['status']) ? strtolower((string)$_POST['status']) : '';
        $adminNote = isset($_POST['admin_note']) ? trim((string)$_POST['admin_note']) : '';

        if ($notificationId <= 0) {
            $formErrors[] = 'Bildirim bulunamadi.';
        } elseif (!in_array($newStatus, array('approved', 'rejected'), true)) {
            $formErrors[] = 'Gecersiz durum secildi.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM bank_transfer_notifications WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(array('id' => $notificationId));
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                $formErrors[] = 'Bildirim kaydi bulunamadi.';
            } elseif ($notification['status'] !== 'pending') {
                $formErrors[] = 'Sadece bekleyen bildirimler guncellenebilir.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $pdo->prepare('UPDATE bank_transfer_notifications SET status = :status, notes = :notes, updated_at = NOW() WHERE id = :id')
                        ->execute(array(
                            'id' => $notificationId,
                            'status' => $newStatus,
                            'notes' => $adminNote !== '' ? $adminNote : $notification['notes'],
                        ));

                    if ($newStatus === 'approved') {
                        $orderReference = isset($notification['order_reference']) ? $notification['order_reference'] : null;
                        if ($orderReference) {
                            $pdo->prepare("UPDATE product_orders SET status = CASE WHEN status = 'pending' THEN 'processing' ELSE status END, updated_at = NOW() WHERE external_reference = :reference")
                                ->execute(array('reference' => $orderReference));
                        }
                    }

                    $pdo->commit();

                    $formSuccess = $newStatus === 'approved' ? 'Bildirim onaylandi.' : 'Bildirim reddedildi.';
                    AuditLog::record(
                        $currentUser['id'],
                        'payments.notification.update',
                        'bank_transfer_notification',
                        $notificationId,
                        sprintf('Banka transfer bildirimi %s olarak isaretlendi', $newStatus)
                    );
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $formErrors[] = 'Bildirim guncellenemedi: ' . $exception->getMessage();
                }
            }
        }
    }

    if ($formErrors) {
        Helpers::redirectWithFlash($redirectPath, array('errors' => $formErrors));
    }

    if ($formSuccess !== '') {
        Helpers::redirectWithFlash($redirectPath, array('success' => $formSuccess));
    }
}

$query = '
    SELECT
        n.*,
        b.bank_name,
        b.account_holder,
        u.name AS user_name,
        u.email AS user_email
    FROM bank_transfer_notifications n
    LEFT JOIN bank_accounts b ON b.id = n.bank_account_id
    LEFT JOIN users u ON u.id = n.user_id
';
$params = array();
if ($statusFilter !== '') {
    $query .= ' WHERE n.status = :status';
    $params['status'] = $statusFilter;
}
$query .= ' ORDER BY FIELD(n.status, \'pending\', \'approved\', \'rejected\'), n.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Banka Transfer Bildirimleri';
include __DIR__ . '/templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-0">Banka Transfer Bildirimleri</h5>
            <small class="text-muted">Musterilerden gelen havale / EFT bildirimlerini yonetin.</small>
        </div>
        <form method="get" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tum Durumlar</option>
                <?php foreach ($statusOptions as $option): ?>
                    <option value="<?= Helpers::sanitize($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= Helpers::sanitize(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
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
            <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
        <?php endif; ?>

        <?php if (!$notifications): ?>
            <p class="text-muted mb-0">Gosterilecek bildirim bulunmuyor.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Tutar</th>
                            <th>Banka</th>
                            <th>Transfer Tarihi</th>
                            <th>Durum</th>
                            <th>Oluşturma</th>
                            <th class="text-end">Islemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notification): ?>
                            <tr>
                                <td><?= (int)$notification['id'] ?></td>
                                <td>
                                    <?php if (!empty($notification['user_name'])): ?>
                                        <strong><?= Helpers::sanitize($notification['user_name']) ?></strong><br>
                                        <small class="text-muted"><?= Helpers::sanitize($notification['user_email']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Misafir</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= Helpers::sanitize(Helpers::formatCurrency((float)$notification['amount'])) ?></strong>
                                    <?php if (!empty($notification['order_reference'])): ?>
                                        <div class="small text-muted">Ref: <?= Helpers::sanitize($notification['order_reference']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($notification['bank_name'])): ?>
                                        <strong><?= Helpers::sanitize($notification['bank_name']) ?></strong><br>
                                        <small class="text-muted"><?= Helpers::sanitize($notification['account_holder']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Belirtilmedi</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= Helpers::sanitize(date('d.m.Y H:i', strtotime($notification['transfer_datetime']))) ?><br>
                                    <small class="text-muted"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($notification['created_at']))) ?></small>
                                </td>
                                <td>
                                    <?php if ($notification['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Beklemede</span>
                                    <?php elseif ($notification['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Onaylandi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Reddedildi</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($notification['created_at']))) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#notificationDetail<?= (int)$notification['id'] ?>">Detay</button>
                                    <?php if ($notification['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#notificationApprove<?= (int)$notification['id'] ?>">Onayla</button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#notificationReject<?= (int)$notification['id'] ?>">Reddet</button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <div class="modal fade" id="notificationDetail<?= (int)$notification['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Bildirim Detayi</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <dl class="row">
                                                <dt class="col-sm-4">Customer</dt>
                                                <dd class="col-sm-8">
                                                    <?php if (!empty($notification['user_name'])): ?>
                                                        <?= Helpers::sanitize($notification['user_name']) ?><br>
                                                        <small class="text-muted"><?= Helpers::sanitize($notification['user_email']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Belirtilmedi</span>
                                                    <?php endif; ?>
                                                </dd>
                                                <dt class="col-sm-4">Tutar</dt>
                                                <dd class="col-sm-8"><?= Helpers::sanitize(Helpers::formatCurrency((float)$notification['amount'])) ?></dd>
                                                <dt class="col-sm-4">Banka</dt>
                                                <dd class="col-sm-8">
                                                    <?php if (!empty($notification['bank_name'])): ?>
                                                        <?= Helpers::sanitize($notification['bank_name']) ?><br>
                                                        <small class="text-muted"><?= Helpers::sanitize($notification['account_holder']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Belirtilmedi</span>
                                                    <?php endif; ?>
                                                </dd>
                                                <dt class="col-sm-4">Transfer Tarihi</dt>
                                                <dd class="col-sm-8"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($notification['transfer_datetime']))) ?></dd>
                                                <dt class="col-sm-4">Durum</dt>
                                                <dd class="col-sm-8"><?= Helpers::sanitize(ucfirst($notification['status'])) ?></dd>
                                                <dt class="col-sm-4">Not</dt>
                                                <dd class="col-sm-8"><?= nl2br(Helpers::sanitize(isset($notification['notes']) ? $notification['notes'] : '-')) ?></dd>
                                                <?php if (!empty($notification['receipt_path'])): ?>
                                                    <dt class="col-sm-4">Dekont</dt>
                                                    <dd class="col-sm-8"><a href="<?= Helpers::sanitize($notification['receipt_path']) ?>" target="_blank" rel="noopener">Dekontu Goruntule</a></dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="notificationApprove<?= (int)$notification['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Bildirimi Onayla</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="redirect" value="<?= Helpers::sanitize($currentUrl) ?>">
                                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <p class="mb-3">Bu bildirimi onaylamak istiyor musunuz? Baglantili siparisler <strong>processing</strong> durumuna cekilecektir.</p>
                                                <div class="mb-3">
                                                    <label class="form-label">Yonetici Notu (opsiyonel)</label>
                                                    <textarea name="admin_note" class="form-control" rows="3" placeholder="Onay notu ekleyin"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Iptal</button>
                                                <button type="submit" class="btn btn-success">Onayla</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="notificationReject<?= (int)$notification['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Bildirimi Reddet</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="redirect" value="<?= Helpers::sanitize($currentUrl) ?>">
                                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                <p class="mb-3">Bu bildirimi reddetmek istiyor musunuz? Baglantili siparisler degistirilmeyecektir.</p>
                                                <div class="mb-3">
                                                    <label class="form-label">Yonetici Notu</label>
                                                    <textarea name="admin_note" class="form-control" rows="3" placeholder="Reddetme sebebini belirtin" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Iptal</button>
                                                <button type="submit" class="btn btn-danger">Reddet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Database;
use App\Helpers;
use App\CouponService;
use App\AuditLog;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
CouponService::ensureSchema();
$errors = array();
$success = '';

function normalizeDateInput(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $formats = array('Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d');
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $trimmed);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
}

function datetimeForInput(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('Y-m-d\TH:i');
    } catch (\Throwable $exception) {
        return '';
    }
}

function couponLabel(array $coupon): string
{
    if (($coupon['discount_type'] ?? '') === 'percent') {
        $value = isset($coupon['discount_value']) ? (float)$coupon['discount_value'] : 0.0;
        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted . '%';
    }

    $currency = isset($coupon['currency']) && $coupon['currency'] !== '' ? $coupon['currency'] : 'TRY';
    $amount = isset($coupon['discount_value']) ? (float)$coupon['discount_value'] : 0.0;
    return Helpers::formatCurrency($amount, $currency);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

    if (in_array($action, array('create', 'update'), true)) {
        $couponId = $action === 'update' ? (int)($_POST['coupon_id'] ?? 0) : null;
        if ($action === 'update' && $couponId <= 0) {
            $errors[] = 'Düzenlenecek kupon bulunamadı.';
        }

        $code = isset($_POST['code']) ? strtoupper(trim((string)$_POST['code'])) : '';
        if ($code === '' || !preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/', $code)) {
            $errors[] = 'Kupon kodu en az 2, en fazla 50 karakter olmalı ve yalnızca harf, rakam, tire veya alt çizgi içermelidir.';
        }

        $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
        $discountType = isset($_POST['discount_type']) && $_POST['discount_type'] === 'percent' ? 'percent' : 'fixed';
        $discountValue = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0.0;

        if ($discountType === 'percent') {
            if ($discountValue <= 0 || $discountValue > 100) {
                $errors[] = 'Yüzde indirimi 0 ile 100 arasında olmalıdır.';
            }
        } else {
            if ($discountValue <= 0) {
                $errors[] = 'Sabit indirim tutarı sıfırdan büyük olmalıdır.';
            }
        }

        $currency = isset($_POST['currency']) ? strtoupper(trim((string)$_POST['currency'])) : 'TRY';
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = 'Para birimi üç harften oluşmalıdır.';
        }

        $minOrderAmount = isset($_POST['min_order_amount']) ? max(0.0, (float)$_POST['min_order_amount']) : 0.0;
        $maxUses = isset($_POST['max_uses']) && $_POST['max_uses'] !== '' ? max(0, (int)$_POST['max_uses']) : null;
        if ($maxUses !== null && $maxUses <= 0) {
            $maxUses = null;
        }

        $usagePerUser = isset($_POST['usage_per_user']) && $_POST['usage_per_user'] !== '' ? max(0, (int)$_POST['usage_per_user']) : null;
        if ($usagePerUser !== null && $usagePerUser <= 0) {
            $usagePerUser = null;
        }

        $startsAt = normalizeDateInput($_POST['starts_at'] ?? null);
        $expiresAt = normalizeDateInput($_POST['expires_at'] ?? null);
        if ($startsAt && $expiresAt) {
            try {
                $startDate = new DateTimeImmutable($startsAt);
                $endDate = new DateTimeImmutable($expiresAt);
                if ($endDate < $startDate) {
                    $errors[] = 'Bitiş tarihi başlangıç tarihinden önce olamaz.';
                }
            } catch (\Throwable $exception) {
                $errors[] = 'Geçerli bir başlangıç/bitiş tarihi girin.';
            }
        }

        $status = isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (!$errors) {
            $params = array(
                'code' => $code,
                'description' => $description,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'currency' => $currency,
                'min_order_amount' => $minOrderAmount,
                'max_uses' => $maxUses,
                'usage_per_user' => $usagePerUser,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => $status,
            );

            if ($action === 'create') {
                $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM coupons WHERE code = :code');
                $existsStmt->execute(array('code' => $code));
                if ((int)$existsStmt->fetchColumn() > 0) {
                    $errors[] = 'Bu kupon kodu zaten kullanılıyor.';
                }

                if (!$errors) {
                    $insert = $pdo->prepare('INSERT INTO coupons (code, description, discount_type, discount_value, currency, min_order_amount, max_uses, usage_per_user, starts_at, expires_at, status, created_at) VALUES (:code, :description, :discount_type, :discount_value, :currency, :min_order_amount, :max_uses, :usage_per_user, :starts_at, :expires_at, :status, NOW())');
                    $insert->execute(array(
                        'code' => $params['code'],
                        'description' => $params['description'],
                        'discount_type' => $params['discount_type'],
                        'discount_value' => $params['discount_value'],
                        'currency' => $params['currency'],
                        'min_order_amount' => $params['min_order_amount'],
                        'max_uses' => $params['max_uses'],
                        'usage_per_user' => $params['usage_per_user'],
                        'starts_at' => $params['starts_at'],
                        'expires_at' => $params['expires_at'],
                        'status' => $params['status'],
                    ));
                    $newId = (int)$pdo->lastInsertId();
                    $success = 'Kupon oluşturuldu.';
                    AuditLog::record($currentUser['id'], 'coupon.create', 'coupon', $newId, sprintf('Kupon oluşturuldu: %s', $code));
                }
            } else {
                $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM coupons WHERE code = :code AND id != :id');
                $existsStmt->execute(array('code' => $code, 'id' => $couponId));
                if ((int)$existsStmt->fetchColumn() > 0) {
                    $errors[] = 'Bu kupon kodu zaten kullanılıyor.';
                }

                if (!$errors) {
                    $update = $pdo->prepare('UPDATE coupons SET code = :code, description = :description, discount_type = :discount_type, discount_value = :discount_value, currency = :currency, min_order_amount = :min_order_amount, max_uses = :max_uses, usage_per_user = :usage_per_user, starts_at = :starts_at, expires_at = :expires_at, status = :status WHERE id = :id');
                    $update->execute(array(
                        'code' => $params['code'],
                        'description' => $params['description'],
                        'discount_type' => $params['discount_type'],
                        'discount_value' => $params['discount_value'],
                        'currency' => $params['currency'],
                        'min_order_amount' => $params['min_order_amount'],
                        'max_uses' => $params['max_uses'],
                        'usage_per_user' => $params['usage_per_user'],
                        'starts_at' => $params['starts_at'],
                        'expires_at' => $params['expires_at'],
                        'status' => $params['status'],
                        'id' => $couponId,
                    ));
                    $success = 'Kupon güncellendi.';
                    AuditLog::record($currentUser['id'], 'coupon.update', 'coupon', $couponId, sprintf('Kupon güncellendi: %s', $code));
                }
            }
        }
    } elseif ($action === 'delete') {
        $couponId = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        if ($couponId <= 0) {
            $errors[] = 'Silinecek kupon bulunamadı.';
        } else {
            $lookup = $pdo->prepare('SELECT code FROM coupons WHERE id = :id');
            $lookup->execute(array('id' => $couponId));
            $row = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $errors[] = 'Kupon bulunamadı.';
            } else {
                $pdo->prepare('DELETE FROM coupons WHERE id = :id')->execute(array('id' => $couponId));
                $success = 'Kupon silindi.';
                AuditLog::record($currentUser['id'], 'coupon.delete', 'coupon', $couponId, sprintf('Kupon silindi: %s', $row['code']));
            }
        }
    }
}

$coupons = array();
$couponStmt = $pdo->query('SELECT c.*, COUNT(u.id) AS usage_count, COUNT(DISTINCT u.user_id) AS usage_users, MAX(u.used_at) AS last_used_at FROM coupons c LEFT JOIN coupon_usages u ON u.coupon_id = c.id GROUP BY c.id ORDER BY c.created_at DESC');
if ($couponStmt instanceof \PDOStatement) {
    while ($row = $couponStmt->fetch(PDO::FETCH_ASSOC)) {
        $row['usage_count'] = (int)$row['usage_count'];
        $row['usage_users'] = (int)$row['usage_users'];
        $row['min_order_amount'] = isset($row['min_order_amount']) ? (float)$row['min_order_amount'] : 0.0;
        $row['discount_value'] = isset($row['discount_value']) ? (float)$row['discount_value'] : 0.0;
        $row['max_uses'] = isset($row['max_uses']) ? ($row['max_uses'] !== null ? (int)$row['max_uses'] : null) : null;
        $row['usage_per_user'] = isset($row['usage_per_user']) ? ($row['usage_per_user'] !== null ? (int)$row['usage_per_user'] : null) : null;
        $row['last_used_at_human'] = $row['last_used_at'] ? date('d.m.Y H:i', strtotime($row['last_used_at'])) : null;
        $coupons[] = $row;
    }
}

$pageTitle = 'Kupon Yönetimi';
include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kupon Oluştur</h5>
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
                    <div class="alert alert-success mb-3"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Kupon Kodu</label>
                        <input type="text" name="code" class="form-control" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Opsiyonel"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">İndirim Tipi</label>
                            <select name="discount_type" class="form-select">
                                <option value="fixed">Sabit</option>
                                <option value="percent">Yüzde</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">İndirim Değeri</label>
                            <input type="number" name="discount_value" step="0.01" min="0" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Para Birimi</label>
                            <input type="text" name="currency" class="form-control" value="TRY" maxlength="3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Sepet</label>
                            <input type="number" name="min_order_amount" step="0.01" min="0" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="row g-2 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Toplam Kullanım</label>
                            <input type="number" name="max_uses" min="0" class="form-control" placeholder="Sınırsız">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kişi Başına</label>
                            <input type="number" name="usage_per_user" min="0" class="form-control" placeholder="Sınırsız">
                        </div>
                    </div>
                    <div class="row g-2 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Başlangıç</label>
                            <input type="datetime-local" name="starts_at" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bitiş</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Durum</label>
                        <select name="status" class="form-select">
                            <option value="active">Aktif</option>
                            <option value="inactive">Pasif</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kuponu Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kuponlar</h5>
            </div>
            <div class="card-body">
                <?php if (!$coupons): ?>
                    <p class="text-muted mb-0">Henüz tanımlanmış kupon bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Kod</th>
                                    <th>İndirim</th>
                                    <th>Geçerlilik</th>
                                    <th>Kullanım</th>
                                    <th>Durum</th>
                                    <th class="text-end">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $couponModals = array(); ?>
                                <?php foreach ($coupons as $coupon): ?>
                                    <?php
                                        $label = couponLabel($coupon);
                                        $validity = array();
                                        if (!empty($coupon['starts_at'])) {
                                            $validity[] = 'Başlangıç: ' . Helpers::sanitize(date('d.m.Y H:i', strtotime($coupon['starts_at'])));
                                        }
                                        if (!empty($coupon['expires_at'])) {
                                            $validity[] = 'Bitiş: ' . Helpers::sanitize(date('d.m.Y H:i', strtotime($coupon['expires_at'])));
                                        }
                                        if (!$validity) {
                                            $validity[] = 'Süresiz';
                                        }
                                        $limitInfo = $coupon['max_uses'] ? ($coupon['usage_count'] . ' / ' . $coupon['max_uses']) : ($coupon['usage_count'] . ' kullanım');
                                    ?>
                                    <?php
                                        ob_start();
                                    ?>
                                    <div class="modal fade" id="couponModal<?= (int)$coupon['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Kuponu Düzenle</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="coupon_id" value="<?= (int)$coupon['id'] ?>">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Kupon Kodu</label>
                                                                <input type="text" name="code" class="form-control" value="<?= Helpers::sanitize($coupon['code']) ?>" required maxlength="50">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Durum</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="active" <?= $coupon['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                                                    <option value="inactive" <?= $coupon['status'] === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Açıklama</label>
                                                                <textarea name="description" class="form-control" rows="2"><?= Helpers::sanitize($coupon['description']) ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">İndirim Tipi</label>
                                                                <select name="discount_type" class="form-select">
                                                                    <option value="fixed" <?= $coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>>Sabit</option>
                                                                    <option value="percent" <?= $coupon['discount_type'] === 'percent' ? 'selected' : '' ?>>Yüzde</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">İndirim Değeri</label>
                                                                <input type="number" name="discount_value" step="0.01" min="0" class="form-control" value="<?= htmlspecialchars((string)$coupon['discount_value'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Para Birimi</label>
                                                                <input type="text" name="currency" class="form-control" value="<?= Helpers::sanitize($coupon['currency']) ?>" maxlength="3">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Minimum Sepet</label>
                                                                <input type="number" name="min_order_amount" step="0.01" min="0" class="form-control" value="<?= htmlspecialchars((string)$coupon['min_order_amount'], ENT_QUOTES, 'UTF-8') ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Kişi Başına</label>
                                                                <input type="number" name="usage_per_user" min="0" class="form-control" value="<?= $coupon['usage_per_user'] !== null ? (int)$coupon['usage_per_user'] : '' ?>" placeholder="Sınırsız">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Toplam Kullanım</label>
                                                                <input type="number" name="max_uses" min="0" class="form-control" value="<?= $coupon['max_uses'] !== null ? (int)$coupon['max_uses'] : '' ?>" placeholder="Sınırsız">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Başlangıç</label>
                                                                <input type="datetime-local" name="starts_at" class="form-control" value="<?= datetimeForInput($coupon['starts_at'] ?? null) ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Bitiş</label>
                                                                <input type="datetime-local" name="expires_at" class="form-control" value="<?= datetimeForInput($coupon['expires_at'] ?? null) ?>">
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
                                    <?php
                                        $couponModals[] = ob_get_clean();
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= Helpers::sanitize($coupon['code']) ?></strong>
                                            <?php if (!empty($coupon['description'])): ?>
                                                <div class="text-muted small"><?= Helpers::sanitize($coupon['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= Helpers::sanitize($label) ?></div>
                                            <?php if ($coupon['min_order_amount'] > 0): ?>
                                                <div class="text-muted small">Min: <?= Helpers::formatCurrency($coupon['min_order_amount'], $coupon['currency']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php foreach ($validity as $line): ?>
                                                <div class="small text-muted"><?= $line ?></div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <div><?= Helpers::sanitize($limitInfo) ?></div>
                                            <?php if ($coupon['usage_per_user']): ?>
                                                <div class="text-muted small">Kişi başı: <?= (int)$coupon['usage_per_user'] ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($coupon['last_used_at_human'])): ?>
                                                <div class="text-muted small">Son: <?= Helpers::sanitize($coupon['last_used_at_human']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $coupon['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= $coupon['status'] === 'active' ? 'Aktif' : 'Pasif' ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#couponModal<?= (int)$coupon['id'] ?>">Düzenle</button>
                                                <form method="post" onsubmit="return confirm('Kuponu silmek istediğinize emin misiniz?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="coupon_id" value="<?= (int)$coupon['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger">Sil</button>
                                                </form>
                                            </div>
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
<?php if (!empty($couponModals)): ?>
    <?php foreach ($couponModals as $modalMarkup): ?>
        <?= $modalMarkup ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php include __DIR__ . '/templates/footer.php';

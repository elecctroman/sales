<?php
require __DIR__ . '/../bootstrap.php';

use App\AuditLog;
use App\Helpers;
use App\Database;
use App\Auth;
use App\Mailer;

Auth::requireRoles(array('super_admin', 'admin'));

$currentUser = $_SESSION['user'];
$pdo = Database::connection();
$errors = array();
$success = '';
$assignableRoles = Auth::assignableRoles($currentUser);
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';

if ($roleFilter && !in_array($roleFilter, Auth::roles(), true)) {
    $roleFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $balance = isset($_POST['balance']) ? (float)$_POST['balance'] : 0;
        $role = isset($_POST['role']) ? $_POST['role'] : 'support';

        if (!$name || !$email || !$password) {
            $errors[] = 'Name, email and password are required.';
        }

        if (!in_array($role, $assignableRoles, true)) {
            $errors[] = 'You are not allowed to assign the selected role.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'This email address is already registered.';
            }
        }

        if (!$errors) {
            $userId = Auth::createUser($name, $email, $password, $role, $balance);

            if ($balance > 0) {
                $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                    'user_id' => $userId,
                    'amount' => $balance,
                    'type' => 'credit',
                    'description' => 'Initial credit',
                ]);
            }

            Mailer::send($email, 'Your customer account is ready', "Hello $name,\n\nWe created a customer account for you.\nUsername: $email\nPassword: $password\n\nSign in to the dashboard to get started immediately.");
            $success = 'Customer account created and notification email sent.';

            AuditLog::record(
                $currentUser['id'],
                'user.create',
                'user',
                $userId,
                sprintf('Yeni kullanÄ±cÄ±: %s (%s)', $email, $role)
            );
        }
    } elseif ($action === 'update') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $status = isset($_POST['status']) ? (string)$_POST['status'] : 'active';
        $newRole = isset($_POST['role']) ? (string)$_POST['role'] : 'customer';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $balanceAmount = isset($_POST['balance_amount']) ? (float)$_POST['balance_amount'] : 0.0;
        $balanceDirection = isset($_POST['balance_direction']) && $_POST['balance_direction'] === 'debit' ? 'debit' : 'credit';
        $balanceNote = isset($_POST['balance_note']) ? trim((string)$_POST['balance_note']) : '';

        $target = Auth::findUser($userId);
        if (!$target) {
            $errors[] = 'User not found.';
        }

        if (!$name) {
            $errors[] = 'Name is required.';
        }

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (!in_array($status, array('active', 'inactive'), true)) {
            $errors[] = 'Invalid status selected.';
        }

        $allRoles = Auth::roles();
        if (!in_array($newRole, $allRoles, true)) {
            $errors[] = 'Invalid role selected.';
        }

        if ($target) {
            if ($target['role'] === 'super_admin' && $currentUser['role'] !== 'super_admin') {
                $errors[] = 'Only super administrators can update another super administrator.';
            }

            if ($newRole !== $target['role'] && !in_array($newRole, $assignableRoles, true)) {
                $errors[] = 'You are not allowed to assign this role.';
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }

        if ($target) {
            $emailCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :id');
            $emailCheck->execute(array('email' => $email, 'id' => $userId));
            if ((int)$emailCheck->fetchColumn() > 0) {
                $errors[] = 'This email is already in use by another account.';
            }
        }

        $balanceAmount = $balanceAmount > 0 ? $balanceAmount : 0.0;

        if (!$errors && $target) {
            $changes = array();

            try {
                $pdo->beginTransaction();

                $pdo->prepare('UPDATE users SET name = :name, email = :email, status = :status, role = :role WHERE id = :id')->execute(array(
                    'name' => $name,
                    'email' => $email,
                    'status' => $status,
                    'role' => $newRole,
                    'id' => $userId,
                ));

                if ($target['name'] !== $name) {
                    $changes[] = 'ad güncellendi';
                }
                if ($target['email'] !== $email) {
                    $changes[] = 'email güncellendi';
                }
                if ($target['status'] !== $status) {
                    $changes[] = 'durum ' . $status;
                }
                if ($target['role'] !== $newRole) {
                    $changes[] = 'rol ' . $newRole;
                }

                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(array(
                        'hash' => password_hash($password, PASSWORD_BCRYPT),
                        'id' => $userId,
                    ));
                    $changes[] = 'şifre sıfırlandı';
                }

                if ($balanceAmount > 0) {
                    $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute(array(
                        'user_id' => $userId,
                        'amount' => $balanceAmount,
                        'type' => $balanceDirection,
                        'description' => $balanceNote !== '' ? $balanceNote : 'Manual adjustment',
                    ));

                    if ($balanceDirection === 'credit') {
                        $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')->execute(array(
                            'amount' => $balanceAmount,
                            'id' => $userId,
                        ));
                        $changes[] = sprintf('bakiye +%0.2f', $balanceAmount);
                    } else {
                        $pdo->prepare('UPDATE users SET balance = GREATEST(balance - :amount, 0) WHERE id = :id')->execute(array(
                            'amount' => $balanceAmount,
                            'id' => $userId,
                        ));
                        $changes[] = sprintf('bakiye -%0.2f', $balanceAmount);
                    }
                }

                $pdo->commit();

                if ($userId === $currentUser['id']) {
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['status'] = $status;
                    $_SESSION['user']['role'] = $newRole;
                }

                $success = 'Kullanıcı başarıyla güncellendi.';

                if (!$changes) {
                    $changes[] = 'profil güncellendi';
                }

                AuditLog::record(
                    $currentUser['id'],
                    'user.update',
                    'user',
                    $userId,
                    implode(', ', $changes)
                );
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Kullanıcı güncellenemedi: ' . $exception->getMessage();
            }
        }
    }
}

$userQuery = 'SELECT * FROM users';
$conditions = array();
$params = array();

if ($roleFilter) {
    $conditions[] = 'role = :role';
    $params['role'] = $roleFilter;
}

if ($currentUser['role'] !== 'super_admin') {
    $conditions[] = "role != 'super_admin'";
}

if ($conditions) {
    $userQuery .= ' WHERE ' . implode(' AND ', $conditions);
}

$userQuery .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($userQuery);
$stmt->execute($params);
$users = $stmt->fetchAll();
$pageTitle = 'Customer Management';
include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Create Customer</h5>
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
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <?php foreach ($assignableRoles as $roleOption): ?>
                                <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= ((isset($_POST['role']) ? $_POST['role'] : 'support') === $roleOption) ? 'selected' : '' ?>><?= Helpers::sanitize(Auth::roleLabel($roleOption)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opening balance</label>
                        <input type="number" step="0.01" class="form-control" name="balance" value="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create Customer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Customers</h5>
                <form method="get" class="d-flex align-items-center gap-2">
                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All roles</option>
                        <?php foreach (Auth::roles() as $roleOption): ?>
                            <?php if ($currentUser['role'] !== 'super_admin' && $roleOption === 'super_admin') { continue; } ?>
                            <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= $roleFilter === $roleOption ? 'selected' : '' ?>><?= Helpers::sanitize(Auth::roleLabel($roleOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Balance</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                                $roleChoices = array();
                                foreach (Auth::roles() as $roleOption) {
                                    if ($roleOption === $user['role'] || in_array($roleOption, $assignableRoles, true)) {
                                        $roleChoices[] = $roleOption;
                                    }
                                }
                                if (!in_array($user['role'], $roleChoices, true)) {
                                    $roleChoices[] = $user['role'];
                                }
                                $roleChoices = array_values(array_unique($roleChoices));
                                $createdAt = date('d.m.Y H:i', strtotime($user['created_at']));
                                $updatedAt = !empty($user['updated_at']) ? date('d.m.Y H:i', strtotime($user['updated_at'])) : '-';
                                $rolePayload = array();
                                foreach ($roleChoices as $roleOption) {
                                    $rolePayload[] = array(
                                        'value' => $roleOption,
                                        'label' => Auth::roleLabel($roleOption),
                                    );
                                }
                                $modalPayload = array(
                                    'id' => (int)$user['id'],
                                    'name' => (string)$user['name'],
                                    'email' => (string)$user['email'],
                                    'status' => (string)$user['status'],
                                    'status_label' => $user['status'] === 'active' ? 'Aktif' : 'Pasif',
                                    'role' => (string)$user['role'],
                                    'role_label' => Auth::roleLabel($user['role']),
                                    'roles' => $rolePayload,
                                    'balance_formatted' => Helpers::formatCurrency((float)$user['balance']),
                                    'created_at' => $createdAt,
                                    'updated_at' => $updatedAt,
                                );
                                $modalJson = json_encode($modalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                if ($modalJson === false) {
                                    $modalJson = '{}';
                                }
                                $modalData = htmlspecialchars($modalJson, ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= Helpers::sanitize($user['name']) ?></div>
                                    <div class="text-muted small">ID: <?= (int)$user['id'] ?></div>
                                </td>
                                <td><?= Helpers::sanitize($user['email']) ?></td>
                                <td><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></td>
                                <td>
                                    <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= Helpers::sanitize($user['status'] === 'active' ? 'Active' : 'Inactive') ?>
                                    </span>
                                </td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$user['balance'])) ?></td>
                                <td><?= Helpers::sanitize($createdAt) ?></td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm edit-user-trigger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-user="<?= $modalData ?>"
                                    >Düzenle</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true" aria-labelledby="editUserModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" id="editUserForm">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="editUserModalLabel">Kullanıcıyı Düzenle</h5>
                        <span class="d-block text-muted small" id="editUserMeta"></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="editUserId" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="editUserName">Ad Soyad</label>
                            <input type="text" name="name" id="editUserName" class="form-control" value="" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserEmail">E-posta</label>
                            <input type="email" name="email" id="editUserEmail" class="form-control" value="" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserStatus">Durum</label>
                            <select name="status" id="editUserStatus" class="form-select">
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserRole">Rol</label>
                            <select name="role" id="editUserRole" class="form-select" disabled>
                                <option value="">Rol yükleniyor...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserPassword">Yeni Şifre</label>
                            <input type="password" name="password" id="editUserPassword" class="form-control" placeholder="Opsiyonel">
                            <small class="text-muted">Boş bırakırsanız değişmez.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserCurrentBalance">Aktif Bakiye</label>
                            <input type="text" class="form-control" id="editUserCurrentBalance" value="" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="editUserBalanceDirection">Bakiye İşlemi</label>
                            <select name="balance_direction" id="editUserBalanceDirection" class="form-select">
                                <option value="credit">Bakiye Ekle</option>
                                <option value="debit">Bakiye Çıkar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="editUserBalanceAmount">Tutar</label>
                            <input type="number" step="0.01" name="balance_amount" id="editUserBalanceAmount" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="editUserBalanceNote">Not</label>
                            <input type="text" name="balance_note" id="editUserBalanceNote" class="form-control" placeholder="Opsiyonel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserCreatedAt">Oluşturma Tarihi</label>
                            <input type="text" class="form-control" id="editUserCreatedAt" value="" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="editUserUpdatedAt">Son Güncelleme</label>
                            <input type="text" class="form-control" id="editUserUpdatedAt" value="" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

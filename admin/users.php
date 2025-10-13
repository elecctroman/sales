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
    } elseif ($action === 'balance') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : 'credit';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        $user = Auth::findUser($userId);
        if (!$user) {
            $errors[] = 'Customer not found.';
        } elseif ($amount <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        } else {
            $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute([
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type,
                'description' => $description ?: 'Balance adjustment',
            ]);

            if ($type === 'credit') {
                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')->execute([
                    'amount' => $amount,
                    'id' => $userId,
                ]);
            } else {
                $pdo->prepare('UPDATE users SET balance = GREATEST(balance - :amount, 0) WHERE id = :id')->execute([
                    'amount' => $amount,
                    'id' => $userId,
                ]);
            }

            $success = 'Balance updated successfully.';

            AuditLog::record(
                $currentUser['id'],
                'user.balance_adjust',
                'user',
                $userId,
                sprintf('Balance %s: %0.2f (%s)', $type, $amount, $description ?: 'Balance adjustment')
            );
        }
    } elseif ($action === 'status') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Invalid status selected.';
        } else {
            $pdo->prepare('UPDATE users SET status = :status WHERE id = :id')->execute([
                'status' => $status,
                'id' => $userId,
            ]);
            $success = 'Customer status updated.';

            AuditLog::record(
                $currentUser['id'],
                'user.status_change',
                'user',
                $userId,
                sprintf('Status changed to %s', $status)
            );
        }
    } elseif ($action === 'role') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $newrole = isset($_POST['role']) ? $_POST['role'] : '';

        if (!in_array($newrole, Auth::roles(), true)) {
            $errors[] = 'Invalid role selected.';
        } elseif (!in_array($newrole, $assignableRoles, true)) {
            $errors[] = 'You are not allowed to assign this role.';
        } else {
            $target = Auth::findUser($userId);
            if (!$target) {
                $errors[] = 'User not found.';
            } elseif ($target['role'] === 'super_admin' && $currentUser['role'] !== 'super_admin') {
                $errors[] = 'Only super administrators can update another super administrator.';
            } else {
                $pdo->prepare('UPDATE users SET role = :role WHERE id = :id')->execute([
                    'role' => $newrole,
                    'id' => $userId,
                ]);

                if ($userId === $currentUser['id']) {
                    $_SESSION['user']['role'] = $newrole;
                }

                $success = 'User role updated.';

                AuditLog::record(
                    $currentUser['id'],
                    'user.role_change',
                    'user',
                    $userId,
                    sprintf('role changed to %s', $newrole)
                );
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
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= Helpers::sanitize($user['name']) ?></div>
                                    <div class="text-muted small"><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></div>
                                </td>
                                <td><?= Helpers::sanitize($user['email']) ?></td>
                                <td><?= Helpers::sanitize(Auth::roleLabel($user['role'])) ?></td>
                                <td>
                                    <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= Helpers::sanitize($user['status'] === 'active' ? 'Active' : 'Inactive') ?>
                                    </span>
                                </td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$user['balance'])) ?></td>
                                <td><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($user['created_at']))) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#balanceModal<?= (int)$user['id'] ?>">Balance</button>
                                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$user['id'] ?>">Status</button>
                                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#roleModal<?= (int)$user['id'] ?>">Role</button>
                                    </div>
                                </td>
                            </tr>
                            <div class="modal fade" id="balanceModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Adjust balance</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="balance">
                                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Transaction type</label>
                                                    <select name="type" class="form-select">
                                                        <option value="credit">Credit</option>
                                                        <option value="debit">Debit</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Amount</label>
                                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="2" placeholder="Optional"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="statusModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Update status</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="roleModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Update role</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="role">
                                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Role</label>
                                                    <select name="role" class="form-select">
                                                        <?php foreach ($assignableRoles as $roleOption): ?>
                                                            <option value="<?= htmlspecialchars($roleOption, ENT_QUOTES, 'UTF-8') ?>" <?= $user['role'] === $roleOption ? 'selected' : '' ?>><?= Helpers::sanitize(Auth::roleLabel($roleOption)) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/templates/footer.php';

<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\Database;
use App\Helpers;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$pdo = Database::connection();
$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$errors = array();
$success = '';

/**
 * @param string|null $value
 * @return string|null
 */
function normalize_optional_text(?string $value, int $maxLength): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    if (mb_strlen($trimmed) > $maxLength) {
        return null;
    }

    return $trimmed;
}

/**
 * @param string|null $value
 * @return array{value:?string,error:bool,message:?string}
 */
function normalize_icon_input(?string $value): array
{
    if ($value === null) {
        return array('value' => null, 'error' => false, 'message' => null);
    }

    $trimmedOriginal = trim($value);
    if ($trimmedOriginal === '') {
        return array('value' => null, 'error' => false, 'message' => null);
    }

    $normalized = normalize_optional_text($value, 150);
    if ($normalized === null) {
        return array(
            'value' => null,
            'error' => true,
            'message' => 'Icon alanı 150 karakterden uzun olamaz.',
        );
    }

    $normalized = preg_replace('/\s+/u', ' ', $normalized);
    $pattern = '/^(iconify:[A-Za-z0-9:_-]+|[A-Za-z0-9:_-]+(?:\s+[A-Za-z0-9:_-]+)*)$/u';

    if (!preg_match($pattern, $normalized)) {
        return array(
            'value' => null,
            'error' => true,
            'message' => 'Icon alanında yalnızca harf, rakam, : , - ve _ karakterleri kullanılabilir.',
        );
    }

    return array('value' => $normalized, 'error' => false, 'message' => null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if ($action === 'create_category') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            $description = normalize_optional_text(isset($_POST['description']) ? $_POST['description'] : null, 5000);
            $iconValue = isset($_POST['icon']) ? $_POST['icon'] : null;
            $imageValue = isset($_POST['image']) ? $_POST['image'] : null;

            $iconResult = normalize_icon_input($iconValue);
            $icon = $iconResult['value'];
            $image = normalize_optional_text($imageValue, 255);

            if ($name === '') {
                $errors[] = 'Kategori adı zorunludur.';
            }

            if ($iconResult['error']) {
                $errors[] = $iconResult['message'];
            }

            if ($imageValue !== null && $image === null && trim($imageValue) !== '') {
                $errors[] = 'Görsel adresi 255 karakterden uzun olamaz.';
            }

            if ($parentId) {
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
                $stmt->execute(array('id' => $parentId));
                if (!$stmt->fetchColumn()) {
                    $errors[] = 'Belirtilen üst kategori bulunamadı.';
                }
            }

            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO categories (name, parent_id, icon, image, description, created_at) VALUES (:name, :parent_id, :icon, :image, :description, NOW())');
                $stmt->execute(array(
                    'name' => $name,
                    'parent_id' => $parentId,
                    'icon' => $icon,
                    'image' => $image,
                    'description' => $description,
                ));

                $success = 'Kategori oluşturuldu.';

                AuditLog::record(
                    $currentUser['id'],
                    'product_category.create',
                    'category',
                    (int)$pdo->lastInsertId(),
                    sprintf('Kategori oluşturuldu: %s', $name)
                );
            }
        } elseif ($action === 'update_category') {
            $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            $description = normalize_optional_text(isset($_POST['description']) ? $_POST['description'] : null, 5000);
            $iconValue = isset($_POST['icon']) ? $_POST['icon'] : null;
            $imageValue = isset($_POST['image']) ? $_POST['image'] : null;

            $iconResult = normalize_icon_input($iconValue);
            $icon = $iconResult['value'];
            $image = normalize_optional_text($imageValue, 255);

            if ($categoryId <= 0) {
                $errors[] = 'Geçersiz kategori seçimi.';
            }

            if ($name === '') {
                $errors[] = 'Kategori adı zorunludur.';
            }

            if ($iconResult['error']) {
                $errors[] = $iconResult['message'];
            }

            if ($imageValue !== null && $image === null && trim($imageValue) !== '') {
                $errors[] = 'Görsel adresi 255 karakterden uzun olamaz.';
            }

            if ($parentId && $parentId === $categoryId) {
                $errors[] = 'Bir kategori kendi altına taşınamaz.';
            }

            if (!$errors && $parentId) {
                $loopGuard = 0;
                $checkId = $parentId;
                $stmt = $pdo->prepare('SELECT parent_id FROM categories WHERE id = :id LIMIT 1');

                while ($checkId && $loopGuard < 20) {
                    if ($checkId === $categoryId) {
                        $errors[] = 'Bir kategori kendi alt kategorisine taşınamaz.';
                        break;
                    }

                    $stmt->execute(array('id' => $checkId));
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        break;
                    }

                    $checkId = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
                    $loopGuard++;
                }

                if ($checkId && $loopGuard >= 20) {
                    $errors[] = 'Kategori hiyerarşisi kontrol edilirken beklenmedik bir hata oluştu.';
                }
            }

            if (!$errors) {
                $stmt = $pdo->prepare('UPDATE categories SET name = :name, parent_id = :parent_id, icon = :icon, image = :image, description = :description, updated_at = NOW() WHERE id = :id');
                $stmt->execute(array(
                    'id' => $categoryId,
                    'name' => $name,
                    'parent_id' => $parentId,
                    'icon' => $icon,
                    'image' => $image,
                    'description' => $description,
                ));

                $success = 'Kategori güncellendi.';

                AuditLog::record(
                    $currentUser['id'],
                    'product_category.update',
                    'category',
                    $categoryId,
                    sprintf('Kategori güncellendi: %s', $name)
                );
            }
        } elseif ($action === 'delete_category') {
            $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($categoryId <= 0) {
                $errors[] = 'Geçersiz kategori seçimi.';
            } else {
                $productCountStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
                $productCountStmt->execute(array('id' => $categoryId));
                $productCount = (int)$productCountStmt->fetchColumn();

                if ($productCount > 0) {
                    $errors[] = 'Bu kategoride ürün bulunduğu için silinemez. Önce ürünleri başka kategoriye taşıyın.';
                }

                if (!$errors) {
                    $childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :id');
                    $childCountStmt->execute(array('id' => $categoryId));
                    $childCount = (int)$childCountStmt->fetchColumn();

                    if ($childCount > 0) {
                        $errors[] = 'Alt kategorileri olan kategoriler silinemez. Önce alt kategorileri taşıyın ya da silin.';
                    }
                }

                if (!$errors) {
                    $delete = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                    $delete->execute(array('id' => $categoryId));

                    $success = 'Kategori silindi.';

                    AuditLog::record(
                        $currentUser['id'],
                        'product_category.delete',
                        'category',
                        $categoryId,
                        sprintf('Kategori silindi: #%d', $categoryId)
                    );
                }
            }
        }
    }
}

$rawCategories = $pdo->query('SELECT id, parent_id, name, icon, image, description, created_at, updated_at FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$categoryMap = array();
foreach ($rawCategories as $category) {
    $categoryMap[(int)$category['id']] = $category;
}

$categoryChildren = array();
foreach ($rawCategories as $category) {
    $parentId = isset($category['parent_id']) && $category['parent_id'] ? (int)$category['parent_id'] : 0;
    if (!isset($categoryChildren[$parentId])) {
        $categoryChildren[$parentId] = array();
    }
    $categoryChildren[$parentId][] = $category;
}

foreach ($categoryChildren as &$list) {
    usort($list, function ($a, $b) {
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });
}
unset($list);

$flattenedCategories = array();
$walker = function ($parentId, $depth) use (&$walker, &$flattenedCategories, $categoryChildren) {
    if (!isset($categoryChildren[$parentId])) {
        return;
    }

    foreach ($categoryChildren[$parentId] as $category) {
        $flattenedCategories[] = array(
            'id' => (int)$category['id'],
            'name' => isset($category['name']) ? (string)$category['name'] : '',
            'description' => isset($category['description']) ? (string)$category['description'] : '',
            'icon' => isset($category['icon']) ? (string)$category['icon'] : null,
            'image' => isset($category['image']) ? (string)$category['image'] : null,
            'depth' => $depth,
            'created_at' => isset($category['created_at']) ? $category['created_at'] : null,
            'updated_at' => isset($category['updated_at']) ? $category['updated_at'] : null,
        );

        $walker((int)$category['id'], $depth + 1);
    }
};
$walker(0, 0);

$productCounts = $pdo->query('SELECT category_id, COUNT(*) AS total FROM products GROUP BY category_id')->fetchAll(PDO::FETCH_ASSOC);
$productCountMap = array();
foreach ($productCounts as $row) {
    $productCountMap[(int)$row['category_id']] = (int)$row['total'];
}

$pageScripts = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$pageScripts[] = 'https://code.iconify.design/3/3.1.0/iconify.min.js';
$pageScripts[] = '/assets/admin/js/icon-picker.js';
$GLOBALS['pageScripts'] = array_values(array_unique($pageScripts));

$pageTitle = 'Kategoriler';

include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Yeni Kategori</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Kategorileri hiyerarşik olarak düzenleyebilir, ürünlerinizi daha kolay yönetebilirsiniz.</p>

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

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="action" value="create_category">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div>
                        <label class="form-label">Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Üst Kategori</label>
                        <select name="parent_id" class="form-select">
                            <option value="">(Ana kategori)</option>
                            <?php foreach ($flattenedCategories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= str_repeat('— ', $category['depth']) . Helpers::sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Icon Secimi</label>
                        <input type="text" name="icon" class="form-control" data-icon-picker placeholder="iconify:simple-icons:valorant">
                        <small class="text-muted">Opsiyonel. Listeden ikon secip veya manuel ikon adi girerek menulerde kullanabilirsiniz.</small>
                    </div>
                    <div>
                        <label class="form-label">Görsel (URL)</label>
                        <input type="text" name="image" class="form-control" placeholder="/theme/assets/images/site/example.webp">
                        <small class="text-muted">Opsiyonel. Küçük yuvarlak görsel için bir URL sağlayın.</small>
                    </div>
                    <div>
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Opsiyonel"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Kategori Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Kategori Listesi</h5>
            </div>
            <div class="card-body">
                <?php if (!$flattenedCategories): ?>
                    <p class="text-muted mb-0">Henüz kategori oluşturulmadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Ürün Sayısı</th>
                                <th>Güncelleme</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($flattenedCategories as $category): ?>
                                <?php $count = isset($productCountMap[$category['id']]) ? $productCountMap[$category['id']] : 0; ?>
                                <?php $map = isset($categoryMap[$category['id']]) ? $categoryMap[$category['id']] : array(); ?>
                                <tr>
                                    <td style="padding-left: <?= 12 + ($category['depth'] * 18) ?>px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($category['image'])): ?>
                                                <img src="<?= Helpers::sanitize($category['image']) ?>" alt="" width="28" height="28" class="rounded-circle border">
                                            <?php elseif (!empty($category['icon'])): ?>
                                                <span class="badge bg-light text-dark px-2 py-1">
                                                    <i class="<?= Helpers::sanitize($category['icon']) ?>"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis px-2 py-1">
                                                    <?= Helpers::sanitize(mb_strtoupper(mb_substr($category['name'], 0, 1))) ?>
                                                </span>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= Helpers::sanitize($category['name']) ?></strong>
                                                <?php if (!empty($category['description'])): ?>
                                                    <div class="text-muted small"><?= Helpers::sanitize($category['description']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($category['image'])): ?>
                                                    <div class="text-muted small">Görsel: <?= Helpers::sanitize($category['image']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($category['icon'])): ?>
                                                    <div class="text-muted small">Icon: <?= Helpers::sanitize($category['icon']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= (int)$count ?></span></td>
                                    <td>
                                        <?php if (!empty($category['updated_at'])): ?>
                                            <small class="text-muted"><?= Helpers::sanitize(date('d.m.Y H:i', strtotime($category['updated_at']))) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategory<?= (int)$category['id'] ?>">Düzenle</button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Kategoriyi silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" <?= $count > 0 ? 'disabled' : '' ?>>Sil</button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editCategory<?= (int)$category['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Kategoriyi Düzenle</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_category">
                                                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Kategori Adı</label>
                                                        <input type="text" name="name" class="form-control" value="<?= Helpers::sanitize($category['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Üst Kategori</label>
                                                        <select name="parent_id" class="form-select">
                                                            <option value="">(Ana kategori)</option>
                                                            <?php foreach ($flattenedCategories as $parent): ?>
                                                                <?php if ($parent['id'] === $category['id']) { continue; } ?>
                                                                <option value="<?= (int)$parent['id'] ?>" <?= isset($categoryMap[$category['id']]['parent_id']) && (int)$categoryMap[$category['id']]['parent_id'] === $parent['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $parent['depth']) . Helpers::sanitize($parent['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Icon Secimi</label>
                                                        <input type="text" name="icon" class="form-control" value="<?= Helpers::sanitize(isset($map['icon']) ? $map['icon'] : '') ?>" data-icon-picker placeholder="iconify:simple-icons:valorant">
                                                        <small class="text-muted">Opsiyonel. Listeden ikon secip veya manuel ikon adi girerek menulerde kullanabilirsiniz.</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Görsel (URL)</label>
                                                        <input type="text" name="image" class="form-control" value="<?= Helpers::sanitize(isset($map['image']) ? $map['image'] : '') ?>" placeholder="/theme/assets/images/site/example.webp">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Açıklama</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= Helpers::sanitize(isset($map['description']) ? $map['description'] : '') ?></textarea>
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












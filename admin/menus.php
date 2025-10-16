<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\MenuRepository;
use App\PageRepository;
use App\BlogRepository;
use App\Database;

Auth::requireRoles(array('super_admin', 'admin', 'content'));

$errors = array();
$success = '';
$locations = MenuRepository::locations();
$currentLocation = isset($_GET['location']) ? strtolower(trim((string)$_GET['location'])) : 'header';
if (!isset($locations[$currentLocation])) {
    $currentLocation = array_key_first($locations);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'İstek doğrulanamadı. Lütfen tekrar deneyin.';
    } elseif ($action === 'save_menu') {
        $location = isset($_POST['location']) ? (string)$_POST['location'] : '';
        if (!isset($locations[$location])) {
            $errors[] = 'Geçersiz menü konumu seçildi.';
        } else {
            $structureInput = isset($_POST['structure']) ? (string)$_POST['structure'] : '[]';
            $decoded = json_decode($structureInput, true);
            if (!is_array($decoded)) {
                $errors[] = 'Menü verisi çözümlenemedi. Lütfen sayfayı yenileyip tekrar deneyin.';
            } else {
                try {
                    MenuRepository::saveMenu($location, $decoded);
                    if ($location === 'header') {
                        MenuRepository::syncCategoryMenu();
                    }
                    $success = 'Menü yapısı güncellendi.';
                    $currentLocation = $location;
                } catch (\Throwable $exception) {
                    $errors[] = 'Menü kaydedilemedi: ' . $exception->getMessage();
                }
            }
        }
    }
}

$menuStructures = array();
foreach (array_keys($locations) as $locationKey) {
    $menuStructures[$locationKey] = MenuRepository::getMenuTree($locationKey, false);
}


$pdo = Database::connection();
$categoryRows = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: array();
$categoriesById = array();
foreach ($categoryRows as $row) {
    $id = (int)$row['id'];
    $categoriesById[$id] = array(
        'id' => $id,
        'parent_id' => isset($row['parent_id']) ? (int)$row['parent_id'] : 0,
        'name' => (string)$row['name'],
    );
}

$categoryPaths = array();
$resolveCategoryPath = function (int $categoryId) use (&$resolveCategoryPath, &$categoriesById, &$categoryPaths) {
    if (isset($categoryPaths[$categoryId])) {
        return $categoryPaths[$categoryId];
    }
    if (!isset($categoriesById[$categoryId])) {
        return '';
    }
    $category = $categoriesById[$categoryId];
    $parentId = $category['parent_id'];
    $slug = Helpers::slugify($category['name']);
    if ($slug === '') {
        $slug = 'kategori-' . $categoryId;
    }
    $path = $slug;
    if ($parentId > 0) {
        $parentPath = $resolveCategoryPath($parentId);
        $path = $parentPath !== '' ? $parentPath . '/' . $slug : $slug;
    }
    $categoryPaths[$categoryId] = $path;
    return $path;
};
foreach (array_keys($categoriesById) as $categoryId) {
    $resolveCategoryPath((int)$categoryId);
}

$applyCategoryMeta = function (array &$items) use (&$applyCategoryMeta, $categoryPaths) {
    foreach ($items as &$item) {
        if (isset($item['type']) && $item['type'] === 'category' && isset($item['reference_key'])) {
            $categoryId = (int)$item['reference_key'];
            if (isset($categoryPaths[$categoryId])) {
                $item['path'] = $categoryPaths[$categoryId];
            }
        }
        if (isset($item['children']) && is_array($item['children'])) {
            $applyCategoryMeta($item['children']);
        }
    }
    unset($item);
};

if (isset($menuStructures['header'])) {
    $applyCategoryMeta($menuStructures['header']);
}

$categoryOptions = array();
foreach ($categoriesById as $category) {
    $categoryOptions[] = array(
        'title' => $category['name'],
        'reference' => (string)$category['id'],
        'path' => isset($categoryPaths[$category['id']]) ? $categoryPaths[$category['id']] : '',
    );
}

$pageOptions = array();
foreach (PageRepository::all() as $page) {
    $slug = isset($page['slug']) ? (string)$page['slug'] : '';
    $pageOptions[] = array(
        'title' => isset($page['title']) ? (string)$page['title'] : $slug,
        'reference' => $slug,
    );
}

$blogOptions = array();
foreach (BlogRepository::listPublished(100) as $post) {
    $blogOptions[] = array(
        'title' => isset($post['title']) ? (string)$post['title'] : (isset($post['slug']) ? (string)$post['slug'] : 'Blog Yazısı'),
        'reference' => isset($post['slug']) ? (string)$post['slug'] : '',
    );
}

$adminRouteOptions = array(
    array('title' => 'Kontrol Paneli', 'url' => '/admin/dashboard.php', 'pattern' => '/admin/dashboard.php'),
    array('title' => 'Siparişler', 'url' => '/admin/orders.php', 'pattern' => '/admin/orders.php'),
    array('title' => 'Ürünler', 'url' => '/admin/products.php', 'pattern' => '/admin/products.php'),
    array('title' => 'Kullanıcılar', 'url' => '/admin/users.php', 'pattern' => '/admin/users.php'),
    array('title' => 'Sayfalar', 'url' => '/admin/pages.php', 'pattern' => '/admin/pages.php'),
    array('title' => 'Blog Yazıları', 'url' => '/admin/blog-posts.php', 'pattern' => '/admin/blog-posts.php'),
    array('title' => 'Bildirim Yönetimi', 'url' => '/admin/notifications.php', 'pattern' => '/admin/notifications.php'),
    array('title' => 'Menü Yönetimi', 'url' => '/admin/menus.php', 'pattern' => '/admin/menus.php'),
);

$menuConfig = array(
    'locations' => $locations,
    'structures' => $menuStructures,
    'sources' => array(
        'header' => array(
            'categories' => $categoryOptions,
            'pages' => $pageOptions,
            'blogs' => $blogOptions,
        ),
        'footer' => array(
            'pages' => $pageOptions,
            'categories' => $categoryOptions,
            'blogs' => $blogOptions,
        ),
        'admin' => array(
            'routes' => $adminRouteOptions,
        ),
    ),
);

$csrfToken = Helpers::csrfToken();

$GLOBALS['pageScripts'][] = '/assets/admin/js/menu-manager.js';
$pageTitle = 'Menü Yönetimi';

require __DIR__ . '/templates/header.php';
?>
<div class="app-content" data-menu-manager data-menu-config="<?= htmlspecialchars(json_encode($menuConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>">
    <div class="page-head">
        <h1>Menü Yönetimi</h1>
        <p>Site ve yönetim menülerini sürükle-bırak ile düzenleyin, bağlantıları hızlıca güncelleyin.</p>
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

    <ul class="nav nav-pills mb-4" role="tablist">
        <?php foreach ($locations as $locationKey => $locationMeta): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?= $locationKey === $currentLocation ? ' active' : '' ?>" type="button" data-menu-tab="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($locationMeta['title'], ENT_QUOTES, 'UTF-8') ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php foreach ($locations as $locationKey => $locationMeta): ?>
        <section class="menu-editor<?= $locationKey === $currentLocation ? ' is-active' : '' ?>" data-menu-section="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">
            <header class="menu-editor__head">
                <div>
                    <h2><?= htmlspecialchars($locationMeta['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($locationMeta['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="menu-editor__actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-menu-expand="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">Tümünü Aç</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-menu-collapse="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">Tümünü Kapat</button>
                </div>
            </header>
            <form method="post" class="menu-editor__form" data-menu-form="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_menu">
                <input type="hidden" name="location" value="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="structure" value="[]" data-menu-structure-input>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="menu-editor__canvas">
                            <ol class="menu-tree" data-menu-tree="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>"></ol>
                            <div class="menu-editor__empty" data-menu-empty="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">Menüye öğe eklemek için sağdaki bağlantıları kullanın.</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="menu-sources" data-menu-sources="<?= htmlspecialchars($locationKey, ENT_QUOTES, 'UTF-8') ?>">
                            <h3>Yeni Öğeler</h3>
                            <p class="text-muted">Bağlantı eklemek için aşağıdaki seçeneklerden birini seçin.</p>
                            <div class="menu-source__list" data-menu-source-list></div>
                            <div class="menu-source__custom">
                                <button type="button" class="btn btn-outline-primary w-100" data-menu-add-custom>Özel Bağlantı Ekle</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="menu-editor__footer">
                    <button type="submit" class="btn btn-primary">Menüyü Kaydet</button>
                </div>
            </form>
        </section>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" integrity="sha384-rkjc8htuhOcUEBhwKIDfU+P5pV6oru1j7rwhhtjfiPgkBC5T9jp+1rssZCvGSqtD" crossorigin="anonymous"></script>
<?php require __DIR__ . '/templates/footer.php'; ?>

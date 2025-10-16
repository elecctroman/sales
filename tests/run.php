<?php
session_start();

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Lang.php';
require_once __DIR__ . '/../app/Helpers.php';
require_once __DIR__ . '/../app/PageRepository.php';
require_once __DIR__ . '/../app/BlogRepository.php';
require_once __DIR__ . '/../app/MenuRepository.php';
require_once __DIR__ . '/../app/Notification.php';

use App\Lang;
use App\Helpers;
use App\PageRepository;
use App\BlogRepository;
use App\MenuRepository;
use App\Notification;

class TestFakePDOStatement
{
    private $pdo;
    private $sql;
    private $result = array();
    private $index = 0;
    private $bindings = array();

    public function __construct(TestFakePDO $pdo, string $sql)
    {
        $this->pdo = $pdo;
        $this->sql = trim($sql);
    }

    public function execute($params = array()): bool
    {
        $sql = $this->sql;
        $upper = strtoupper($sql);
        $this->result = array();
        $this->index = 0;

        if (strpos($upper, 'SELECT ID FROM MENUS WHERE LOCATION') === 0) {
            $location = $params['location'] ?? $params[':location'] ?? null;
            $menu = $this->pdo->findMenuByLocation($location);
            $this->result = $menu ? array(array('id' => $menu['id'])) : array();
            return true;
        }

        if (strpos($upper, 'SELECT COUNT(*) FROM MENU_ITEMS') === 0) {
            $menuId = isset($params['menu']) ? (int)$params['menu'] : 0;
            $count = $this->pdo->countMenuItems($menuId);
            $this->result = array(array('count' => $count));
            return true;
        }

        if (strpos($upper, 'SELECT * FROM MENU_ITEMS WHERE MENU_ID = :MENU AND TYPE = :TYPE') === 0) {
            $menuId = isset($params['menu']) ? (int)$params['menu'] : 0;
            $type = isset($params['type']) ? (string)$params['type'] : null;
            $items = $this->pdo->getMenuItemsByMenu($menuId, $type);
            $this->result = $items;
            return true;
        }

        if (strpos($upper, 'SELECT * FROM MENU_ITEMS WHERE MENU_ID = :MENU') === 0) {
            $menuId = isset($params['menu']) ? (int)$params['menu'] : 0;
            $visibleOnly = strpos($upper, 'AND IS_VISIBLE = 1') !== false;
            $items = $this->pdo->getMenuItemsByMenu($menuId, null, $visibleOnly);
            $this->result = $items;
            return true;
        }

        if (strpos($upper, 'INSERT INTO MENU_ITEMS') === 0) {
            $this->pdo->insertMenuItemRow(array(
                'menu_id' => isset($params['menu_id']) ? (int)$params['menu_id'] : 0,
                'parent_id' => array_key_exists('parent_id', $params) ? ($params['parent_id'] !== null ? (int)$params['parent_id'] : null) : null,
                'type' => isset($params['type']) ? (string)$params['type'] : 'custom',
                'reference_key' => array_key_exists('reference_key', $params) ? ($params['reference_key'] !== null ? (string)$params['reference_key'] : null) : null,
                'title' => isset($params['title']) ? (string)$params['title'] : '',
                'url' => array_key_exists('url', $params) ? ($params['url'] !== null ? (string)$params['url'] : null) : null,
                'target' => isset($params['target']) ? (string)$params['target'] : '_self',
                'position' => isset($params['position']) ? (int)$params['position'] : 0,
                'is_visible' => isset($params['is_visible']) ? (int)$params['is_visible'] : 1,
                'settings' => array_key_exists('settings', $params) ? $params['settings'] : null,
            ));
            return true;
        }

        if (strpos($upper, 'UPDATE MENU_ITEMS SET') === 0) {
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            $fields = array();
            foreach (array('parent_id', 'type', 'reference_key', 'title', 'url', 'target', 'position', 'is_visible', 'settings') as $field) {
                if (array_key_exists($field, $params)) {
                    $fields[$field] = $params[$field];
                }
            }
            $this->pdo->updateMenuItem($id, $fields);
            return true;
        }

        if (strpos($upper, 'DELETE FROM MENU_ITEMS WHERE ID IN') === 0) {
            $menuId = isset($params['menu']) ? (int)$params['menu'] : 0;
            if (preg_match('/IN \(([^)]+)\)/i', $sql, $matches)) {
                $ids = array();
                foreach (explode(',', $matches[1]) as $part) {
                    $ids[] = (int)trim($part);
                }
                $this->pdo->deleteMenuItemsByIds($ids, $menuId);
            }
            return true;
        }

        if (strpos($upper, 'DELETE FROM MENU_ITEMS WHERE MENU_ID = ? AND TYPE = "CATEGORY"') === 0) {
            $menuId = isset($params[0]) ? (int)$params[0] : 0;
            $allowed = array();
            foreach ($params as $index => $value) {
                if ($index === 0) {
                    continue;
                }
                $allowed[] = (string)$value;
            }
            $this->pdo->deleteCategoryMenuItemsNotIn($menuId, $allowed);
            return true;
        }

        if (strpos($upper, 'DELETE FROM MENU_ITEMS WHERE MENU_ID = :MENU') === 0) {
            $menuId = isset($params['menu']) ? (int)$params['menu'] : 0;
            $this->pdo->deleteMenuItemsByMenu($menuId);
            return true;
        }

        if (strpos($upper, 'SELECT ID, PARENT_ID, NAME, ICON, IMAGE FROM CATEGORIES') === 0) {
            $this->result = $this->pdo->getCategoriesSorted(true);
            return true;
        }

        if (strpos($upper, 'SELECT ID, PARENT_ID, NAME FROM CATEGORIES') === 0) {
            $this->result = $this->pdo->getCategoriesSorted(false);
            return true;
        }

        if (strpos($upper, 'INSERT INTO NOTIFICATIONS') === 0) {
            $this->pdo->insertNotification(array(
                'title' => $params['title'] ?? '',
                'message' => $params['message'] ?? '',
                'link' => array_key_exists('link', $params) ? $params['link'] : null,
                'scope' => $params['scope'] ?? 'global',
                'user_id' => array_key_exists('user_id', $params) ? $params['user_id'] : null,
                'status' => $params['status'] ?? 'draft',
                'publish_at' => array_key_exists('publish_at', $params) ? $params['publish_at'] : null,
                'expire_at' => array_key_exists('expire_at', $params) ? $params['expire_at'] : null,
            ));
            return true;
        }

        if (strpos($upper, 'UPDATE NOTIFICATIONS SET') === 0) {
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            $fields = array();
            foreach (array('title', 'message', 'link', 'scope', 'user_id', 'status', 'publish_at', 'expire_at') as $field) {
                if (array_key_exists($field, $params)) {
                    $fields[$field] = $params[$field];
                }
            }
            $this->pdo->updateNotification($id, $fields);
            return true;
        }

        if (strpos($upper, 'SELECT * FROM NOTIFICATIONS WHERE ID = :ID') === 0) {
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            $row = $this->pdo->findNotification($id);
            $this->result = $row ? array($row) : array();
            return true;
        }

        if (strpos($upper, 'DELETE FROM NOTIFICATIONS WHERE ID = :ID') === 0) {
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            $this->pdo->deleteNotification($id);
            return true;
        }

        if (strpos($upper, 'INSERT INTO NOTIFICATION_READS') === 0) {
            return true;
        }

        $this->result = array();
        return true;
    }

    public function fetch($mode = null)
    {
        if ($this->index >= count($this->result)) {
            return false;
        }
        $row = $this->result[$this->index];
        $this->index++;
        return $row;
    }

    public function fetchAll($mode = null)
    {
        return $this->result;
    }

    public function fetchColumn($columnNumber = 0)
    {
        if (!$this->result) {
            return false;
        }
        $row = $this->result[0];
        $values = array_values($row);
        return $values[$columnNumber] ?? false;
    }

    public function bindValue($parameter, $value, $dataType = null)
    {
        $this->bindings[$parameter] = $value;
        return true;
    }

    public function bindParam($parameter, &$variable, $dataType = null, $length = null, $driverOptions = null)
    {
        $this->bindings[$parameter] = $variable;
        return true;
    }
}

class TestFakePDO extends PDO
{
    public $tables = array();
    private $autoIds = array();
    private $lastInsertId = 0;

    public function __construct()
    {
        // Prevent parent constructor from attempting a real connection.
    }

    public function initStorage(): void
    {
        $this->tables = array(
            'menus' => array(),
            'menu_items' => array(),
            'categories' => array(),
            'notifications' => array(),
        );
        $this->autoIds = array(
            'menus' => 0,
            'menu_items' => 0,
            'categories' => 0,
            'notifications' => 0,
        );
    }

    #[\ReturnTypeWillChange]
    public function exec($sql)
    {
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function prepare($sql, $options = array())
    {
        return new TestFakePDOStatement($this, $sql);
    }

    #[\ReturnTypeWillChange]
    public function query($sql, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        $stmt = new TestFakePDOStatement($this, $sql);
        $stmt->execute();
        return $stmt;
    }

    #[\ReturnTypeWillChange]
    public function beginTransaction()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function commit()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function rollBack()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function lastInsertId($name = null)
    {
        return (string)$this->lastInsertId;
    }

    public function setLastInsertId(int $id): void
    {
        $this->lastInsertId = $id;
    }

    public function nextId(string $table): int
    {
        if (!isset($this->autoIds[$table])) {
            $this->autoIds[$table] = 0;
        }
        $this->autoIds[$table]++;
        $this->setLastInsertId($this->autoIds[$table]);
        return $this->autoIds[$table];
    }

    public function seedMenu(int $id, string $location, string $title = '', string $description = ''): void
    {
        $this->tables['menus'][$id] = array(
            'id' => $id,
            'location' => $location,
            'title' => $title !== '' ? $title : ucfirst($location),
            'description' => $description,
        );
        if ($id > $this->autoIds['menus']) {
            $this->autoIds['menus'] = $id;
        }
    }

    public function seedCategory(int $id, int $parentId, string $name, string $icon = '', string $image = ''): void
    {
        $this->tables['categories'][$id] = array(
            'id' => $id,
            'parent_id' => $parentId,
            'name' => $name,
            'icon' => $icon,
            'image' => $image,
        );
        if ($id > $this->autoIds['categories']) {
            $this->autoIds['categories'] = $id;
        }
    }

    public function insertMenuItemRow(array $row): int
    {
        if (!isset($row['id']) || (int)$row['id'] === 0) {
            $row['id'] = $this->nextId('menu_items');
        } else {
            $this->setLastInsertId((int)$row['id']);
            if ($row['id'] > $this->autoIds['menu_items']) {
                $this->autoIds['menu_items'] = (int)$row['id'];
            }
        }

        $defaults = array(
            'menu_id' => 0,
            'parent_id' => null,
            'type' => 'custom',
            'reference_key' => null,
            'title' => '',
            'url' => null,
            'target' => '_self',
            'position' => 0,
            'is_visible' => 1,
            'settings' => null,
        );
        $row = array_merge($defaults, $row);
        $row['menu_id'] = (int)$row['menu_id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
        $row['position'] = (int)$row['position'];
        $row['is_visible'] = (int)$row['is_visible'];
        $this->tables['menu_items'][(int)$row['id']] = $row;

        return (int)$row['id'];
    }

    public function updateMenuItem(int $id, array $fields): void
    {
        if (!isset($this->tables['menu_items'][$id])) {
            return;
        }
        foreach ($fields as $key => $value) {
            if ($key === 'parent_id') {
                $this->tables['menu_items'][$id][$key] = $value !== null ? (int)$value : null;
                continue;
            }
            if ($key === 'position' || $key === 'is_visible') {
                $this->tables['menu_items'][$id][$key] = (int)$value;
                continue;
            }
            if ($key === 'menu_id' || $key === 'id') {
                continue;
            }
            $this->tables['menu_items'][$id][$key] = $value;
        }
    }

    public function deleteMenuItemsByIds(array $ids, int $menuId): void
    {
        foreach ($ids as $id) {
            $id = (int)$id;
            if (isset($this->tables['menu_items'][$id]) && (int)$this->tables['menu_items'][$id]['menu_id'] === $menuId) {
                unset($this->tables['menu_items'][$id]);
            }
        }
    }

    public function deleteMenuItemsByMenu(int $menuId): void
    {
        foreach ($this->tables['menu_items'] as $id => $item) {
            if ((int)$item['menu_id'] === $menuId) {
                unset($this->tables['menu_items'][$id]);
            }
        }
    }

    public function deleteCategoryMenuItemsNotIn(int $menuId, array $allowed): void
    {
        $allowedMap = array_flip($allowed);
        foreach ($this->tables['menu_items'] as $id => $item) {
            if ((int)$item['menu_id'] !== $menuId) {
                continue;
            }
            if ($item['type'] !== 'category') {
                continue;
            }
            $reference = $item['reference_key'] !== null ? (string)$item['reference_key'] : '';
            if (!isset($allowedMap[$reference])) {
                unset($this->tables['menu_items'][$id]);
            }
        }
    }

    public function getMenuItemsByMenu(int $menuId, ?string $type = null, bool $visibleOnly = false): array
    {
        $items = array();
        foreach ($this->tables['menu_items'] as $item) {
            if ((int)$item['menu_id'] !== $menuId) {
                continue;
            }
            if ($type !== null && $item['type'] !== $type) {
                continue;
            }
            if ($visibleOnly && empty($item['is_visible'])) {
                continue;
            }
            $items[] = $item;
        }
        usort($items, function ($a, $b) {
            if ($a['position'] === $b['position']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['position'] <=> $b['position'];
        });

        return $items;
    }

    public function countMenuItems(int $menuId): int
    {
        $count = 0;
        foreach ($this->tables['menu_items'] as $item) {
            if ((int)$item['menu_id'] === $menuId) {
                $count++;
            }
        }
        return $count;
    }

    public function findMenuByLocation(?string $location): ?array
    {
        if ($location === null) {
            return null;
        }
        foreach ($this->tables['menus'] as $menu) {
            if ($menu['location'] === $location) {
                return $menu;
            }
        }
        return null;
    }

    public function getCategoriesSorted(bool $withMedia): array
    {
        $rows = array_values($this->tables['categories']);
        usort($rows, function ($a, $b) {
            if ($a['parent_id'] === $b['parent_id']) {
                return $a['id'] <=> $b['id'];
            }
            return $a['parent_id'] <=> $b['parent_id'];
        });

        $mapped = array();
        foreach ($rows as $row) {
            $entry = array(
                'id' => (int)$row['id'],
                'parent_id' => (int)$row['parent_id'],
                'name' => $row['name'],
            );
            if ($withMedia) {
                $entry['icon'] = isset($row['icon']) ? $row['icon'] : '';
                $entry['image'] = isset($row['image']) ? $row['image'] : '';
            }
            $mapped[] = $entry;
        }

        return $mapped;
    }

    public function insertNotification(array $row): int
    {
        $id = $this->nextId('notifications');
        $now = date('Y-m-d H:i:s');
        $this->tables['notifications'][$id] = array(
            'id' => $id,
            'title' => $row['title'],
            'message' => $row['message'],
            'link' => $row['link'],
            'scope' => $row['scope'],
            'user_id' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
            'status' => $row['status'],
            'publish_at' => $row['publish_at'],
            'expire_at' => $row['expire_at'],
            'created_at' => $now,
            'updated_at' => $now,
        );
        return $id;
    }

    public function updateNotification(int $id, array $fields): void
    {
        if (!isset($this->tables['notifications'][$id])) {
            return;
        }
        foreach ($fields as $key => $value) {
            if ($key === 'user_id') {
                $this->tables['notifications'][$id][$key] = $value !== null ? (int)$value : null;
            } else {
                $this->tables['notifications'][$id][$key] = $value;
            }
        }
        $this->tables['notifications'][$id]['updated_at'] = date('Y-m-d H:i:s');
    }

    public function findNotification(int $id): ?array
    {
        return isset($this->tables['notifications'][$id]) ? $this->tables['notifications'][$id] : null;
    }

    public function deleteNotification(int $id): void
    {
        unset($this->tables['notifications'][$id]);
    }
}

$testPdo = new TestFakePDO();
$testPdo->initStorage();
$testPdo->seedMenu(1, 'header', 'Site Üst Menü');
$testPdo->seedMenu(2, 'footer', 'Site Alt Menü');
$testPdo->seedMenu(3, 'admin', 'Yönetim Menüsü');

$setConnection = \Closure::bind(function ($pdo) {
    self::$connection = $pdo;
}, null, \App\Database::class);
$setConnection($testPdo);

$databaseReflection = new ReflectionClass(\App\Database::class);
$connectionProperty = $databaseReflection->getProperty('connection');
$connectionProperty->setAccessible(true);
$connectionProperty->setValue(null, $testPdo);

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'off';

Lang::boot();
Lang::setLocale('en');

$assertions = array();
$assertions[] = Lang::locale() === 'tr';
$assertions[] = Lang::htmlLocale() === 'tr';

$slug = Helpers::slugify('Çılgın Şövalye 2024');
$assertions[] = $slug === 'cilgin-sovalye-2024';

$categoryPath = Helpers::categoryUrl('oyun/test');
$assertions[] = $categoryPath === '/kategori/oyun/test';

$absoluteCategory = Helpers::categoryUrl('deneme', true);
$assertions[] = strpos($absoluteCategory, '/kategori/deneme') !== false;

$sanitized = Helpers::sanitize('<script>alert("x")</script>');
$assertions[] = $sanitized === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;';

$invalidInput = "Ge\xffi";
$assertions[] = mb_check_encoding(htmlspecialchars_decode(Helpers::sanitize($invalidInput)), 'UTF-8');

$pageUrl = Helpers::pageUrl('Gizlilik Politikası');
$assertions[] = $pageUrl === '/page/gizlilik-politikasi';

$unsafeHtml = '<p onclick="evil()">Merhaba <a href="javascript:alert(1)">test</a></p><script>alert(1)</script>';
$cleanHtml = Helpers::sanitizePageHtml($unsafeHtml);
$assertions[] = strpos($cleanHtml, 'onclick') === false;
$assertions[] = strpos($cleanHtml, 'javascript:') === false;
$assertions[] = strpos($cleanHtml, '<script') === false;

$_GET = array('category' => 'aksiyon', 'page' => '3', 'sort' => 'new');
$paginationUrl = Helpers::replaceQueryParameters('/blog', array('page' => 2), array('remove' => array('page')));
$assertions[] = $paginationUrl === '/blog?category=aksiyon&page=2&sort=new';

$defaults = PageRepository::defaultPages();
$assertions[] = isset($defaults['gizlilik-politikasi']);
$assertions[] = isset($defaults['api-dokumantasyon']);

$apiDocs = PageRepository::findBySlug('api-dokumantasyon');
$assertions[] = is_array($apiDocs) && isset($apiDocs['content']) && strpos($apiDocs['content'], 'API') !== false;

$blogPagination = BlogRepository::paginatePublished(1, 6);
$assertions[] = is_array($blogPagination) && isset($blogPagination['page']) && $blogPagination['page'] === 1;
$assertions[] = isset($blogPagination['total']) && $blogPagination['total'] === 0;
$assertions[] = isset($blogPagination['items']) && is_array($blogPagination['items']);

MenuRepository::saveMenu('header', array(
    array(
        'type' => 'custom',
        'title' => 'Anasayfa',
        'url' => '/',
        'target' => '_self',
        'is_visible' => true,
        'children' => array(),
    ),
    array(
        'type' => 'category',
        'reference_key' => '1',
        'title' => 'Elektronik',
        'url' => '/kategori/elektronik',
        'target' => '_self',
        'is_visible' => true,
        'settings' => array('title_locked' => false),
        'children' => array(),
    ),
));

$headerTree = MenuRepository::getMenuTree('header');
$assertions[] = is_array($headerTree) && count($headerTree) === 2;
$assertions[] = isset($headerTree[0]['title']) && $headerTree[0]['title'] === 'Anasayfa';
$assertions[] = isset($headerTree[1]['type']) && $headerTree[1]['type'] === 'category';

MenuRepository::saveMenu('header', array(
    array(
        'id' => $headerTree[1]['id'],
        'type' => 'category',
        'reference_key' => '1',
        'title' => 'Elektronik',
        'url' => '/kategori/elektronik',
        'target' => '_self',
        'is_visible' => true,
        'children' => array(),
    ),
));

$headerTreeAfterUpdate = MenuRepository::getMenuTree('header');
$assertions[] = count($headerTreeAfterUpdate) === 1;
$categoryItemId = $headerTreeAfterUpdate[0]['id'];

$testPdo->seedCategory(1, 0, 'Elektronik Güncel');
$testPdo->seedCategory(2, 1, 'Konsol Ürünleri');
$testPdo->seedCategory(3, 0, 'Yazılım');

$testPdo->insertMenuItemRow(array(
    'menu_id' => 1,
    'parent_id' => $categoryItemId,
    'type' => 'category',
    'reference_key' => '2',
    'title' => 'Sabit Başlık',
    'url' => null,
    'target' => '_self',
    'position' => 2,
    'is_visible' => 1,
    'settings' => json_encode(array('title_locked' => true)),
));

MenuRepository::syncCategoryMenu();

$headerTreeSynced = MenuRepository::getMenuTree('header');
$findCategory = function (array $nodes, string $reference) use (&$findCategory) {
    foreach ($nodes as $node) {
        if (isset($node['reference_key']) && (string)$node['reference_key'] === $reference) {
            return $node;
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            $found = $findCategory($node['children'], $reference);
            if ($found) {
                return $found;
            }
        }
    }
    return null;
};

$catOne = $findCategory($headerTreeSynced, '1');
$catTwo = $findCategory($headerTreeSynced, '2');
$catThree = $findCategory($headerTreeSynced, '3');

$assertions[] = $catOne !== null && $catOne['title'] === 'Elektronik Güncel';
$assertions[] = $catTwo !== null && $catTwo['title'] === 'Sabit Başlık';
$assertions[] = $catThree !== null && $catThree['title'] === 'Yazılım';
$assertions[] = $catTwo !== null && isset($catTwo['parent_id']) && $catTwo['parent_id'] === $catOne['id'];

$categoryCounter = 0;
$walkCategories = function (array $nodes) use (&$walkCategories, &$categoryCounter) {
    foreach ($nodes as $node) {
        if (isset($node['type']) && $node['type'] === 'category') {
            $categoryCounter++;
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            $walkCategories($node['children']);
        }
    }
};
$walkCategories($headerTreeSynced);
$assertions[] = $categoryCounter === 3;

$notificationValidationFailed = false;
try {
    Notification::save(array('title' => '', 'message' => ''));
} catch (\InvalidArgumentException $exception) {
    $notificationValidationFailed = true;
}
$assertions[] = $notificationValidationFailed;

$userScopeValidationFailed = false;
try {
    Notification::save(array('title' => 'Test', 'message' => 'Kapsam', 'scope' => 'user'));
} catch (\InvalidArgumentException $exception) {
    $userScopeValidationFailed = true;
}
$assertions[] = $userScopeValidationFailed;

$publishedNotification = Notification::save(array(
    'title' => 'Genel Duyuru',
    'message' => 'Merhaba dünya',
    'status' => 'published',
));
$assertions[] = isset($publishedNotification['id']) && $publishedNotification['id'] > 0;
$assertions[] = $publishedNotification['status'] === 'published';
$assertions[] = !empty($publishedNotification['publish_at']);

$draftNotification = Notification::save(array(
    'id' => $publishedNotification['id'],
    'title' => 'Güncellendi',
    'message' => 'Yeni içerik',
    'status' => 'draft',
    'scope' => 'global',
));
$assertions[] = $draftNotification['title'] === 'Güncellendi';
$assertions[] = $draftNotification['status'] === 'draft';
$assertions[] = $draftNotification['publish_at'] === null;

$userNotification = Notification::save(array(
    'title' => 'Özel Duyuru',
    'message' => 'Merhaba kullanıcı',
    'scope' => 'user',
    'user_id' => 42,
    'status' => 'published',
    'publish_at' => '2024-01-01 10:00:00',
));
$assertions[] = $userNotification['scope'] === 'user';
$assertions[] = $userNotification['user_id'] === 42;
$assertions[] = $userNotification['publish_at'] === '2024-01-01 10:00:00';

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "Testler başarısız oldu.\n");
    exit(1);
}

echo "Tüm testler başarıyla tamamlandı.\n";

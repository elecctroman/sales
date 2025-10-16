<?php

namespace App;

use App\Database;
use App\Helpers;
use App\Auth;
use PDO;

class MenuRepository
{
    private const TABLE_MENUS = 'menus';
    private const TABLE_ITEMS = 'menu_items';

    /**
     * Ensure menu tables exist in the database.
     *
     * @return void
     */
    public static function ensureTables(): void
    {
        $pdo = Database::connection();

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE_MENUS . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            location VARCHAR(60) NOT NULL UNIQUE,
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE_ITEMS . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            menu_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            type VARCHAR(30) NOT NULL DEFAULT "custom",
            reference_key VARCHAR(191) DEFAULT NULL,
            title VARCHAR(191) NOT NULL,
            url VARCHAR(255) DEFAULT NULL,
            target VARCHAR(20) NOT NULL DEFAULT "_self",
            position INT NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            settings LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_menu (menu_id),
            INDEX idx_parent (parent_id),
            INDEX idx_type (type),
            INDEX idx_reference (reference_key),
            CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES ' . self::TABLE_MENUS . ' (id) ON DELETE CASCADE,
            CONSTRAINT fk_menu_items_parent FOREIGN KEY (parent_id) REFERENCES ' . self::TABLE_ITEMS . ' (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * Menu locations that can be managed.
     *
     * @return array
     */
    public static function locations(): array
    {
        return array(
            'header' => array(
                'title' => 'Site Üst Menü',
                'description' => 'Kategori ve özel bağlantıları içeren üst navigasyon menüsü.',
                'max_depth' => 2,
                'auto' => 'category',
            ),
            'footer' => array(
                'title' => 'Site Alt Menü',
                'description' => 'Footer sütunlarını ve bağlantılarını düzenleyin.',
                'max_depth' => 2,
            ),
            'admin' => array(
                'title' => 'Yönetim Menüsü',
                'description' => 'Admin panelindeki gezinme menüsü.',
                'max_depth' => 2,
            ),
        );
    }

    /**
     * Ensure menus exist and seed defaults.
     *
     * @return void
     */
    public static function bootstrap(): void
    {
        self::ensureTables();

        $pdo = Database::connection();
        foreach (self::locations() as $location => $config) {
            $stmt = $pdo->prepare('SELECT id FROM ' . self::TABLE_MENUS . ' WHERE location = :location LIMIT 1');
            $stmt->execute(array('location' => $location));
            $menuId = (int)$stmt->fetchColumn();

            if ($menuId <= 0) {
                $insert = $pdo->prepare('INSERT INTO ' . self::TABLE_MENUS . ' (location, title, description, created_at, updated_at)
                    VALUES (:location, :title, :description, NOW(), NOW())');
                $insert->execute(array(
                    'location' => $location,
                    'title' => $config['title'],
                    'description' => $config['description'],
                ));
                $menuId = (int)$pdo->lastInsertId();
            }

            if ($location === 'admin' && !self::hasItems($menuId)) {
                self::seedAdminMenu($menuId);
            }

            if ($location === 'footer' && !self::hasItems($menuId)) {
                self::seedFooterMenu($menuId);
            }
        }
    }

    /**
     * Check whether the given menu has items.
     *
     * @param int $menuId
     * @return bool
     */
    private static function hasItems(int $menuId): bool
    {
        if ($menuId <= 0) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE_ITEMS . ' WHERE menu_id = :menu LIMIT 1');
        $stmt->execute(array('menu' => $menuId));

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Sync category records with header menu entries.
     *
     * @return void
     */
    public static function syncCategoryMenu(): void
    {
        $menuId = self::menuId('header');
        if ($menuId <= 0) {
            return;
        }

        $pdo = Database::connection();
        $categoryStmt = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY parent_id ASC, id ASC');
        $categories = array();
        while ($row = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['id'];
            $categories[$id] = array(
                'id' => $id,
                'parent_id' => isset($row['parent_id']) ? (int)$row['parent_id'] : 0,
                'name' => (string)$row['name'],
            );
        }

        if (!$categories) {
            return;
        }

        $existingStmt = $pdo->prepare('SELECT * FROM ' . self::TABLE_ITEMS . ' WHERE menu_id = :menu AND type = :type');
        $existingStmt->execute(array('menu' => $menuId, 'type' => 'category'));
        $existing = array();
        $parentPositions = array();
        while ($item = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
            $referenceKey = isset($item['reference_key']) ? (string)$item['reference_key'] : '';
            $categoryId = (int)$referenceKey;
            $item['id'] = (int)$item['id'];
            $item['parent_id'] = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            $item['position'] = isset($item['position']) ? (int)$item['position'] : 0;
            $item['settings'] = self::decodeSettings($item['settings']);
            if ($categoryId > 0) {
                $existing[$categoryId] = $item;
                $parentKey = $item['parent_id'] ?: 0;
                if (!isset($parentPositions[$parentKey]) || $item['position'] > $parentPositions[$parentKey]) {
                    $parentPositions[$parentKey] = $item['position'];
                }
            }
        }

        $processed = array();
        $ensureCategoryItem = function (int $categoryId) use (&$ensureCategoryItem, &$categories, &$existing, &$processed, &$parentPositions, $pdo, $menuId) {
            if (isset($processed[$categoryId])) {
                return $processed[$categoryId];
            }
            if (!isset($categories[$categoryId])) {
                return 0;
            }

            $category = $categories[$categoryId];
            $parentCategoryId = $category['parent_id'];
            $parentItemId = 0;
            if ($parentCategoryId > 0) {
                $parentItemId = $ensureCategoryItem($parentCategoryId);
            }

            if (isset($existing[$categoryId])) {
                $item = $existing[$categoryId];
                $desiredParent = $parentItemId > 0 ? $parentItemId : null;
                $needsUpdate = false;
                $updateFields = array('id' => $item['id']);

                if ($item['parent_id'] !== ($parentItemId > 0 ? $parentItemId : 0)) {
                    $updateFields['parent_id'] = $desiredParent;
                    $needsUpdate = true;
                }

                $titleLocked = !empty($item['settings']['title_locked']);
                if (!$titleLocked && $item['title'] !== $category['name']) {
                    $updateFields['title'] = $category['name'];
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $parts = array();
                    if (array_key_exists('parent_id', $updateFields)) {
                        $parts[] = 'parent_id = :parent_id';
                    }
                    if (array_key_exists('title', $updateFields)) {
                        $parts[] = 'title = :title';
                    }
                    if ($parts) {
                        $sql = 'UPDATE ' . self::TABLE_ITEMS . ' SET ' . implode(', ', $parts) . ', updated_at = NOW() WHERE id = :id LIMIT 1';
                        $pdo->prepare($sql)->execute($updateFields);
                    }
                }

                $processed[$categoryId] = $item['id'];
                return $item['id'];
            }

            $parentKey = $parentItemId ?: 0;
            $position = isset($parentPositions[$parentKey]) ? $parentPositions[$parentKey] + 1 : 1;
            $parentPositions[$parentKey] = $position;

            $insert = $pdo->prepare('INSERT INTO ' . self::TABLE_ITEMS . ' (menu_id, parent_id, type, reference_key, title, url, target, position, is_visible, settings, created_at, updated_at)
                VALUES (:menu_id, :parent_id, :type, :reference_key, :title, NULL, "_self", :position, 1, :settings, NOW(), NOW())');
            $insert->execute(array(
                'menu_id' => $menuId,
                'parent_id' => $parentItemId > 0 ? $parentItemId : null,
                'type' => 'category',
                'reference_key' => (string)$categoryId,
                'title' => $category['name'],
                'position' => $position,
                'settings' => self::encodeSettings(array('title_locked' => false)),
            ));

            $newId = (int)$pdo->lastInsertId();
            $processed[$categoryId] = $newId;
            return $newId;
        };

        foreach (array_keys($categories) as $categoryId) {
            $ensureCategoryItem((int)$categoryId);
        }

        $categoryKeys = array_map('strval', array_keys($categories));
        if ($categoryKeys) {
            $placeholders = implode(',', array_fill(0, count($categoryKeys), '?'));
            $params = array_merge(array($menuId), $categoryKeys);
            $delete = $pdo->prepare('DELETE FROM ' . self::TABLE_ITEMS . ' WHERE menu_id = ? AND type = "category" AND reference_key NOT IN (' . $placeholders . ')');
            $delete->execute($params);
        }
    }

    /**
     * Return menu tree for a location.
     *
     * @param string $location
     * @param bool $visibleOnly
     * @return array
     */
    public static function getMenuTree(string $location, bool $visibleOnly = false): array
    {
        $menuId = self::menuId($location);
        if ($menuId <= 0) {
            return array();
        }

        $rows = self::fetchItems($menuId, $visibleOnly);
        if (!$rows) {
            return array();
        }

        $indexed = array();
        foreach ($rows as $row) {
            $row['children'] = array();
            $indexed[$row['id']] = $row;
        }

        $tree = array();
        foreach ($indexed as $id => &$item) {
            $parentId = $item['parent_id'];
            if ($parentId && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] =& $item;
            } else {
                $tree[] =& $item;
            }
        }
        unset($item);

        return $tree;
    }

    /**
     * Save menu structure for a location.
     *
     * @param string $location
     * @param array $structure
     * @return void
     */
    public static function saveMenu(string $location, array $structure): void
    {
        $menuId = self::menuId($location);
        if ($menuId <= 0) {
            throw new \RuntimeException('Menü kaydı bulunamadı.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existing = self::fetchExistingItems($menuId);
            $allowedTypes = array('custom', 'category', 'page', 'blog', 'route', 'group');
            $seen = array();
            $orderCounters = array();

            $persist = function (array $node, ?int $parentId = null) use (&$persist, &$seen, &$orderCounters, &$existing, $allowedTypes, $pdo, $menuId) {
                $rawType = isset($node['type']) ? strtolower((string)$node['type']) : 'custom';
                $type = in_array($rawType, $allowedTypes, true) ? $rawType : 'custom';
                $id = isset($node['id']) ? (int)$node['id'] : 0;
                $reference = null;
                if (isset($node['reference_key'])) {
                    $reference = (string)$node['reference_key'];
                } elseif (isset($node['reference'])) {
                    $reference = (string)$node['reference'];
                } elseif (isset($node['reference_id'])) {
                    $reference = (string)$node['reference_id'];
                }

                if ($type === 'category' && $reference !== null && !ctype_digit($reference)) {
                    $reference = null;
                }

                $title = isset($node['title']) ? trim((string)$node['title']) : '';
                $url = isset($node['url']) ? trim((string)$node['url']) : '';
                $target = isset($node['target']) && strtolower((string)$node['target']) === '_blank' ? '_blank' : '_self';
                $visible = !empty($node['is_visible']);
                $settings = array();
                if (isset($node['settings'])) {
                    if (is_array($node['settings'])) {
                        $settings = $node['settings'];
                    } elseif (is_string($node['settings'])) {
                        $decoded = json_decode($node['settings'], true);
                        if (is_array($decoded)) {
                            $settings = $decoded;
                        }
                    }
                }

                $parentKey = $parentId ?: 0;
                if (!isset($orderCounters[$parentKey])) {
                    $orderCounters[$parentKey] = 0;
                }
                $orderCounters[$parentKey]++;
                $position = $orderCounters[$parentKey];

                $payload = array(
                    'menu_id' => $menuId,
                    'parent_id' => $parentId ?: null,
                    'type' => $type,
                    'reference_key' => $reference,
                    'title' => $title !== '' ? $title : 'Menü Öğesi',
                    'url' => $url !== '' ? $url : null,
                    'target' => $target,
                    'position' => $position,
                    'is_visible' => $visible ? 1 : 0,
                    'settings' => self::encodeSettings($settings),
                );

                if ($id > 0 && isset($existing[$id])) {
                    $update = $pdo->prepare('UPDATE ' . self::TABLE_ITEMS . '
                        SET parent_id = :parent_id, type = :type, reference_key = :reference_key, title = :title,
                            url = :url, target = :target, position = :position, is_visible = :is_visible, settings = :settings,
                            updated_at = NOW()
                        WHERE id = :id LIMIT 1');
                    $update->execute(array(
                        'parent_id' => $payload['parent_id'],
                        'type' => $payload['type'],
                        'reference_key' => $payload['reference_key'],
                        'title' => $payload['title'],
                        'url' => $payload['url'],
                        'target' => $payload['target'],
                        'position' => $payload['position'],
                        'is_visible' => $payload['is_visible'],
                        'settings' => $payload['settings'],
                        'id' => $id,
                    ));
                } else {
                    $insert = $pdo->prepare('INSERT INTO ' . self::TABLE_ITEMS . '
                        (menu_id, parent_id, type, reference_key, title, url, target, position, is_visible, settings, created_at, updated_at)
                        VALUES (:menu_id, :parent_id, :type, :reference_key, :title, :url, :target, :position, :is_visible, :settings, NOW(), NOW())');
                    $insert->execute($payload);
                    $id = (int)$pdo->lastInsertId();
                }

                $seen[] = $id;

                if (!empty($node['children']) && is_array($node['children'])) {
                    $orderCounters[$id] = 0;
                    foreach ($node['children'] as $child) {
                        $persist($child, $id);
                    }
                }
            };

            $orderCounters[0] = 0;
            foreach ($structure as $node) {
                $persist($node, null);
            }

            if ($existing) {
                $keep = array_flip($seen);
                $deleteIds = array();
                foreach ($existing as $existingId => $row) {
                    if (!isset($keep[$existingId])) {
                        $deleteIds[] = (int)$existingId;
                    }
                }

                if ($deleteIds) {
                    $pdo->prepare('DELETE FROM ' . self::TABLE_ITEMS . ' WHERE id IN (' . implode(',', $deleteIds) . ') AND menu_id = :menu')
                        ->execute(array('menu' => $menuId));
                }
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * Build navigation menu for the theme header.
     *
     * @return array
     */
    public static function buildHeaderMenu(): array
    {
        $tree = self::getMenuTree('header', true);
        if (!$tree) {
            return array();
        }

        $pdo = Database::connection();
        $categoryStmt = $pdo->query('SELECT id, parent_id, name, icon, image FROM categories ORDER BY parent_id ASC, id ASC');
        $categories = array();
        $slugIndex = array();
        while ($row = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['id'];
            $name = (string)$row['name'];
            $slug = Helpers::slugify($name);
            if ($slug === '') {
                $slug = 'kategori-' . $id;
            }
            $baseSlug = $slug;
            $suffix = 2;
            while (isset($slugIndex[$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }
            $slugIndex[$slug] = true;
            $categories[$id] = array(
                'id' => $id,
                'parent_id' => isset($row['parent_id']) ? (int)$row['parent_id'] : 0,
                'name' => $name,
                'slug' => $slug,
                'icon' => isset($row['icon']) ? (string)$row['icon'] : '',
                'image' => isset($row['image']) ? (string)$row['image'] : '',
            );
        }

        $paths = array();
        $resolvePath = function (int $categoryId) use (&$resolvePath, &$categories, &$paths) {
            if (isset($paths[$categoryId])) {
                return $paths[$categoryId];
            }
            if (!isset($categories[$categoryId])) {
                return '';
            }
            $category = $categories[$categoryId];
            $slug = $category['slug'];
            $parentId = $category['parent_id'];
            $path = $slug;
            if ($parentId > 0) {
                $parentPath = $resolvePath($parentId);
                $path = $parentPath !== '' ? $parentPath . '/' . $slug : $slug;
            }
            $paths[$categoryId] = $path;
            $categories[$categoryId]['path'] = $path;
            return $path;
        };
        foreach (array_keys($categories) as $categoryId) {
            $resolvePath($categoryId);
        }

        $mapNode = function (array $node) use (&$mapNode, &$categories) {
            $type = isset($node['type']) ? $node['type'] : 'custom';
            $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : array();
            $entry = array(
                'id' => $node['id'],
                'type' => $type,
                'title' => $node['title'],
                'name' => $node['title'],
                'target' => isset($node['target']) ? $node['target'] : '_self',
                'icon' => isset($settings['icon']) ? (string)$settings['icon'] : '',
                'image' => isset($settings['image']) ? (string)$settings['image'] : '',
                'children' => array(),
                'url' => isset($node['url']) && $node['url'] !== '' ? (string)$node['url'] : '#',
            );

            if ($type === 'category' && !empty($node['reference_key'])) {
                $categoryId = (int)$node['reference_key'];
                if (isset($categories[$categoryId])) {
                    $category = $categories[$categoryId];
                    $entry['title'] = $category['name'];
                    $entry['name'] = $category['name'];
                    $entry['slug'] = $category['slug'];
                    $entry['path'] = isset($category['path']) ? $category['path'] : $category['slug'];
                    $entry['icon'] = $category['icon'] !== '' ? $category['icon'] : $entry['icon'];
                    $entry['image'] = $category['image'] !== '' ? $category['image'] : $entry['image'];
                    $entry['url'] = Helpers::categoryUrl($entry['path']);
                }
            } elseif ($type === 'page' && !empty($node['reference_key'])) {
                $entry['url'] = Helpers::pageUrl($node['reference_key']);
            } elseif ($type === 'blog' && !empty($node['reference_key'])) {
                $entry['url'] = '/blog/' . rawurlencode($node['reference_key']);
            }

            if (!empty($node['children'])) {
                $children = array();
                foreach ($node['children'] as $child) {
                    $children[] = $mapNode($child);
                }
                $entry['children'] = $children;
            }

            return $entry;
        };

        $menu = array();
        foreach ($tree as $node) {
            $menu[] = $mapNode($node);
        }

        return $menu;
    }

    /**
     * Build footer menus grouped by column.
     *
     * @return array
     */
    public static function buildFooterMenu(): array
    {
        $tree = self::getMenuTree('footer', true);
        if (!$tree) {
            return array();
        }

        $groups = array();
        foreach ($tree as $node) {
            $title = $node['title'];
            $items = array();
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $items[] = array(
                        'title' => $child['title'],
                        'url' => isset($child['url']) && $child['url'] !== '' ? $child['url'] : '#',
                        'target' => isset($child['target']) ? $child['target'] : '_self',
                    );
                }
            }
            $groups[] = array(
                'title' => $title,
                'items' => $items,
            );
        }

        return $groups;
    }

    /**
     * Build admin menu sections filtered by user role.
     *
     * @param array|null $user
     * @return array
     */
    public static function buildAdminMenu(?array $user): array
    {
        $tree = self::getMenuTree('admin', true);
        if (!$tree) {
            return array();
        }

        $sections = array();
        foreach ($tree as $node) {
            $heading = $node['title'];
            $children = !empty($node['children']) ? $node['children'] : array();
            $items = array();
            foreach ($children as $child) {
                $mapped = self::mapAdminItem($child);
                if (!$mapped) {
                    continue;
                }

                $allowedRoles = isset($mapped['roles']) ? $mapped['roles'] : Auth::adminRoles();
                if (!Auth::userHasRole($user, $allowedRoles)) {
                    if (!empty($mapped['children'])) {
                        $visibleChildren = array();
                        foreach ($mapped['children'] as $childItem) {
                            $childRoles = isset($childItem['roles']) ? $childItem['roles'] : $allowedRoles;
                            if (Auth::userHasRole($user, $childRoles)) {
                                $visibleChildren[] = $childItem;
                            }
                        }
                        if ($visibleChildren) {
                            $mapped['children'] = $visibleChildren;
                            $items[] = $mapped;
                        }
                    }
                    continue;
                }

                if (!empty($mapped['children'])) {
                    $visibleChildren = array();
                    foreach ($mapped['children'] as $childItem) {
                        $childRoles = isset($childItem['roles']) ? $childItem['roles'] : $allowedRoles;
                        if (Auth::userHasRole($user, $childRoles)) {
                            $visibleChildren[] = $childItem;
                        }
                    }
                    if ($visibleChildren) {
                        $mapped['children'] = $visibleChildren;
                        $items[] = $mapped;
                    } elseif (!empty($mapped['href'])) {
                        unset($mapped['children']);
                        $items[] = $mapped;
                    }
                } else {
                    $items[] = $mapped;
                }
            }

            if ($items) {
                $sections[] = array(
                    'heading' => $heading,
                    'items' => $items,
                );
            }
        }

        return $sections;
    }

    /**
     * Fetch menu items for editing.
     *
     * @param int $menuId
     * @return array
     */
    private static function fetchExistingItems(int $menuId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::TABLE_ITEMS . ' WHERE menu_id = :menu');
        $stmt->execute(array('menu' => $menuId));
        $rows = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
            $rows[$row['id']] = $row;
        }
        return $rows;
    }

    /**
     * Fetch menu items for building trees.
     *
     * @param int $menuId
     * @param bool $visibleOnly
     * @return array
     */
    private static function fetchItems(int $menuId, bool $visibleOnly = false): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM ' . self::TABLE_ITEMS . ' WHERE menu_id = :menu';
        if ($visibleOnly) {
            $sql .= ' AND is_visible = 1';
        }
        $sql .= ' ORDER BY position ASC, id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array('menu' => $menuId));

        $rows = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = array(
                'id' => (int)$row['id'],
                'menu_id' => (int)$row['menu_id'],
                'parent_id' => isset($row['parent_id']) ? (int)$row['parent_id'] : 0,
                'type' => (string)$row['type'],
                'reference_key' => isset($row['reference_key']) ? (string)$row['reference_key'] : null,
                'title' => (string)$row['title'],
                'url' => isset($row['url']) ? (string)$row['url'] : '',
                'target' => isset($row['target']) ? (string)$row['target'] : '_self',
                'position' => isset($row['position']) ? (int)$row['position'] : 0,
                'is_visible' => !empty($row['is_visible']),
                'settings' => self::decodeSettings($row['settings']),
            );
        }

        return $rows;
    }

    /**
     * Resolve menu id for a location.
     *
     * @param string $location
     * @return int
     */
    private static function menuId(string $location): int
    {
        self::ensureTables();

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM ' . self::TABLE_MENUS . ' WHERE location = :location LIMIT 1');
        $stmt->execute(array('location' => $location));

        return (int)$stmt->fetchColumn();
    }

    /**
     * Decode settings payload.
     *
     * @param string|null $settings
     * @return array
     */
    private static function decodeSettings(?string $settings): array
    {
        if ($settings === null || $settings === '') {
            return array();
        }

        $decoded = json_decode($settings, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Encode settings payload.
     *
     * @param array $settings
     * @return string|null
     */
    private static function encodeSettings(array $settings): ?string
    {
        if (!$settings) {
            return null;
        }

        return json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Map raw admin node to view item.
     *
     * @param array $node
     * @return array|null
     */
    private static function mapAdminItem(array $node): ?array
    {
        $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : array();
        $item = array(
            'label' => $node['title'],
            'href' => isset($node['url']) ? (string)$node['url'] : '',
            'icon' => isset($settings['icon']) ? (string)$settings['icon'] : '',
            'pattern' => isset($settings['pattern']) ? $settings['pattern'] : '',
            'roles' => isset($settings['roles']) && is_array($settings['roles']) ? $settings['roles'] : Auth::adminRoles(),
            'children' => array(),
        );

        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $childMapped = self::mapAdminItem($child);
                if ($childMapped) {
                    $item['children'][] = $childMapped;
                }
            }
        }

        if (!$item['href'] && empty($item['children'])) {
            return null;
        }

        return $item;
    }

    /**
     * Seed admin menu with defaults.
     *
     * @param int $menuId
     * @return void
     */
    private static function seedAdminMenu(int $menuId): void
    {
        $structure = array(
            array(
                'title' => 'Genel',
                'type' => 'group',
                'children' => array(
                    array(
                        'title' => 'Anasayfa',
                        'type' => 'route',
                        'url' => '/admin/dashboard.php',
                        'settings' => array(
                            'icon' => 'bi-house',
                            'pattern' => '/admin/dashboard.php',
                            'roles' => Auth::adminRoles(),
                        ),
                    ),
                    array(
                        'title' => 'Genel Ayarlar',
                        'type' => 'route',
                        'url' => '/admin/settings-general.php',
                        'settings' => array(
                            'icon' => 'bi-sliders',
                            'pattern' => '/admin/settings-general.php',
                            'roles' => array('super_admin', 'admin'),
                        ),
                        'children' => array(
                            array(
                                'title' => 'Site Ayarları',
                                'type' => 'route',
                                'url' => '/admin/settings-general.php',
                                'settings' => array(
                                    'pattern' => '/admin/settings-general.php',
                                    'roles' => array('super_admin', 'admin'),
                                ),
                            ),
                            array(
                                'title' => 'Mail Ayarları',
                                'type' => 'route',
                                'url' => '/admin/settings-mail.php',
                                'settings' => array(
                                    'pattern' => '/admin/settings-mail.php',
                                    'roles' => array('super_admin', 'admin'),
                                ),
                            ),
                            array(
                                'title' => 'Telegram Entegrasyonu',
                                'type' => 'route',
                                'url' => '/admin/settings-telegram.php',
                                'settings' => array(
                                    'pattern' => '/admin/settings-telegram.php',
                                    'roles' => array('super_admin', 'admin'),
                                ),
                            ),
                            array(
                                'title' => 'Slider Sistemi',
                                'type' => 'route',
                                'url' => '/admin/settings-slider.php',
                                'settings' => array(
                                    'pattern' => '/admin/settings-slider.php',
                                    'roles' => array('super_admin', 'admin'),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title' => 'Ödemeler',
                        'type' => 'route',
                        'url' => '/admin/settings-payments.php',
                        'settings' => array(
                            'icon' => 'bi-credit-card',
                            'pattern' => '/admin/settings-payments.php',
                            'roles' => array('super_admin', 'admin', 'finance'),
                        ),
                        'children' => array(
                            array(
                                'title' => 'Ödeme Yöntemleri',
                                'type' => 'route',
                                'url' => '/admin/settings-payments.php',
                                'settings' => array(
                                    'pattern' => '/admin/settings-payments.php',
                                    'roles' => array('super_admin', 'admin', 'finance'),
                                ),
                            ),
                            array(
                                'title' => 'Bakiyeler',
                                'type' => 'route',
                                'url' => '/admin/balances.php',
                                'settings' => array(
                                    'pattern' => '/admin/balances.php',
                                    'roles' => array('super_admin', 'admin', 'finance'),
                                ),
                            ),
                            array(
                                'title' => 'Transfer Bildirimleri',
                                'type' => 'route',
                                'url' => '/admin/payment-notifications.php',
                                'settings' => array(
                                    'pattern' => '/admin/payment-notifications.php',
                                    'roles' => array('super_admin', 'admin', 'finance'),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title' => 'Raporlar',
                        'type' => 'route',
                        'url' => '/admin/reports.php',
                        'settings' => array(
                            'icon' => 'bi-bar-chart',
                            'pattern' => '/admin/reports.php',
                            'roles' => array('super_admin', 'admin', 'finance'),
                        ),
                    ),
                    array(
                        'title' => 'Customers',
                        'type' => 'route',
                        'url' => '/admin/users.php',
                        'settings' => array(
                            'icon' => 'bi-people',
                            'pattern' => '/admin/users.php',
                            'roles' => array('super_admin', 'admin'),
                        ),
                    ),
                    array(
                        'title' => 'Destek Talepleri',
                        'type' => 'route',
                        'url' => '/admin/support.php',
                        'settings' => array(
                            'icon' => 'bi-life-preserver',
                            'pattern' => '/admin/support.php',
                            'roles' => array('super_admin', 'admin', 'support'),
                        ),
                    ),
                ),
            ),
            array(
                'title' => 'Ürün İşlemleri',
                'type' => 'group',
                'children' => array(
                    array(
                        'title' => 'Ürünler',
                        'type' => 'route',
                        'url' => '/admin/products.php',
                        'settings' => array(
                            'icon' => 'bi-box-seam',
                            'pattern' => '/admin/products.php',
                            'roles' => array('super_admin', 'admin', 'content'),
                        ),
                    ),
                    array(
                        'title' => 'Kategoriler',
                        'type' => 'route',
                        'url' => '/admin/categories.php',
                        'settings' => array(
                            'icon' => 'bi-diagram-3',
                            'pattern' => '/admin/categories.php',
                            'roles' => array('super_admin', 'admin', 'content'),
                        ),
                    ),
                    array(
                        'title' => 'Kuponlar',
                        'type' => 'route',
                        'url' => '/admin/coupons.php',
                        'settings' => array(
                            'icon' => 'bi-ticket',
                            'pattern' => '/admin/coupons.php',
                            'roles' => array('super_admin', 'admin', 'finance'),
                        ),
                    ),
                    array(
                        'title' => 'Paketler',
                        'type' => 'route',
                        'url' => '/admin/packages.php',
                        'settings' => array(
                            'icon' => 'bi-box',
                            'pattern' => '/admin/packages.php',
                            'roles' => array('super_admin', 'admin'),
                        ),
                    ),
                    array(
                        'title' => 'Siparişler',
                        'type' => 'route',
                        'url' => '/admin/orders.php',
                        'settings' => array(
                            'icon' => 'bi-bag-check',
                            'pattern' => '/admin/orders.php',
                            'roles' => array('super_admin', 'admin', 'support'),
                        ),
                        'children' => array(
                            array(
                                'title' => 'Paket Siparişleri',
                                'type' => 'route',
                                'url' => '/admin/orders.php',
                                'settings' => array(
                                    'pattern' => '/admin/orders.php',
                                    'roles' => array('super_admin', 'admin', 'support'),
                                ),
                            ),
                            array(
                                'title' => 'Ürün Siparişleri',
                                'type' => 'route',
                                'url' => '/admin/product-orders.php',
                                'settings' => array(
                                    'pattern' => '/admin/product-orders.php',
                                    'roles' => array('super_admin', 'admin', 'support'),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title' => 'WooCommerce',
                        'type' => 'route',
                        'url' => '/admin/woocommerce-import.php',
                        'settings' => array(
                            'icon' => 'bi-cart3',
                            'pattern' => '/admin/woocommerce-(import|export)\\.php',
                            'roles' => array('super_admin', 'admin', 'content'),
                        ),
                        'children' => array(
                            array(
                                'title' => 'CSV İçe Aktar',
                                'type' => 'route',
                                'url' => '/admin/woocommerce-import.php',
                                'settings' => array(
                                    'pattern' => '/admin/woocommerce-import.php',
                                    'roles' => array('super_admin', 'admin', 'content'),
                                ),
                            ),
                            array(
                                'title' => 'CSV Dışa Aktar',
                                'type' => 'route',
                                'url' => '/admin/woocommerce-export.php',
                                'settings' => array(
                                    'pattern' => '/admin/woocommerce-export.php',
                                    'roles' => array('super_admin', 'admin', 'content'),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            array(
                'title' => 'İçerik',
                'type' => 'group',
                'children' => array(
                    array(
                        'title' => 'Blog Yazıları',
                        'type' => 'route',
                        'url' => '/admin/blog-posts.php',
                        'settings' => array(
                            'icon' => 'bi-journal-richtext',
                            'pattern' => '/admin/blog-posts.php',
                            'roles' => array('super_admin', 'admin', 'content'),
                        ),
                    ),
                    array(
                        'title' => 'Sabit Sayfalar',
                        'type' => 'route',
                        'url' => '/admin/pages.php',
                        'settings' => array(
                            'icon' => 'bi-file-earmark-text',
                            'pattern' => '/admin/pages.php',
                            'roles' => array('super_admin', 'admin', 'content'),
                        ),
                    ),
                    array(
                        'title' => 'Menü Yönetimi',
                        'type' => 'route',
                        'url' => '/admin/menus.php',
                        'settings' => array(
                            'icon' => 'bi-list-ul',
                            'pattern' => '/admin/menus.php',
                            'roles' => array('super_admin', 'admin', 'content'),
                        ),
                    ),
                ),
            ),
            array(
                'title' => 'Entegrasyonlar',
                'type' => 'group',
                'children' => array(
                    array(
                        'title' => 'Entegrasyonlar',
                        'type' => 'route',
                        'url' => '/admin/integrations-providers.php',
                        'settings' => array(
                            'icon' => 'bi-plug',
                            'pattern' => '/admin/integrations-(providers|dalle|contentbot)\\.php',
                            'roles' => array('super_admin', 'admin', 'support'),
                        ),
                        'children' => array(
                            array(
                                'title' => 'Sağlayıcı Entegrasyonlar',
                                'type' => 'route',
                                'url' => '/admin/integrations-providers.php',
                                'settings' => array(
                                    'pattern' => '/admin/integrations-providers.php',
                                    'roles' => array('super_admin', 'admin', 'support'),
                                ),
                            ),
                            array(
                                'title' => 'Dall-e Yapay Zeka',
                                'type' => 'route',
                                'url' => '/admin/integrations-dalle.php',
                                'settings' => array(
                                    'pattern' => '/admin/integrations-dalle.php',
                                    'roles' => array('super_admin', 'admin', 'support'),
                                ),
                            ),
                            array(
                                'title' => 'Makale ve Yorum Botu',
                                'type' => 'route',
                                'url' => '/admin/integrations-contentbot.php',
                                'settings' => array(
                                    'pattern' => '/admin/integrations-contentbot.php',
                                    'roles' => array('super_admin', 'admin', 'support'),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            array(
                'title' => 'Denetim',
                'type' => 'group',
                'children' => array(
                    array(
                        'title' => 'Aktivite Kayıtları',
                        'type' => 'route',
                        'url' => '/admin/activity-logs.php',
                        'settings' => array(
                            'icon' => 'bi-clipboard-data',
                            'pattern' => '/admin/activity-logs.php',
                            'roles' => array('super_admin', 'admin'),
                        ),
                    ),
                    array(
                        'title' => 'Bildirim Yönetimi',
                        'type' => 'route',
                        'url' => '/admin/notifications.php',
                        'settings' => array(
                            'icon' => 'bi-bell',
                            'pattern' => '/admin/notifications.php',
                            'roles' => array('super_admin', 'admin', 'support'),
                        ),
                    ),
                ),
            ),
        );

        self::saveMenuById($menuId, $structure);
    }

    /**
     * Seed footer columns with defaults.
     *
     * @param int $menuId
     * @return void
     */
    private static function seedFooterMenu(int $menuId): void
    {
        $structure = array(
            array(
                'title' => 'Müşteri Hizmetleri',
                'type' => 'group',
                'children' => array(
                    array('title' => 'Destek Merkezi', 'type' => 'route', 'url' => '/support.php'),
                    array('title' => 'Sipariş Takibi', 'type' => 'route', 'url' => Helpers::pageUrl('order-tracking')),
                    array('title' => 'İade Politikası', 'type' => 'page', 'url' => Helpers::pageUrl('iade')),
                    array('title' => 'Gizlilik Politikası', 'type' => 'page', 'url' => Helpers::pageUrl('gizlilik-politikasi')),
                    array('title' => 'Kullanım Şartları', 'type' => 'page', 'url' => Helpers::pageUrl('kullanim-sartlari')),
                ),
            ),
            array(
                'title' => 'Popüler Ürünler',
                'type' => 'group',
                'children' => array(
                    array('title' => 'Valorant Points', 'type' => 'route', 'url' => Helpers::categoryUrl('valorant')),
                    array('title' => 'PUBG UC', 'type' => 'route', 'url' => Helpers::categoryUrl('pubg')),
                    array('title' => 'Windows Lisansları', 'type' => 'route', 'url' => Helpers::categoryUrl('windows')),
                    array('title' => 'Tasarım Araçları', 'type' => 'route', 'url' => Helpers::categoryUrl('design-tools')),
                ),
            ),
            array(
                'title' => 'Şirket',
                'type' => 'group',
                'children' => array(
                    array('title' => 'Blog', 'type' => 'route', 'url' => '/blog'),
                    array('title' => 'Hakkımızda', 'type' => 'page', 'url' => Helpers::pageUrl('about-us')),
                    array('title' => 'Kariyer', 'type' => 'page', 'url' => Helpers::pageUrl('careers')),
                    array('title' => 'İletişim', 'type' => 'route', 'url' => '/contact.php'),
                ),
            ),
        );

        self::saveMenuById($menuId, $structure);
    }

    /**
     * Internal helper to persist seeded structure.
     *
     * @param int $menuId
     * @param array $structure
     * @return void
     */
    private static function saveMenuById(int $menuId, array $structure): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ' . self::TABLE_ITEMS . ' WHERE menu_id = :menu')->execute(array('menu' => $menuId));
            $orderCounters = array();
            $persist = function (array $node, ?int $parentId = null) use (&$persist, &$orderCounters, $pdo, $menuId) {
                $parentKey = $parentId ?: 0;
                if (!isset($orderCounters[$parentKey])) {
                    $orderCounters[$parentKey] = 0;
                }
                $orderCounters[$parentKey]++;
                $position = $orderCounters[$parentKey];

                $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : array();
                $insert = $pdo->prepare('INSERT INTO ' . self::TABLE_ITEMS . '
                    (menu_id, parent_id, type, reference_key, title, url, target, position, is_visible, settings, created_at, updated_at)
                    VALUES (:menu_id, :parent_id, :type, :reference_key, :title, :url, "_self", :position, 1, :settings, NOW(), NOW())');
                $insert->execute(array(
                    'menu_id' => $menuId,
                    'parent_id' => $parentId,
                    'type' => isset($node['type']) ? (string)$node['type'] : 'custom',
                    'reference_key' => isset($node['reference_key']) ? (string)$node['reference_key'] : null,
                    'title' => isset($node['title']) ? (string)$node['title'] : 'Menü',
                    'url' => isset($node['url']) ? (string)$node['url'] : null,
                    'position' => $position,
                    'settings' => self::encodeSettings($settings),
                ));
                $id = (int)$pdo->lastInsertId();

                if (!empty($node['children']) && is_array($node['children'])) {
                    foreach ($node['children'] as $child) {
                        $persist($child, $id);
                    }
                }
            };

            foreach ($structure as $node) {
                $persist($node, null);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}

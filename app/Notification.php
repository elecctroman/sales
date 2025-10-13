<?php

namespace App;

use App\Database;
use PDO;

class Notification
{
    private const TABLE = 'notifications';
    private const READ_TABLE = 'notification_reads';

    /**
     * Cached stats to avoid duplicate calculations within a single request.
     *
     * @var array<string,int>
     */
    private static $statsCache = array();

    /**
     * Ensure the notification tables exist.
     *
     * @return void
     */
    public static function ensureTables(): void
    {
        $pdo = Database::connection();

        $pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT NULL,
            scope ENUM('global','user') NOT NULL DEFAULT 'global',
            user_id INT DEFAULT NULL,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
            publish_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expire_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_scope (scope),
            INDEX idx_status (status),
            INDEX idx_publish_at (publish_at),
            INDEX idx_user (user_id),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS " . self::READ_TABLE . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_notification_user (notification_id, user_id),
            INDEX idx_user (user_id),
            CONSTRAINT fk_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES " . self::TABLE . " (id) ON DELETE CASCADE,
            CONSTRAINT fk_notification_reads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Fetch notifications for a user (or global guest view).
     *
     * @param int|null $userId
     * @param int $limit
     * @return array
     */
    public static function forUser(?int $userId, int $limit = 12): array
    {
        self::ensureTables();

        $pdo = Database::connection();
        $params = array(
            ':now' => date('Y-m-d H:i:s'),
        );

        $selectRead = '0';
        $join = '';
        $targetClauses = array('n.scope = "global"');

        if ($userId && $userId > 0) {
            $params[':user_id'] = $userId;
            $selectRead = 'IF(r.read_at IS NULL, 0, 1)';
            $join = 'LEFT JOIN ' . self::READ_TABLE . ' r ON r.notification_id = n.id AND r.user_id = :user_id';
            $targetClauses[] = '(n.scope = "user" AND n.user_id = :user_id)';
        }

        $targetSql = '(' . implode(' OR ', $targetClauses) . ')';

        $sql = '
            SELECT
                n.id,
                n.title,
                n.message,
                n.link,
                n.scope,
                n.user_id,
                n.status,
                n.publish_at,
                n.expire_at,
                n.created_at,
                ' . $selectRead . ' AS is_read
            FROM ' . self::TABLE . ' n
            ' . $join . '
            WHERE n.status = "published"
              AND (n.publish_at IS NULL OR n.publish_at <= :now)
              AND (n.expire_at IS NULL OR n.expire_at >= :now)
              AND ' . $targetSql . '
            ORDER BY COALESCE(n.publish_at, n.created_at) DESC
            LIMIT ' . max(1, (int)$limit);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notifications = array();
        foreach ($rows as $row) {
            $publishedAt = isset($row['publish_at']) && $row['publish_at'] !== null ? $row['publish_at'] : $row['created_at'];
            $notifications[] = array(
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'message' => (string)$row['message'],
                'link' => isset($row['link']) ? (string)$row['link'] : '',
                'scope' => (string)$row['scope'],
                'is_read' => !empty($row['is_read']),
                'published_at' => $publishedAt,
                'published_at_human' => $publishedAt ? date('d.m.Y H:i', strtotime($publishedAt)) : '',
            );
        }

        return $notifications;
    }

    /**
     * Retrieve all notifications (admin listing).
     *
     * @return array
     */
    public static function all(): array
    {
        self::ensureTables();

        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT n.*, u.name AS user_name, u.email AS user_email
            FROM ' . self::TABLE . ' n
            LEFT JOIN users u ON u.id = n.user_id
            ORDER BY n.created_at DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    }

    /**
     * @param array $payload
     * @return int
     */
    public static function create(array $payload): int
    {
        self::ensureTables();

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO ' . self::TABLE . ' (title, message, link, scope, user_id, status, publish_at, expire_at, created_at, updated_at)
            VALUES (:title, :message, :link, :scope, :user_id, :status, :publish_at, :expire_at, NOW(), NOW())
        ');
        $stmt->execute(array(
            'title' => $payload['title'],
            'message' => $payload['message'],
            'link' => $payload['link'] ?? null,
            'scope' => $payload['scope'] ?? 'global',
            'user_id' => $payload['scope'] === 'user' ? ($payload['user_id'] ?? null) : null,
            'status' => $payload['status'] ?? 'published',
            'publish_at' => $payload['publish_at'] ?? date('Y-m-d H:i:s'),
            'expire_at' => $payload['expire_at'] ?? null,
        ));

        self::$statsCache = array();

        return (int)$pdo->lastInsertId();
    }

    /**
     * Retrieve aggregate notification statistics for dashboards.
     *
     * @return array<string,int>
     */
    public static function stats(): array
    {
        if (self::$statsCache) {
            return self::$statsCache;
        }

        self::ensureTables();

        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');

        $stats = array(
            'total' => 0,
            'published' => 0,
            'draft' => 0,
            'archived' => 0,
            'scheduled' => 0,
            'expired' => 0,
            'targeted' => 0,
            'active' => 0,
        );

        $statusStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM ' . self::TABLE . ' GROUP BY status');
        $rows = $statusStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string)$row['status'] : '';
            $count = isset($row['total']) ? (int)$row['total'] : 0;
            if ($status === 'published' || $status === 'draft' || $status === 'archived') {
                $stats[$status] = $count;
                $stats['total'] += $count;
            } else {
                $stats['total'] += $count;
            }
        }

        $scheduledStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE status = "published" AND publish_at IS NOT NULL AND publish_at > :now'
        );
        $scheduledStmt->execute(array('now' => $now));
        $stats['scheduled'] = (int)$scheduledStmt->fetchColumn();

        $expiredStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE status = "published" AND expire_at IS NOT NULL AND expire_at < :now'
        );
        $expiredStmt->execute(array('now' => $now));
        $stats['expired'] = (int)$expiredStmt->fetchColumn();

        $activeStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE status = "published"'
            . ' AND (publish_at IS NULL OR publish_at <= :now)'
            . ' AND (expire_at IS NULL OR expire_at >= :now)'
        );
        $activeStmt->execute(array('now' => $now));
        $stats['active'] = (int)$activeStmt->fetchColumn();

        $targetedStmt = $pdo->query('SELECT COUNT(*) FROM ' . self::TABLE . " WHERE scope = 'user'");
        $stats['targeted'] = (int)$targetedStmt->fetchColumn();

        self::$statsCache = $stats;

        return $stats;
    }

    /**
     * Get read counts for a list of notifications.
     *
     * @param array<int,int> $notificationIds
     * @return array<int,int>
     */
    public static function readCounts(array $notificationIds): array
    {
        $ids = array();
        foreach ($notificationIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if (!$ids) {
            return array();
        }

        self::ensureTables();

        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            'SELECT notification_id, COUNT(*) AS total FROM ' . self::READ_TABLE . ' WHERE notification_id IN ('
            . $placeholders . ') GROUP BY notification_id'
        );
        $stmt->execute($ids);

        $counts = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[(int)$row['notification_id']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * Update the status of a notification.
     *
     * @param int $id
     * @param string $status
     * @return void
     */
    public static function setStatus(int $id, string $status): void
    {
        $valid = array('draft', 'published', 'archived');
        if (!in_array($status, $valid, true)) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE ' . self::TABLE . ' SET status = :status, updated_at = NOW() WHERE id = :id LIMIT 1');
        $stmt->execute(array('status' => $status, 'id' => $id));

        self::$statsCache = array();
    }

    /**
     * Delete a notification.
     *
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));

        self::$statsCache = array();
    }

    /**
     * Mark a notification as read for a user.
     *
     * @param int $notificationId
     * @param int $userId
     * @return void
     */
    public static function markRead(int $notificationId, int $userId): void
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return;
        }

        self::ensureTables();

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO ' . self::READ_TABLE . ' (notification_id, user_id, read_at, created_at)
            VALUES (:notification_id, :user_id, NOW(), NOW())
            ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)
        ');
        $stmt->execute(array(
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ));
    }

    /**
     * Mark all notifications visible to the user as read.
     *
     * @param int $userId
     * @return void
     */
    public static function markAllRead(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $notifications = self::forUser($userId, 100);
        if (!$notifications) {
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                INSERT INTO ' . self::READ_TABLE . ' (notification_id, user_id, read_at, created_at)
                VALUES (:notification_id, :user_id, NOW(), NOW())
                ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)
            ');
            foreach ($notifications as $notification) {
                if (!empty($notification['is_read'])) {
                    continue;
                }
                $stmt->execute(array(
                    'notification_id' => (int)$notification['id'],
                    'user_id' => $userId,
                ));
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
        }
    }
}

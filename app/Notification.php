<?php

namespace App;

use App\Database;
use PDO;

class Notification
{
    private const TABLE = 'notifications';
    private const READ_TABLE = 'notification_reads';

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
    /**
     * Normalise a notification record.
     *
     * @param array $row
     * @return array
     */
    private static function normaliseRow(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int)$row['id'] : 0,
            'title' => isset($row['title']) ? (string)$row['title'] : '',
            'message' => isset($row['message']) ? (string)$row['message'] : '',
            'link' => isset($row['link']) && $row['link'] !== null ? (string)$row['link'] : '',
            'scope' => isset($row['scope']) ? (string)$row['scope'] : 'global',
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'status' => isset($row['status']) ? (string)$row['status'] : 'draft',
            'publish_at' => isset($row['publish_at']) ? $row['publish_at'] : null,
            'expire_at' => isset($row['expire_at']) ? $row['expire_at'] : null,
            'created_at' => isset($row['created_at']) ? $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? $row['updated_at'] : null,
        );
    }

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

        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        $normalised = array();
        foreach ($rows as $row) {
            $item = self::normaliseRow($row);
            $item['user_name'] = isset($row['user_name']) ? (string)$row['user_name'] : '';
            $item['user_email'] = isset($row['user_email']) ? (string)$row['user_email'] : '';
            $normalised[] = $item;
        }

        return $normalised;
    }

    /**
     * Find a single notification.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        self::ensureTables();

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normaliseRow($row) : null;
    }

    /**
     * @param array $payload
     * @return int
     */
    public static function create(array $payload): int
    {
        $saved = self::save($payload);

        return isset($saved['id']) ? (int)$saved['id'] : 0;
    }

    /**
     * Create or update a notification record.
     *
     * @param array $payload
     * @return array
     */
    public static function save(array $payload): array
    {
        self::ensureTables();

        $pdo = Database::connection();

        $id = isset($payload['id']) ? (int)$payload['id'] : 0;
        $title = isset($payload['title']) ? trim((string)$payload['title']) : '';
        $message = isset($payload['message']) ? trim((string)$payload['message']) : '';
        $scopeInput = isset($payload['scope']) ? (string)$payload['scope'] : 'global';
        $scope = in_array($scopeInput, array('global', 'user'), true) ? $scopeInput : 'global';
        $statusInput = isset($payload['status']) ? (string)$payload['status'] : 'draft';
        $status = in_array($statusInput, array('draft', 'published', 'archived'), true) ? $statusInput : 'draft';
        $link = isset($payload['link']) ? trim((string)$payload['link']) : '';
        $publishAt = isset($payload['publish_at']) ? trim((string)$payload['publish_at']) : '';
        $expireAt = isset($payload['expire_at']) ? trim((string)$payload['expire_at']) : '';

        if ($title === '' || $message === '') {
            throw new \InvalidArgumentException('Bildirim başlığı ve mesajı zorunludur.');
        }

        $userId = null;
        if ($scope === 'user') {
            $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
            if ($userId <= 0) {
                throw new \InvalidArgumentException('Kullanıcı hedefli bildirimler için kullanıcı seçmelisiniz.');
            }
        }

        $link = $link !== '' ? $link : null;
        $publishAt = $publishAt !== '' ? $publishAt : ($status === 'published' ? date('Y-m-d H:i:s') : null);
        $expireAt = $expireAt !== '' ? $expireAt : null;

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE ' . self::TABLE . ' SET title = :title, message = :message, link = :link, scope = :scope, user_id = :user_id, status = :status, publish_at = :publish_at, expire_at = :expire_at, updated_at = NOW() WHERE id = :id LIMIT 1');
            $stmt->execute(array(
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'scope' => $scope,
                'user_id' => $scope === 'user' ? $userId : null,
                'status' => $status,
                'publish_at' => $publishAt,
                'expire_at' => $expireAt,
                'id' => $id,
            ));
        } else {
            $stmt = $pdo->prepare('INSERT INTO ' . self::TABLE . ' (title, message, link, scope, user_id, status, publish_at, expire_at, created_at, updated_at) VALUES (:title, :message, :link, :scope, :user_id, :status, :publish_at, :expire_at, NOW(), NOW())');
            $stmt->execute(array(
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'scope' => $scope,
                'user_id' => $scope === 'user' ? $userId : null,
                'status' => $status,
                'publish_at' => $publishAt,
                'expire_at' => $expireAt,
            ));
            $id = (int)$pdo->lastInsertId();
        }

        return self::find($id);
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

<?php

namespace App;

use App\Database;
use App\Helpers;
use PDO;
use RuntimeException;

class Auth
{
    private static $roleLabels = array(
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'finance' => 'Finance',
        'support' => 'Support',
        'content' => 'Content',
        'customer' => 'Customer',
    );

    /**
     * @return array
     */
    public static function roles()
    {
        return array_keys(self::$roleLabels);
    }

    /**
     * @return array
     */
    public static function adminRoles()
    {
        return array('super_admin', 'admin', 'finance', 'support', 'content');
    }

    /**
     * @param string $role
     * @return bool
     */
    public static function isAdminRole($role)
    {
        return in_array($role, self::adminRoles(), true);
    }

    /**
     * @param array|string $userOrRole
     * @param array|string $roles
     * @return bool
     */
    public static function userHasRole($userOrRole, $roles)
    {
        $role = is_array($userOrRole) ? (isset($userOrRole['role']) ? $userOrRole['role'] : null) : $userOrRole;

        if ($role === null) {
            return false;
        }

        if (!is_array($roles)) {
            $roles = array($roles);
        }

        return in_array($role, $roles, true);
    }

    /**
     * @param array|string $roles
     * @param string $redirect
     * @return void
     */
    public static function requireRoles($roles, $redirect = '/')
    {
        if (!is_array($roles)) {
            $roles = array($roles);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

        if (!$user || !self::userHasRole($user, $roles)) {
            Helpers::redirect($redirect);
        }
    }

    /**
     * @param array|string $actor
     * @return array
     */
    public static function assignableRoles($actor)
    {
        $role = is_array($actor) ? (isset($actor['role']) ? $actor['role'] : null) : $actor;

        if ($role === 'super_admin') {
            return self::roles();
        }

        if ($role === 'admin') {
            return array('admin', 'finance', 'support', 'content', 'customer');
        }

        if ($role === 'finance') {
            return array('finance', 'support', 'customer');
        }

        if ($role === 'support' || $role === 'content') {
            return array('support', 'content', 'customer');
        }

        return array('customer');
    }

    /**
     * @param string $role
     * @return string
     */
    public static function roleLabel($role)
    {
        return isset(self::$roleLabels[$role]) ? self::$roleLabels[$role] : ucfirst($role);
    }

    /**
     * @param string $identifier
     * @param string $password
     * @return array|null
     */
    public static function attempt($identifier, $password)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = :identifier OR name = :identifier) AND status = :status LIMIT 1');
        $stmt->execute([
            'identifier' => $identifier,
            'status' => 'active'
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            self::logUserSession((int)$user['id']);
            return $user;
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $role
     * @param float $balance
     * @return int
     */
    public static function createUser($name, $email, $password, $role = 'customer', $balance = 0)
    {
        if (!in_array($role, self::roles(), true)) {
            $role = 'customer';
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, balance, status, created_at) VALUES (:name, :email, :password_hash, :role, :balance, :status, NOW())');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'balance' => $balance,
            'status' => 'active'
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public static function findUser($userId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param string $email
     * @param string $token
     * @param string $resetUrl
     * @return void
     */
    public static function sendResetLink($email, $token, $resetUrl)
    {
        $subject = 'Şifre Sıfırlama Talebi';
        $message = "Merhaba,\n\nŞifrenizi sıfırlamak için lütfen aşağıdaki bağlantıya tıklayın:\n$resetUrl\n\nBu bağlantı 1 saat boyunca geçerlidir.\n\nSaygılarımızla.";
        Mailer::send($email, $subject, $message);
    }

    /**
     * @param string $email
     * @return string
     */
    public static function createPasswordReset($email)
    {
        $pdo = Database::connection();
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at, used) VALUES (:email, :token, :expires_at, 0)');
        $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);

        return $token;
    }

    /**
     * @param string $token
     * @return array|null
     */
    public static function validateResetToken($token)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param string $email
     * @param string $password
     * @return void
     */
    public static function resetPassword($email, $password)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE email = :email');
        $stmt->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);
    }

    /**
     * @param int $id
     * @return void
     */
    public static function markResetTokenUsed($id)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param int $userId
     * @return void
     */
    private static function logUserSession($userId)
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $pdo = Database::connection();
            $ipAddress = Helpers::ipAddress();
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? trim((string)$_SERVER['HTTP_USER_AGENT']) : null;
            $userAgent = $userAgent !== null ? substr($userAgent, 0, 250) : null;
            $parsed = self::parseUserAgent($userAgent ?: '');

            $stmt = $pdo->prepare('INSERT INTO user_sessions (user_id, ip_address, user_agent, platform, browser, created_at) VALUES (:user_id, :ip_address, :user_agent, :platform, :browser, NOW())');
            $stmt->execute([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'platform' => $parsed['platform'],
                'browser' => $parsed['browser'],
            ]);
        } catch (\Throwable $exception) {
            // Session logging should never interrupt authentication flow.
        }
    }

    /**
     * @param string $userAgent
     * @return array{platform:string,browser:string}
     */
    private static function parseUserAgent($userAgent)
    {
        $platform = 'Bilinmiyor';
        $browser = 'Bilinmiyor';
        $ua = strtolower($userAgent);

        if (strpos($ua, 'windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($ua, 'mac os') !== false || strpos($ua, 'macintosh') !== false) {
            $platform = 'macOS';
        } elseif (strpos($ua, 'iphone') !== false) {
            $platform = 'iOS';
        } elseif (strpos($ua, 'ipad') !== false) {
            $platform = 'iPadOS';
        } elseif (strpos($ua, 'android') !== false) {
            $platform = 'Android';
        } elseif (strpos($ua, 'linux') !== false) {
            $platform = 'Linux';
        }

        if (strpos($ua, 'edg') !== false) {
            $browser = 'Microsoft Edge';
        } elseif (strpos($ua, 'opr') !== false || strpos($ua, 'opera') !== false) {
            $browser = 'Opera';
        } elseif (strpos($ua, 'chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($ua, 'safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($ua, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) {
            $browser = 'Internet Explorer';
        }

        return array(
            'platform' => $platform,
            'browser' => $browser,
        );
    }
}



<?php

namespace App;

use DateTimeImmutable;
use PDO;
use RuntimeException;

class CouponService
{
    private const SESSION_KEY = 'cart_coupon';
    private const FLASH_KEY = 'cart_notice';

    /**
     * @var bool
     */
    private static $schemaEnsured = false;

    /**
     * Ensure database tables for coupons exist.
     *
     * @return void
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            self::$schemaEnsured = true;
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            description TEXT NULL,
            discount_type ENUM("fixed","percent") NOT NULL DEFAULT "fixed",
            discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT "TRY",
            min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            max_uses INT NULL,
            usage_per_user INT NULL,
            starts_at DATETIME NULL,
            expires_at DATETIME NULL,
            status ENUM("active","inactive") NOT NULL DEFAULT "inactive",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_coupons_status (status),
            INDEX idx_coupons_schedule (starts_at, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        if (!Database::tableHasColumn('coupons', 'currency')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN currency CHAR(3) NOT NULL DEFAULT "TRY" AFTER discount_value');
        }

        if (!Database::tableHasColumn('coupons', 'min_order_amount')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER currency');
        }

        if (!Database::tableHasColumn('coupons', 'max_uses')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN max_uses INT NULL AFTER min_order_amount');
        }

        if (!Database::tableHasColumn('coupons', 'usage_per_user')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN usage_per_user INT NULL AFTER max_uses');
        }

        if (!Database::tableHasColumn('coupons', 'starts_at')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN starts_at DATETIME NULL AFTER usage_per_user');
        }

        if (!Database::tableHasColumn('coupons', 'expires_at')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN expires_at DATETIME NULL AFTER starts_at');
        }

        if (!Database::tableHasColumn('coupons', 'status')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN status ENUM("active","inactive") NOT NULL DEFAULT "inactive" AFTER expires_at');
        }

        if (!Database::tableHasColumn('coupons', 'updated_at')) {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS coupon_usages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coupon_id INT NOT NULL,
            user_id INT NOT NULL,
            order_reference VARCHAR(150) NOT NULL,
            order_id INT NULL,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT "TRY",
            used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_coupon_usages_coupon (coupon_id),
            INDEX idx_coupon_usages_coupon_user (coupon_id, user_id),
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES product_orders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        if (!Database::tableHasColumn('coupon_usages', 'order_id')) {
            $pdo->exec('ALTER TABLE coupon_usages ADD COLUMN order_id INT NULL AFTER order_reference');
        }

        if (!Database::tableHasColumn('coupon_usages', 'discount_amount')) {
            $pdo->exec('ALTER TABLE coupon_usages ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER order_id');
        }

        if (!Database::tableHasColumn('coupon_usages', 'currency')) {
            $pdo->exec('ALTER TABLE coupon_usages ADD COLUMN currency CHAR(3) NOT NULL DEFAULT "TRY" AFTER discount_amount');
        }

        self::$schemaEnsured = true;
    }

    /**
     * Apply a coupon code to the active cart snapshot.
     *
     * @param string $code
     * @param int $userId
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public static function applyCode(string $code, int $userId, array $snapshot): array
    {
        self::ensureSchema();
        self::ensureSession();

        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new RuntimeException('Kupon kodu boş olamaz.');
        }

        $items = isset($snapshot['items']) && is_array($snapshot['items']) ? $snapshot['items'] : array();
        $totals = isset($snapshot['totals']) && is_array($snapshot['totals']) ? $snapshot['totals'] : array();
        $subtotal = isset($totals['subtotal_value']) ? (float)$totals['subtotal_value'] : 0.0;
        $currency = isset($totals['currency']) ? (string)$totals['currency'] : Helpers::activeCurrency();

        if ($subtotal <= 0) {
            throw new RuntimeException('Kupon uygulamak için sepetinizde ürün bulunmalıdır.');
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Kupon doğrulaması yapılamadı.');
        }

        $couponRow = self::findCouponByCode($pdo, $code);
        if (!$couponRow) {
            throw new RuntimeException('Kupon kodu bulunamadı veya aktif değil.');
        }

        $evaluation = self::evaluateCoupon($pdo, $couponRow, $userId, $subtotal, $currency);
        if (!$evaluation['valid']) {
            throw new RuntimeException($evaluation['message']);
        }

        self::storeAppliedCoupon((int)$couponRow['id'], $evaluation['coupon']['code'], $userId);

        $discountValue = (float)$evaluation['discount'];
        $formattedDiscount = Currency::format($discountValue, $currency);

        $message = sprintf('%s kuponu uygulandı.', $evaluation['coupon']['code']);
        if ($discountValue > 0) {
            $message .= ' İndirim: ' . $formattedDiscount;
        }

        return array(
            'coupon' => $evaluation['coupon'],
            'discount_value' => $discountValue,
            'discount_formatted' => $formattedDiscount,
            'message' => $message,
        );
    }

    /**
     * Remove the applied coupon from the session.
     *
     * @param string|null $message
     * @return void
     */
    public static function clear(?string $message = null): void
    {
        self::ensureSession();

        if (isset($_SESSION[self::SESSION_KEY])) {
            unset($_SESSION[self::SESSION_KEY]);
        }

        if ($message !== null && $message !== '') {
            Helpers::setFlash(self::FLASH_KEY, $message);
        }
    }

    /**
     * Return the currently applied coupon metadata from the session.
     *
     * @return array<string,mixed>|null
     */
    public static function current(): ?array
    {
        self::ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $payload = $_SESSION[self::SESSION_KEY];
        if (!isset($payload['coupon_id'], $payload['code'], $payload['user_id'])) {
            return null;
        }

        return array(
            'coupon_id' => (int)$payload['coupon_id'],
            'code' => strtoupper((string)$payload['code']),
            'user_id' => (int)$payload['user_id'],
            'applied_at' => isset($payload['applied_at']) ? (int)$payload['applied_at'] : time(),
        );
    }

    /**
     * Evaluate the currently stored coupon against the provided totals and items.
     *
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $totals
     * @param int|null $userId
     * @param bool $strict
     * @return array<string,mixed>
     */
    public static function recalculate(array $items, array $totals, ?int $userId, bool $strict)
    {
        self::ensureSchema();
        $currency = isset($totals['currency']) ? (string)$totals['currency'] : Helpers::activeCurrency();
        $subtotal = isset($totals['subtotal_value']) ? (float)$totals['subtotal_value'] : 0.0;

        $baseTotals = $totals;
        $baseTotals['currency'] = $currency;
        $baseTotals['discount_value'] = 0.0;
        $baseTotals['discount_formatted'] = Currency::format(0, $currency);
        $baseTotals['grand_total_value'] = $subtotal;
        $baseTotals['grand_total_formatted'] = Currency::format($subtotal, $currency);
        $baseTotals['coupon'] = null;
        $baseTotals['coupon_code'] = null;

        $lineTotals = array();
        $lineDiscounts = array();
        foreach ($items as $index => $item) {
            $lineValue = isset($item['line_total_value']) ? (float)$item['line_total_value'] : 0.0;
            $lineTotals[$index] = $lineValue;
            $lineDiscounts[$index] = 0.0;
        }

        $state = self::current();
        if (!$state) {
            return array(
                'totals' => $baseTotals,
                'coupon' => null,
                'discount_value' => 0.0,
                'line_totals' => $lineTotals,
                'line_discounts' => $lineDiscounts,
            );
        }

        if ($userId === null || $state['user_id'] !== $userId) {
            if ($strict) {
                throw new RuntimeException('Kupon mevcut oturum ile eşleşmiyor.');
            }

            self::clear('Kupon oturum bilgileri doğrulanamadı.');
            return array(
                'totals' => $baseTotals,
                'coupon' => null,
                'discount_value' => 0.0,
                'line_totals' => $lineTotals,
                'line_discounts' => $lineDiscounts,
            );
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            if ($strict) {
                throw new RuntimeException('Kupon doğrulaması yapılamadı.');
            }

            self::clear('Kupon doğrulaması sırasında hata oluştu.');
            return array(
                'totals' => $baseTotals,
                'coupon' => null,
                'discount_value' => 0.0,
                'line_totals' => $lineTotals,
                'line_discounts' => $lineDiscounts,
            );
        }

        $couponRow = self::findCouponById($pdo, (int)$state['coupon_id']);
        if (!$couponRow) {
            if ($strict) {
                throw new RuntimeException('Kupon artık geçerli değil.');
            }

            self::clear('Kupon artık geçerli değil.');
            return array(
                'totals' => $baseTotals,
                'coupon' => null,
                'discount_value' => 0.0,
                'line_totals' => $lineTotals,
                'line_discounts' => $lineDiscounts,
            );
        }

        $evaluation = self::evaluateCoupon($pdo, $couponRow, $userId, $subtotal, $currency);
        if (!$evaluation['valid']) {
            if ($strict) {
                throw new RuntimeException($evaluation['message']);
            }

            self::clear($evaluation['message']);
            return array(
                'totals' => $baseTotals,
                'coupon' => null,
                'discount_value' => 0.0,
                'line_totals' => $lineTotals,
                'line_discounts' => $lineDiscounts,
            );
        }

        $discountValue = (float)$evaluation['discount'];
        $grandTotal = max(0.0, $subtotal - $discountValue);
        $baseTotals['discount_value'] = $discountValue;
        $baseTotals['discount_formatted'] = Currency::format($discountValue, $currency);
        $baseTotals['grand_total_value'] = $grandTotal;
        $baseTotals['grand_total_formatted'] = Currency::format($grandTotal, $currency);
        $baseTotals['coupon_code'] = $evaluation['coupon']['code'];

        $label = self::buildCouponLabel($evaluation['coupon'], $currency);
        $couponInfo = $evaluation['coupon'];
        $couponInfo['label'] = $label;
        $couponInfo['usage_total'] = $evaluation['stats']['total'];
        $couponInfo['usage_user'] = $evaluation['stats']['user'];
        $couponInfo['discount_formatted'] = $baseTotals['discount_formatted'];
        $baseTotals['coupon'] = $couponInfo;

        if ($discountValue > 0 && $subtotal > 0) {
            $remaining = $discountValue;
            $count = count($items);
            foreach ($items as $index => $item) {
                $lineSubtotal = isset($item['line_total_value']) ? (float)$item['line_total_value'] : 0.0;
                if ($lineSubtotal <= 0) {
                    $lineTotals[$index] = 0.0;
                    $lineDiscounts[$index] = 0.0;
                    continue;
                }

                $share = $lineSubtotal / $subtotal;
                $lineDiscount = round($discountValue * $share, 2);
                if ($index === $count - 1) {
                    $lineDiscount = round($remaining, 2);
                }
                $remaining -= $lineDiscount;
                if ($lineDiscount < 0) {
                    $lineDiscount = 0.0;
                }
                $adjusted = max(0.0, $lineSubtotal - $lineDiscount);
                $lineTotals[$index] = $adjusted;
                $lineDiscounts[$index] = $lineDiscount;
            }
        }

        return array(
            'totals' => $baseTotals,
            'coupon' => $couponInfo,
            'discount_value' => $discountValue,
            'line_totals' => $lineTotals,
            'line_discounts' => $lineDiscounts,
        );
    }

    /**
     * Record the coupon usage for auditing limits.
     *
     * @param PDO $pdo
     * @param array<string,mixed> $coupon
     * @param int $userId
     * @param int|null $orderId
     * @param string $orderReference
     * @param float $discountValue
     * @param string $currency
     * @return void
     */
    public static function recordUsage(PDO $pdo, array $coupon, int $userId, ?int $orderId, string $orderReference, float $discountValue, string $currency): void
    {
        self::ensureSchema();
        $couponId = isset($coupon['id']) ? (int)$coupon['id'] : (isset($coupon['coupon_id']) ? (int)$coupon['coupon_id'] : 0);
        if ($couponId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO coupon_usages (coupon_id, user_id, order_reference, order_id, discount_amount, currency, used_at) VALUES (:coupon_id, :user_id, :order_reference, :order_id, :discount_amount, :currency, NOW())');
        $stmt->execute(array(
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'order_reference' => $orderReference,
            'order_id' => $orderId,
            'discount_amount' => max(0.0, (float)$discountValue),
            'currency' => strtoupper($currency),
        ));
    }

    /**
     * Preview the discount amount for a coupon without database access.
     *
     * @param array<string,mixed> $coupon
     * @param float $subtotal
     * @param string $cartCurrency
     * @return float
     */
    public static function previewDiscount(array $coupon, float $subtotal, string $cartCurrency): float
    {
        $subtotal = max(0.0, (float)$subtotal);
        if ($subtotal <= 0) {
            return 0.0;
        }

        $cartCurrency = strtoupper($cartCurrency);
        $couponCurrency = isset($coupon['currency']) && is_string($coupon['currency']) && $coupon['currency'] !== ''
            ? strtoupper($coupon['currency'])
            : $cartCurrency;

        $type = isset($coupon['discount_type']) && $coupon['discount_type'] === 'percent' ? 'percent' : 'fixed';
        $value = isset($coupon['discount_value']) ? (float)$coupon['discount_value'] : 0.0;

        $subtotalInCouponCurrency = Currency::convert($subtotal, $cartCurrency, $couponCurrency);
        $discountInCouponCurrency = 0.0;

        if ($type === 'percent') {
            $rate = max(0.0, min(100.0, $value));
            $discountInCouponCurrency = $subtotalInCouponCurrency * ($rate / 100);
        } else {
            $discountInCouponCurrency = max(0.0, $value);
            if ($discountInCouponCurrency > $subtotalInCouponCurrency) {
                $discountInCouponCurrency = $subtotalInCouponCurrency;
            }
        }

        $discountInCartCurrency = Currency::convert($discountInCouponCurrency, $couponCurrency, $cartCurrency);
        if ($discountInCartCurrency > $subtotal) {
            $discountInCartCurrency = $subtotal;
        }

        return round($discountInCartCurrency, 2);
    }

    /**
     * Normalise and validate the coupon row from database.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normaliseCoupon(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int)$row['id'] : 0,
            'code' => isset($row['code']) ? strtoupper((string)$row['code']) : '',
            'description' => isset($row['description']) ? (string)$row['description'] : '',
            'discount_type' => isset($row['discount_type']) && $row['discount_type'] === 'percent' ? 'percent' : 'fixed',
            'discount_value' => isset($row['discount_value']) ? (float)$row['discount_value'] : 0.0,
            'currency' => isset($row['currency']) && $row['currency'] !== '' ? strtoupper((string)$row['currency']) : Helpers::activeCurrency(),
            'min_order_amount' => isset($row['min_order_amount']) ? max(0.0, (float)$row['min_order_amount']) : 0.0,
            'max_uses' => isset($row['max_uses']) && $row['max_uses'] !== null ? max(0, (int)$row['max_uses']) : null,
            'usage_per_user' => isset($row['usage_per_user']) && $row['usage_per_user'] !== null ? max(0, (int)$row['usage_per_user']) : null,
            'starts_at' => isset($row['starts_at']) && $row['starts_at'] !== null ? (string)$row['starts_at'] : null,
            'expires_at' => isset($row['expires_at']) && $row['expires_at'] !== null ? (string)$row['expires_at'] : null,
            'status' => isset($row['status']) ? (string)$row['status'] : 'inactive',
        );
    }

    /**
     * Build a human readable label for the coupon.
     *
     * @param array<string,mixed> $coupon
     * @param string $cartCurrency
     * @return string
     */
    private static function buildCouponLabel(array $coupon, string $cartCurrency): string
    {
        if ($coupon['discount_type'] === 'percent') {
            $value = number_format((float)$coupon['discount_value'], 2, '.', '');
            $value = rtrim(rtrim($value, '0'), '.');
            return $value . '%';
        }

        $amount = Currency::convert((float)$coupon['discount_value'], $coupon['currency'], $cartCurrency);
        return Currency::format($amount, $cartCurrency);
    }

    /**
     * Persist coupon metadata to the session.
     *
     * @param int $couponId
     * @param string $code
     * @param int $userId
     * @return void
     */
    private static function storeAppliedCoupon(int $couponId, string $code, int $userId): void
    {
        self::ensureSession();

        $_SESSION[self::SESSION_KEY] = array(
            'coupon_id' => $couponId,
            'code' => strtoupper($code),
            'user_id' => $userId,
            'applied_at' => time(),
        );
    }

    /**
     * Fetch a coupon by code.
     *
     * @param PDO $pdo
     * @param string $code
     * @return array<string,mixed>|null
     */
    private static function findCouponByCode(PDO $pdo, string $code): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE UPPER(code) = :code LIMIT 1');
        $stmt->execute(array('code' => strtoupper($code)));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Fetch a coupon by id.
     *
     * @param PDO $pdo
     * @param int $couponId
     * @return array<string,mixed>|null
     */
    private static function findCouponById(PDO $pdo, int $couponId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $couponId));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Validate a coupon against limits and schedule.
     *
     * @param PDO $pdo
     * @param array<string,mixed> $couponRow
     * @param int $userId
     * @param float $subtotal
     * @param string $currency
     * @return array<string,mixed>
     */
    private static function evaluateCoupon(PDO $pdo, array $couponRow, int $userId, float $subtotal, string $currency): array
    {
        $coupon = self::normaliseCoupon($couponRow);

        if ($coupon['id'] <= 0 || $coupon['code'] === '') {
            return array('valid' => false, 'message' => 'Kupon bulunamadı.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => 0, 'user' => 0));
        }

        if ($coupon['status'] !== 'active') {
            return array('valid' => false, 'message' => 'Kupon şu anda aktif değil.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => 0, 'user' => 0));
        }

        $now = new DateTimeImmutable('now');
        if (!empty($coupon['starts_at'])) {
            $start = new DateTimeImmutable($coupon['starts_at']);
            if ($now < $start) {
                return array('valid' => false, 'message' => 'Kupon henüz kullanıma açılmadı.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => 0, 'user' => 0));
            }
        }

        if (!empty($coupon['expires_at'])) {
            $end = new DateTimeImmutable($coupon['expires_at']);
            if ($now > $end) {
                return array('valid' => false, 'message' => 'Kupon süresi doldu.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => 0, 'user' => 0));
            }
        }

        $totalUsage = self::countUsage($pdo, $coupon['id'], null);
        $userUsage = self::countUsage($pdo, $coupon['id'], $userId);

        if ($coupon['max_uses'] !== null && $totalUsage >= $coupon['max_uses']) {
            return array('valid' => false, 'message' => 'Kupon kullanım limiti doldu.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => $totalUsage, 'user' => $userUsage));
        }

        if ($coupon['usage_per_user'] !== null && $userUsage >= $coupon['usage_per_user']) {
            return array('valid' => false, 'message' => 'Bu kuponu daha fazla kullanamazsınız.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => $totalUsage, 'user' => $userUsage));
        }

        if ($coupon['min_order_amount'] > 0) {
            $minimumCart = Currency::convert($coupon['min_order_amount'], $coupon['currency'], $currency);
            if ($subtotal + 0.0001 < $minimumCart) {
                $message = sprintf('Kuponu kullanmak için en az %s tutarında alışveriş yapmalısınız.', Currency::format($minimumCart, $currency));
                return array('valid' => false, 'message' => $message, 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => $totalUsage, 'user' => $userUsage));
            }
        }

        $discountValue = self::previewDiscount($coupon, $subtotal, $currency);
        if ($discountValue <= 0) {
            return array('valid' => false, 'message' => 'Kupon indirimi mevcut sepet tutarına uygulanamıyor.', 'discount' => 0.0, 'coupon' => $coupon, 'stats' => array('total' => $totalUsage, 'user' => $userUsage));
        }

        return array(
            'valid' => true,
            'message' => '',
            'discount' => $discountValue,
            'coupon' => $coupon,
            'stats' => array('total' => $totalUsage, 'user' => $userUsage),
        );
    }

    /**
     * Count coupon usages.
     *
     * @param PDO $pdo
     * @param int $couponId
     * @param int|null $userId
     * @return int
     */
    private static function countUsage(PDO $pdo, int $couponId, ?int $userId): int
    {
        self::ensureSchema();
        if ($couponId <= 0) {
            return 0;
        }

        if ($userId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = :coupon_id AND user_id = :user_id');
            $stmt->execute(array('coupon_id' => $couponId, 'user_id' => $userId));
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = :coupon_id');
            $stmt->execute(array('coupon_id' => $couponId));
        }

        return (int)$stmt->fetchColumn();
    }

    /**
     * Ensure the PHP session is active.
     *
     * @return void
     */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

<?php

namespace App;

use Throwable;

class Cart
{
    private const SESSION_KEY = 'cart_items';

    /**
     * Ensure the PHP session is available.
     *
     * @return void
     */
    private static function ensureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Retrieve cart items from the session.
     *
     * @return array<int, array<string,mixed>>
     */
    private static function getItems()
    {
        self::ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = array();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Persist cart items back to the session.
     *
     * @param array<int, array<string,mixed>> $items
     * @return void
     */
    private static function setItems(array $items)
    {
        self::ensureSession();
        $_SESSION[self::SESSION_KEY] = $items;
    }

    /**
     * Produce a snapshot of the cart items and totals prepared for templates / API output.
     *
     * @return array<string,mixed>
     */
    public static function snapshot()
    {
        $items = self::getItems();
        $items = self::recalculateLineTotals($items);
        $itemsList = array_values($items);
        $totals = self::calculateTotals($itemsList);

        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

        try {
            $couponContext = CouponService::recalculate($itemsList, $totals, $userId, false);
            $totals = $couponContext['totals'];
        } catch (Throwable $couponException) {
            self::logEvent('coupon_evaluation_error', array('error' => $couponException->getMessage()));
            CouponService::clear();
        }

        return array(
            'items' => $itemsList,
            'totals' => $totals,
        );
    }

    /**
     * @param int $productId
     * @param int $quantity
     * @return array<string,mixed>
     */
    public static function add($productId, $quantity = 1)
    {
        $quantity = max(1, (int)$quantity);
        $product = self::loadProduct($productId);
        if (!$product) {
            self::logEvent('product_not_found', array('product_id' => (int)$productId));
            throw new \RuntimeException('Urun bulunamadi veya siparise kapali.');
        }

        if (!$product['in_stock']) {
            self::logEvent('product_out_of_stock', array('product_id' => (int)$productId));
            throw new \RuntimeException('Bu urun su anda siparise kapali.');
        }

        $items = self::getItems();

        if (isset($items[$productId])) {
            $items[$productId]['quantity'] += $quantity;
        } else {
            $product['quantity'] = $quantity;
            $items[$productId] = $product;
        }

        $items = self::recalculateLineTotals($items);
        self::setItems($items);
        self::logEvent('product_added', array(
            'product_id' => (int)$productId,
            'quantity' => (int)$items[$productId]['quantity'],
        ));

        return self::snapshot();
    }

    /**
     * @param int $productId
     * @param int $quantity

     */
    public static function update($productId, $quantity)
    {
        $items = self::getItems();
        if (!isset($items[$productId])) {
            throw new \RuntimeException('Urun sepetinizde bulunamadi.');
        }

        $quantity = (int)$quantity;

        if ($quantity <= 0) {
            unset($items[$productId]);
            self::logEvent('product_removed_by_update', array('product_id' => (int)$productId));
        } else {
            $items[$productId]['quantity'] = $quantity;
            self::logEvent('product_quantity_updated', array(
                'product_id' => (int)$productId,
                'quantity' => $quantity,
            ));
        }

        $items = self::recalculateLineTotals($items);
        self::setItems($items);

        return self::snapshot();
    }

    /**
     * @param int $productId
     * @return array<string,mixed>
     */
    public static function remove($productId)
    {
        $items = self::getItems();
        if (isset($items[$productId])) {
            unset($items[$productId]);
            self::setItems(self::recalculateLineTotals($items));
            self::logEvent('product_removed', array('product_id' => (int)$productId));
        } else {
            self::logEvent('product_remove_missing', array('product_id' => (int)$productId));
        }

        return self::snapshot();
    }

    /**
     * @return array<string,mixed>
     */
    public static function clear()
    {
        self::setItems(array());
        self::logEvent('cart_cleared');
        CouponService::clear();
        return self::snapshot();
    }

    /**
     * @param array<int, array<string,mixed>> $items
     * @return array<int, array<string,mixed>>
     */
    private static function recalculateLineTotals(array $items)
    {
        $activeCurrency = Helpers::activeCurrency();

        foreach ($items as &$item) {
            $unit = Currency::convert(
                (float)$item['price_value'],
                (string)$item['price_currency'],
                $activeCurrency
            );

            $lineTotal = $unit * (int)$item['quantity'];
            $item['price_formatted'] = Helpers::formatCurrency(
                (float)$item['price_value'],
                (string)$item['price_currency']
            );
            $item['line_total_value'] = $lineTotal;
            $item['line_total_formatted'] = Currency::format($lineTotal, $activeCurrency);
        }
        unset($item);

        return $items;
    }

    /**
     * @param array<int, array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private static function calculateTotals(array $items)
    {
        $activeCurrency = Helpers::activeCurrency();
        $total = 0.0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            $unit = Currency::convert(
                (float)$item['price_value'],
                (string)$item['price_currency'],
                $activeCurrency
            );
            $total += $unit * (int)$item['quantity'];
            $totalQuantity += (int)$item['quantity'];
        }

        return array(
            'currency' => $activeCurrency,
            'total_items' => count($items),
            'total_quantity' => $totalQuantity,
            'subtotal_value' => $total,
            'subtotal_formatted' => Currency::format($total, $activeCurrency),
            'is_empty' => count($items) === 0,
        );
    }

    /**
     * Load product data for cart usage.
     *
     * @param int $productId
     * @return array<string,mixed>|null
     */
    private static function loadProduct($productId)
    {
        $productId = (int)$productId;
        if ($productId <= 0) {
            return null;
        }

        try {
            $pdo = Database::connection();
        } catch (\Throwable $connectionException) {
            self::logEvent('database_connection_error', array(
                'product_id' => $productId,
                'error' => $connectionException->getMessage(),
            ));
            return null;
        }

        try {
            $supportsShortDescription = Database::tableHasColumn('products', 'short_description');
            $supportsImageUrl = Database::tableHasColumn('products', 'image_url');
            $supportsViewsCount = Database::tableHasColumn('products', 'views_count');

            $columns = array(
                'p.id',
                'p.name',
                'p.category_id',
                'p.description',
                'p.cost_price_try',
                'p.price',
                'p.status',
            );

            $columns[] = $supportsShortDescription ? 'p.short_description' : 'NULL AS short_description';
            $columns[] = $supportsImageUrl ? 'p.image_url' : 'NULL AS image_url';
            $columns[] = $supportsViewsCount ? 'p.views_count' : '0 AS views_count';

            $stmt = $pdo->prepare('
                SELECT ' . implode(',', $columns) . ',
                    c.image AS category_image
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.id = :id
                LIMIT 1
            ');
            $stmt->execute(array('id' => $productId));
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $queryException) {
            self::logEvent('product_lookup_error', array(
                'product_id' => $productId,
                'error' => $queryException->getMessage(),
            ));
            return null;
        }

        if (!$row) {
            self::logEvent('product_lookup_empty', array('product_id' => $productId));
            return null;
        }

        $status = isset($row['status']) ? (string)$row['status'] : 'inactive';
        $inStock = $status === 'active';

        $priceCurrency = 'USD';
        $priceValue = isset($row['price']) ? (float)$row['price'] : 0.0;
        if (isset($row['cost_price_try']) && (float)$row['cost_price_try'] > 0) {
            $priceCurrency = 'TRY';
            $priceValue = (float)$row['cost_price_try'];
        }

        $image = '/theme/assets/images/placeholder.png';
        if (!empty($row['image_url'])) {
            $image = (string)$row['image_url'];
        } elseif (!empty($row['category_image'])) {
            $image = (string)$row['category_image'];
        }

        return array(
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'image' => $image,
            'description' => isset($row['description']) ? (string)$row['description'] : null,
            'short_description' => isset($row['short_description']) ? (string)$row['short_description'] : null,
            'price_value' => $priceValue,
            'price_currency' => $priceCurrency,
            'price_formatted' => Helpers::formatCurrency($priceValue, $priceCurrency),
            'status' => $status,
            'in_stock' => $inStock,
        );
    }

    /**
     * @param string $event
     * @param array<string,mixed> $context
     * @return void
     */
    private static function logEvent($event, array $context = array())
    {
        self::ensureSession();

        $baseDir = dirname(__DIR__);
        $logDir = $baseDir . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        if (!is_dir($logDir) || !is_writable($logDir)) {
            return;
        }

        $entry = array(
            'time' => date('Y-m-d H:i:s'),
            'event' => (string)$event,
            'session' => session_id(),
        );

        if ($context) {
            $entry['context'] = $context;
        }

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = sprintf(
                '{"time":"%s","event":"%s","session":"%s"}',
                $entry['time'],
                addslashes($event),
                addslashes((string)$entry['session'])
            );
        }

        @file_put_contents($logDir . '/cart.log', $line . PHP_EOL, FILE_APPEND);
    }
}

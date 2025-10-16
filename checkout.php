<?php
require __DIR__ . '/bootstrap.php';

use App\AuditLog;
use App\Cart;
use App\Database;
use App\Helpers;
use App\CouponService;

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'error' => 'Method not allowed.',
    ));
    return;
}

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'error' => 'Oturum acmaniz gerekiyor.',
    ));
    return;
}

$csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
if (!Helpers::verifyCsrf($csrfToken)) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'error' => 'Gecersiz istek. Lutfen sayfayi yenileyip tekrar deneyin.',
    ));
    return;
}

$snapshot = Cart::snapshot();
$items = isset($snapshot['items']) && is_array($snapshot['items']) ? $snapshot['items'] : array();
$totals = isset($snapshot['totals']) && is_array($snapshot['totals']) ? $snapshot['totals'] : array();

if (!$items) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'error' => 'Sepetinizde urun bulunmuyor.',
    ));
    return;
}

$userId = (int)$_SESSION['user']['id'];
$currency = isset($totals['currency']) ? (string)$totals['currency'] : Helpers::activeCurrency();

try {
    $couponEvaluation = CouponService::recalculate($items, $totals, $userId, true);
} catch (\RuntimeException $couponProblem) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'error' => $couponProblem->getMessage(),
    ));
    return;
} catch (\Throwable $couponError) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Kupon doğrulaması tamamlanamadı.',
    ));
    return;
}

$totals = $couponEvaluation['totals'];
$currency = isset($totals['currency']) ? (string)$totals['currency'] : $currency;
$totalValue = isset($totals['grand_total_value'])
    ? (float)$totals['grand_total_value']
    : (isset($totals['subtotal_value']) ? (float)$totals['subtotal_value'] : 0.0);
$discountValue = isset($totals['discount_value']) ? (float)$totals['discount_value'] : 0.0;
$appliedCoupon = isset($couponEvaluation['coupon']) ? $couponEvaluation['coupon'] : null;
$lineTotals = isset($couponEvaluation['line_totals']) && is_array($couponEvaluation['line_totals']) ? $couponEvaluation['line_totals'] : array();
$lineDiscounts = isset($couponEvaluation['line_discounts']) && is_array($couponEvaluation['line_discounts']) ? $couponEvaluation['line_discounts'] : array();

$paymentMethod = isset($_POST['payment_method']) ? strtolower(trim((string)$_POST['payment_method'])) : 'card';
$paymentOption = isset($_POST['payment_option']) ? strtolower(trim((string)$_POST['payment_option'])) : '';
if ($paymentOption !== '') {
    $paymentMethod = $paymentOption;
}
$allowedMethods = array('card', 'balance', 'eft', 'crypto');
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $paymentMethod = 'card';
}

$customerDetails = array(
    'first_name' => isset($_POST['first_name']) ? trim((string)$_POST['first_name']) : '',
    'last_name' => isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '',
    'email' => isset($_POST['email']) ? trim((string)$_POST['email']) : '',
    'phone' => isset($_POST['phone']) ? trim((string)$_POST['phone']) : '',
);

$pdo = Database::connection();

$orderReference = strtoupper(bin2hex(random_bytes(4)));
$orderIds = array();

try {
    $pdo->beginTransaction();

    $metadataTemplate = array(
        'payment_method' => $paymentMethod,
        'currency' => $currency,
        'customer' => $customerDetails,
        'reference' => $orderReference,
    );

    if ($appliedCoupon) {
        $metadataTemplate['coupon'] = array(
            'code' => $appliedCoupon['code'],
            'discount' => $discountValue,
            'label' => isset($appliedCoupon['label']) ? $appliedCoupon['label'] : null,
            'type' => isset($appliedCoupon['discount_type']) ? $appliedCoupon['discount_type'] : null,
        );
    }

    $orderInsert = $pdo->prepare('INSERT INTO product_orders (product_id, user_id, api_token_id, quantity, note, price, status, source, external_reference, external_metadata, created_at) VALUES (:product_id, :user_id, :api_token_id, :quantity, :note, :price, :status, :source, :external_reference, :external_metadata, NOW())');

    if ($paymentMethod === 'balance') {
        $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id FOR UPDATE');
        $userStmt->execute(array('id' => $userId));
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow) {
            throw new \RuntimeException('Kullanici bilgileri bulunamadi.');
        }

        $currentBalance = isset($userRow['balance']) ? (float)$userRow['balance'] : 0.0;
        if ($currentBalance + 0.0001 < $totalValue) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(array(
                'success' => false,
                'error' => 'Bakiyeniz bu siparisi karsilamak icin yetersiz.',
            ));
            return;
        }

        foreach ($items as $index => $item) {
            $productId = isset($item['id']) ? (int)$item['id'] : 0;
            if ($productId <= 0) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $originalLineTotal = isset($item['line_total_value']) ? (float)$item['line_total_value'] : 0.0;
            $lineTotal = isset($lineTotals[$index]) ? (float)$lineTotals[$index] : $originalLineTotal;
            $itemDiscount = isset($lineDiscounts[$index]) ? (float)$lineDiscounts[$index] : max(0.0, $originalLineTotal - $lineTotal);

            $itemMetadata = $metadataTemplate;
            if ($appliedCoupon) {
                $itemMetadata['coupon'] = array(
                    'code' => $appliedCoupon['code'],
                    'discount' => $itemDiscount,
                    'type' => isset($appliedCoupon['discount_type']) ? $appliedCoupon['discount_type'] : null,
                    'label' => isset($appliedCoupon['label']) ? $appliedCoupon['label'] : null,
                );
            }
            $itemMetadata['line'] = array(
                'quantity' => $quantity,
                'unit_price' => isset($item['price_value']) ? (float)$item['price_value'] : 0.0,
                'subtotal' => $originalLineTotal,
                'discount' => $itemDiscount,
                'total' => $lineTotal,
            );

            $orderInsert->execute(array(
                'product_id' => $productId,
                'user_id' => $userId,
                'api_token_id' => null,
                'quantity' => $quantity,
                'note' => null,
                'price' => $lineTotal,
                'status' => 'processing',
                'source' => 'panel',
                'external_reference' => $orderReference,
                'external_metadata' => json_encode($itemMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));

            $orderIds[] = (int)$pdo->lastInsertId();
        }

        if ($appliedCoupon) {
            $firstOrderId = $orderIds ? $orderIds[0] : null;
            CouponService::recordUsage($pdo, $appliedCoupon, $userId, $firstOrderId, $orderReference, $discountValue, $currency);
        }

        $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id')->execute(array(
            'amount' => $totalValue,
            'id' => $userId,
        ));

        $pdo->prepare('INSERT INTO balance_transactions (user_id, amount, type, description, created_at) VALUES (:user_id, :amount, :type, :description, NOW())')->execute(array(
            'user_id' => $userId,
            'amount' => $totalValue,
            'type' => 'debit',
            'description' => 'Sepet odemesi #' . $orderReference,
        ));

        $remainingBalance = $currentBalance - $totalValue;
        $_SESSION['user']['balance'] = $remainingBalance;

        $pdo->commit();

        Cart::clear();

        AuditLog::record($userId, 'orders.checkout.balance', 'product_order', $orderIds ? $orderIds[0] : null, 'Sepet bakiyeyle odendi (' . $orderReference . ')');

        $redirect = '/payment-success.php?method=balance&orders=' . implode(',', $orderIds)
            . '&reference=' . urlencode($orderReference)
            . '&balance=' . urlencode(number_format($remainingBalance, 2, '.', ''));

        if ($appliedCoupon) {
            $redirect .= '&coupon=' . urlencode($appliedCoupon['code']);
            if ($discountValue > 0) {
                $redirect .= '&discount=' . urlencode(number_format($discountValue, 2, '.', ''));
            }
        }

        echo json_encode(array(
            'success' => true,
            'redirect' => $redirect,
            'remaining_balance' => $remainingBalance,
        ));
        return;
    }

    $status = 'pending';
    foreach ($items as $index => $item) {
        $productId = isset($item['id']) ? (int)$item['id'] : 0;
        if ($productId <= 0) {
            continue;
        }

        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $originalLineTotal = isset($item['line_total_value']) ? (float)$item['line_total_value'] : 0.0;
        $lineTotal = isset($lineTotals[$index]) ? (float)$lineTotals[$index] : $originalLineTotal;
        $itemDiscount = isset($lineDiscounts[$index]) ? (float)$lineDiscounts[$index] : max(0.0, $originalLineTotal - $lineTotal);

        $itemMetadata = $metadataTemplate;
        if ($appliedCoupon) {
            $itemMetadata['coupon'] = array(
                'code' => $appliedCoupon['code'],
                'discount' => $itemDiscount,
                'type' => isset($appliedCoupon['discount_type']) ? $appliedCoupon['discount_type'] : null,
                'label' => isset($appliedCoupon['label']) ? $appliedCoupon['label'] : null,
            );
        }
        $itemMetadata['line'] = array(
            'quantity' => $quantity,
            'unit_price' => isset($item['price_value']) ? (float)$item['price_value'] : 0.0,
            'subtotal' => $originalLineTotal,
            'discount' => $itemDiscount,
            'total' => $lineTotal,
        );

        $orderInsert->execute(array(
            'product_id' => $productId,
            'user_id' => $userId,
            'api_token_id' => null,
            'quantity' => $quantity,
            'note' => null,
            'price' => $lineTotal,
            'status' => $status,
            'source' => 'panel',
            'external_reference' => $orderReference,
            'external_metadata' => json_encode($itemMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));

        $orderIds[] = (int)$pdo->lastInsertId();
    }

    if ($appliedCoupon) {
        $firstOrderId = $orderIds ? $orderIds[0] : null;
        CouponService::recordUsage($pdo, $appliedCoupon, $userId, $firstOrderId, $orderReference, $discountValue, $currency);
    }

    $pdo->commit();

    Cart::clear();

    AuditLog::record($userId, 'orders.checkout.' . $paymentMethod, 'product_order', $orderIds ? $orderIds[0] : null, 'Sepet odeme yontemi: ' . $paymentMethod . ' (' . $orderReference . ')');

    $redirect = '/payment-success.php?method=' . urlencode($paymentMethod)
        . '&orders=' . implode(',', $orderIds)
        . '&reference=' . urlencode($orderReference);

    if ($appliedCoupon) {
        $redirect .= '&coupon=' . urlencode($appliedCoupon['code']);
        if ($discountValue > 0) {
            $redirect .= '&discount=' . urlencode(number_format($discountValue, 2, '.', ''));
        }
    }

    echo json_encode(array(
        'success' => true,
        'redirect' => $redirect,
    ));
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Odeme islenemedi: ' . $exception->getMessage(),
    ));
}

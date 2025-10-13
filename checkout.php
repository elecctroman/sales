<?php
require __DIR__ . '/bootstrap.php';

use App\AuditLog;
use App\Cart;
use App\Database;
use App\Helpers;

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

$totalValue = isset($totals['subtotal_value']) ? (float)$totals['subtotal_value'] : 0.0;
$currency = isset($totals['currency']) ? (string)$totals['currency'] : Helpers::activeCurrency();

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

$userId = (int)$_SESSION['user']['id'];
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

        foreach ($items as $item) {
            $productId = isset($item['id']) ? (int)$item['id'] : 0;
            if ($productId <= 0) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $lineTotal = isset($item['line_total_value']) ? (float)$item['line_total_value'] : 0.0;

            $itemMetadata = $metadataTemplate;
            $itemMetadata['line'] = array(
                'quantity' => $quantity,
                'unit_price' => isset($item['price_value']) ? (float)$item['price_value'] : 0.0,
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

        $paymentSuccessBase = Helpers::routeUrl('payment.success') ?: '/odeme/basarili/';
        $balanceRedirect = Helpers::urlWithQuery($paymentSuccessBase, array(
            'method' => 'balance',
            'orders' => implode(',', $orderIds),
            'reference' => $orderReference,
            'balance' => number_format($remainingBalance, 2, '.', ''),
        ));

        echo json_encode(array(
            'success' => true,
            'redirect' => $balanceRedirect,
            'remaining_balance' => $remainingBalance,
        ));
        return;
    }

    $status = 'pending';
    foreach ($items as $item) {
        $productId = isset($item['id']) ? (int)$item['id'] : 0;
        if ($productId <= 0) {
            continue;
        }

        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $lineTotal = isset($item['line_total_value']) ? (float)$item['line_total_value'] : 0.0;

        $itemMetadata = $metadataTemplate;
        $itemMetadata['line'] = array(
            'quantity' => $quantity,
            'unit_price' => isset($item['price_value']) ? (float)$item['price_value'] : 0.0,
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

    $pdo->commit();

    Cart::clear();

    AuditLog::record($userId, 'orders.checkout.' . $paymentMethod, 'product_order', $orderIds ? $orderIds[0] : null, 'Sepet odeme yontemi: ' . $paymentMethod . ' (' . $orderReference . ')');

    $paymentSuccessBase = Helpers::routeUrl('payment.success') ?: '/odeme/basarili/';
    $redirectUrl = Helpers::urlWithQuery($paymentSuccessBase, array(
        'method' => $paymentMethod,
        'orders' => implode(',', $orderIds),
        'reference' => $orderReference,
    ));

    echo json_encode(array(
        'success' => true,
        'redirect' => $redirectUrl,
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

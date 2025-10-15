<?php
require __DIR__ . '/bootstrap.php';

use App\Notification;

header('Content-Type: application/json; charset=UTF-8');

$sessionUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$userId = $sessionUser ? (int)$sessionUser['id'] : null;

try {
    Notification::ensureTables();
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Notifications service is not available.',
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $items = Notification::forUser($userId);
    echo json_encode(array(
        'success' => true,
        'items' => $items,
        'unread' => array_reduce($items, function ($carry, $item) {
            return $carry + (!empty($item['is_read']) ? 0 : 1);
        }, 0),
    ));
    exit;
}

$requiresAuth = !$sessionUser || $userId <= 0;
if ($requiresAuth) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'error' => 'Authentication required.',
    ));
    exit;
}

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
if (!is_array($payload)) {
    $payload = array();
}

$action = isset($payload['action']) ? (string)$payload['action'] : '';

switch ($action) {
    case 'mark-read':
        $notificationId = isset($payload['id']) ? (int)$payload['id'] : 0;
        if ($notificationId <= 0) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'error' => 'Invalid notification id.'));
            exit;
        }
        Notification::markRead($notificationId, $userId);
        echo json_encode(array('success' => true));
        exit;
    case 'mark-all':
        Notification::markAllRead($userId);
        echo json_encode(array('success' => true));
        exit;
    default:
        http_response_code(400);
        echo json_encode(array('success' => false, 'error' => 'Unsupported action.'));
        exit;
}

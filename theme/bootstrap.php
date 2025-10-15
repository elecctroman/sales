<?php

use App\Notification;

if (!function_exists('theme_render')) {
    function theme_render(string $view, array $data = []): void
    {
        $viewPath = __DIR__ . '/pages/' . $view . '.php';
        if (!file_exists($viewPath)) {
            http_response_code(404);
            $viewPath = __DIR__ . '/pages/404.php';
            $data['pageTitle'] = $data['pageTitle'] ?? 'Not Found';
        }

        extract($data, EXTR_SKIP);
        $pageTitle = $data['pageTitle'] ?? ($view === 'index' ? 'Home' : ucfirst($view));
        $viewFile = $viewPath;

        $notificationsData = array();
        if (isset($data['notifications']) && is_array($data['notifications'])) {
            $notificationsData = $data['notifications'];
        } else {
            $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
            try {
                $notificationsData = Notification::forUser($userId);
            } catch (\Throwable $notificationException) {
                $notificationsData = array();
            }
        }
        $GLOBALS['theme_notifications'] = $notificationsData;

        include __DIR__ . '/layouts/base.php';
    }
}

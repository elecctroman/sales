<?php
use App\Helpers;

$navItems = isset($GLOBALS['theme_nav_categories']) ? $GLOBALS['theme_nav_categories'] : array();
$cartSummary = isset($GLOBALS['theme_cart_summary']) ? $GLOBALS['theme_cart_summary'] : array();
$cartTotals = isset($cartSummary['totals']) ? $cartSummary['totals'] : array();
$cartCount = isset($cartTotals['total_quantity']) ? (int)$cartTotals['total_quantity'] : 0;

if (!$navItems) {
    $navItems = array(
        array('title' => 'PUBG', 'url' => Helpers::categoryUrl('pubg'), 'type' => 'category', 'icon' => '', 'image' => '', 'children' => array()),
        array('title' => 'Valorant', 'url' => Helpers::categoryUrl('valorant'), 'type' => 'category', 'icon' => '', 'image' => '', 'children' => array()),
        array('title' => 'Windows', 'url' => Helpers::categoryUrl('windows'), 'type' => 'category', 'icon' => '', 'image' => '', 'children' => array()),
        array('title' => 'Semrush', 'url' => Helpers::categoryUrl('semrush'), 'type' => 'category', 'icon' => '', 'image' => '', 'children' => array()),
        array('title' => 'Adobe', 'url' => Helpers::categoryUrl('adobe'), 'type' => 'category', 'icon' => '', 'image' => '', 'children' => array()),
    );
}

$isLoggedInHeader = !empty($_SESSION['user']);
$currentUser = $isLoggedInHeader ? $_SESSION['user'] : null;
$userName = $currentUser && isset($currentUser['name']) ? trim((string)$currentUser['name']) : '';
$userParts = $userName !== '' ? preg_split('/\s+/', $userName) : array();
$userFirst = $userParts ? array_shift($userParts) : '';
$userLast = $userParts ? implode(' ', $userParts) : '';

$initialsBuilder = function (string $value): string {
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($value, 0, 1));
};

$userInitials = $initialsBuilder($userFirst);
if ($userLast !== '') {
    $userInitials .= $initialsBuilder($userLast);
}
if ($userInitials === '' && $userName !== '') {
    $userInitials = $initialsBuilder($userName);
}

$userBalance = $currentUser && isset($currentUser['balance']) ? (float)$currentUser['balance'] : 0.0;
$userFormattedBalance = number_format($userBalance, 2, ',', '.');
$displayName = trim($userFirst . ' ' . $userLast) !== '' ? trim($userFirst . ' ' . $userLast) : $userName;
$notifications = isset($GLOBALS['theme_notifications']) && is_array($GLOBALS['theme_notifications']) ? $GLOBALS['theme_notifications'] : array();
$unreadNotifications = 0;
foreach ($notifications as $notificationItem) {
    if (empty($notificationItem['is_read'])) {
        $unreadNotifications++;
    }
}
?>
<header class="site-header">
    <div class="site-header__main">
        <a class="site-header__brand" href="/">OyunHesap<span>.com.tr</span></a>
        <form class="site-header__search" action="/kategori/">
            <input type="search" name="q" placeholder="PUBG">
            <button type="submit" aria-label="Ara">
                <span class="material-icons">search</span>
            </button>
        </form>
        <div class="site-header__actions">
            <a href="/cart.php" class="site-header__icon-btn site-header__icon-btn--badge" data-cart-button aria-label="Sepeti aç">
                <span class="material-icons">shopping_cart</span>
                <span class="site-header__badge<?= $cartCount > 0 ? '' : ' is-hidden' ?>" data-cart-count><?= (int)$cartCount ?></span>
            </a>
            <div class="site-header__notifications<?= $unreadNotifications > 0 ? ' has-unread' : '' ?>" data-notification-root>
                <button type="button" class="site-header__icon-btn site-header__icon-btn--badge" data-notification-toggle aria-label="Bildirimler">
                    <span class="material-icons" data-notification-icon="empty">notifications_none</span>
                    <span class="material-icons" data-notification-icon="active">notifications</span>
                    <span class="site-header__badge<?= $unreadNotifications > 0 ? '' : ' is-hidden' ?>" data-notification-count><?= (int)$unreadNotifications ?></span>
                </button>
                <div class="site-header__notification-panel" data-notification-panel>
                    <div class="site-header__notification-header">
                        <span>Bildirimler</span>
                        <button type="button" class="site-header__notification-action" data-notification-mark-all>Hepsini okundu işaretle</button>
                    </div>
                    <div class="site-header__notification-list" data-notification-list>
                        <?php if (!$notifications): ?>
                            <div class="site-header__notification-empty">Yeni bildiriminiz bulunmuyor.</div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <article class="site-header__notification-item<?= empty($notification['is_read']) ? ' is-unread' : '' ?>" data-notification-id="<?= (int)$notification['id'] ?>">
                                    <div class="site-header__notification-content">
                                        <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <p><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if (!empty($notification['published_at_human'])): ?>
                                            <span class="site-header__notification-time"><?= htmlspecialchars($notification['published_at_human'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($notification['link'])): ?>
                                        <a class="site-header__notification-link" href="<?= htmlspecialchars($notification['link'], ENT_QUOTES, 'UTF-8') ?>">İncele</a>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!$isLoggedInHeader): ?>
                <a href="/login.php" class="site-header__pill site-header__pill--primary">
                    <span class="material-icons">login</span>
                    <span>Giriş Yap</span>
                </a>
                <a href="/register.php" class="site-header__pill site-header__pill--success">
                    <span class="material-icons">person_add</span>
                    <span>Kayıt Ol</span>
                </a>
            <?php else: ?>
                <a href="/account" class="site-header__user" data-account-link>
                    <span class="site-header__user-avatar" aria-hidden="true"><?= htmlspecialchars($userInitials !== '' ? $userInitials : 'U', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="site-header__user-details">
                        <span class="site-header__user-name">
                            <?= htmlspecialchars($displayName !== '' ? $displayName : $userName, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="site-header__user-balance">Bakiye: <?= htmlspecialchars($userFormattedBalance, ENT_QUOTES, 'UTF-8') ?> TL</span>
                    </span>
                </a>
                <a href="/logout.php" class="site-header__pill site-header__pill--primary site-header__pill--icon-only" aria-label="Çıkış Yap">
                    <span class="material-icons">logout</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <nav class="site-header__nav">
        <ul>
            <?php foreach ($navItems as $item): ?>
                <?php
                    $label = isset($item['title']) ? (string)$item['title'] : (isset($item['name']) ? (string)$item['name'] : 'Menü');
                    $url = isset($item['url']) && $item['url'] !== '' ? (string)$item['url'] : '#';
                    $iconClass = isset($item['icon']) ? trim((string)$item['icon']) : '';
                    $image = isset($item['image']) ? trim((string)$item['image']) : '';
                    $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : array();
                    $firstLetter = strtoupper(substr($label, 0, 1));
                    $iconifyName = '';
                    if ($iconClass !== '' && strpos($iconClass, 'iconify:') === 0) {
                        $iconifyName = substr($iconClass, 8);
                    }
                ?>
                <li class="site-header__nav-item<?= $children ? ' has-children' : '' ?>">
                    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" class="site-header__nav-link"<?= isset($item['target']) && $item['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
                        <span class="site-header__nav-avatar">
                            <?php if ($iconClass !== ''): ?>
                                <?php if ($iconifyName !== ''): ?>
                                    <span class="iconify" data-icon="<?= htmlspecialchars($iconifyName, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                                <?php else: ?>
                                    <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                <?php endif; ?>
                            <?php elseif ($image !== ''): ?>
                                <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy"
                                     decoding="async"
                                     width="40"
                                     height="40">
                            <?php else: ?>
                                <?= htmlspecialchars($firstLetter, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($children): ?>
                            <span class="site-header__nav-caret material-icons">expand_more</span>
                        <?php endif; ?>
                    </a>
                    <?php if ($children): ?>
                        <div class="site-header__nav-dropdown">
                            <?php foreach ($children as $child): ?>
                                <?php
                                    $childLabel = isset($child['title']) ? (string)$child['title'] : (isset($child['name']) ? (string)$child['name'] : 'Alt Menü');
                                    $childUrl = isset($child['url']) && $child['url'] !== '' ? (string)$child['url'] : '#';
                                ?>
                                <a href="<?= htmlspecialchars($childUrl, ENT_QUOTES, 'UTF-8') ?>" class="site-header__nav-dropdown-link"<?= isset($child['target']) && $child['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
                                    <?= htmlspecialchars($childLabel, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

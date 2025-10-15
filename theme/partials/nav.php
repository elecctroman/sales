<?php
use App\Helpers;
$navCategories = isset($GLOBALS['theme_nav_categories']) ? $GLOBALS['theme_nav_categories'] : array();
$cartSummary = isset($GLOBALS['theme_cart_summary']) ? $GLOBALS['theme_cart_summary'] : array();
$cartTotals = isset($cartSummary['totals']) ? $cartSummary['totals'] : array();
$cartCount = isset($cartTotals['total_quantity']) ? (int)$cartTotals['total_quantity'] : 0;

if (!$navCategories) {
    $navCategories = array(
        array('id' => 1, 'name' => 'PUBG', 'slug' => 'pubg', 'icon' => '', 'image' => '', 'children' => array()),
        array('id' => 2, 'name' => 'Valorant', 'slug' => 'valorant', 'icon' => '', 'image' => '', 'children' => array()),
        array('id' => 3, 'name' => 'Windows', 'slug' => 'windows', 'icon' => '', 'image' => '', 'children' => array()),
        array('id' => 4, 'name' => 'Semrush', 'slug' => 'semrush', 'icon' => '', 'image' => '', 'children' => array()),
        array('id' => 5, 'name' => 'Adobe', 'slug' => 'adobe', 'icon' => '', 'image' => '', 'children' => array()),
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
        <form class="site-header__search" action="/catalog.php">
            <input type="search" name="q" placeholder="PUBG">
            <button type="submit" aria-label="Ara">
                <span class="material-icons">search</span>
            </button>
        </form>
        <div class="site-header__actions">
            <a href="/cart.php" class="site-header__icon-btn site-header__icon-btn--badge" data-cart-button aria-label="Sepeti ac">
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
                        <button type="button" class="site-header__notification-action" data-notification-mark-all>Hepsini okundu isaretle</button>
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
                                        <a class="site-header__notification-link" href="<?= htmlspecialchars($notification['link'], ENT_QUOTES, 'UTF-8') ?>">Incele</a>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="site-header__actions-group">
                <a href="#" class="site-header__pill">
                    <span class="material-icons">language</span>
                    <span>Turkce</span>
                </a>
            </div>
            <?php if (!$isLoggedInHeader): ?>
                <a href="/login.php" class="site-header__pill site-header__pill--primary">
                    <span class="material-icons">login</span>
                    <span>Giri&#351; Yap</span>
                </a>
                <a href="/register.php" class="site-header__pill site-header__pill--success">
                    <span class="material-icons">person_add</span>
                    <span>Kay&#305;t Ol</span>
                </a>
            <?php else: ?>
                <a href="/account.php" class="site-header__user" data-account-link>
                    <span class="site-header__user-avatar" aria-hidden="true"><?= htmlspecialchars($userInitials !== '' ? $userInitials : 'U', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="site-header__user-details">
                        <span class="site-header__user-name">
                            <?= htmlspecialchars($displayName !== '' ? $displayName : $userName, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="site-header__user-balance">Bakiye: <?= htmlspecialchars($userFormattedBalance, ENT_QUOTES, 'UTF-8') ?> TL</span>
                    </span>
                </a>
                <a href="/logout.php" class="site-header__pill site-header__pill--primary site-header__pill--icon-only" aria-label="Cikis Yap">
                    <span class="material-icons">logout</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <nav class="site-header__nav">
        <ul>
            <?php foreach ($navCategories as $category): ?>
                    $path = isset($category['path']) && $category['path'] !== '' ? (string)$category['path'] : $slug;
                    $categoryUrl = isset($category['url']) && $category['url'] !== '' ? (string)$category['url'] : Helpers::categoryUrl($path);
                <?php
                    $slug = !empty($category['slug']) ? $category['slug'] : 'category-' . (int)$category['id'];
                    $hasChildren = !empty($category['children']);
                    $iconClass = isset($category['icon']) ? trim((string)$category['icon']) : '';
                    $image = isset($category['image']) ? trim((string)$category['image']) : '';
                    $firstLetter = strtoupper(substr($category['name'], 0, 1));
                    $iconifyName = '';

                    if ($iconClass !== '' && strpos($iconClass, 'iconify:') === 0) {
                        $iconifyName = substr($iconClass, 8);
                    }
                ?>
                <li class="site-header__nav-item<?= $hasChildren ? ' has-children' : '' ?>">
                    <a href="/catalog.php#<?= htmlspecialchars($slug) ?>" class="site-header__nav-link">
                        <span class="site-header__nav-avatar">
                            <?php if ($iconClass !== ''): ?>
                                <?php if ($iconifyName !== ''): ?>
                                    <span class="iconify" data-icon="<?= htmlspecialchars($iconifyName, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                                <?php else: ?>
                                    <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                <?php endif; ?>
                            <?php elseif ($image !== ''): ?>
                                <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy"
                                     decoding="async"
                                     width="40"
                                     height="40">
                            <?php else: ?>
                                <?= htmlspecialchars($firstLetter, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($category['name']) ?></span>
                        <?php if ($hasChildren): ?>
                            <span class="site-header__nav-caret material-icons">expand_more</span>
                        <?php endif; ?>
                    </a>
                    <?php if ($hasChildren): ?>
                        <div class="site-header__nav-dropdown">
                            <?php foreach ($category['children'] as $child): ?>
                                <a href="/catalog.php#<?= htmlspecialchars($child['slug']) ?>" class="site-header__nav-dropdown-link">
                                    <?= htmlspecialchars($child['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<?php
use App\Helpers;

$pageData = isset($productPage) && is_array($productPage) ? $productPage : array();
$product = isset($pageData['product']) ? $pageData['product'] : null;
$breadcrumbs = isset($pageData['breadcrumbs']) && is_array($pageData['breadcrumbs']) ? $pageData['breadcrumbs'] : array();
$comments = isset($pageData['comments']) && is_array($pageData['comments']) ? $pageData['comments'] : array();
$commentFeedback = isset($pageData['commentFeedback']) && is_array($pageData['commentFeedback']) ? $pageData['commentFeedback'] : array();
$commentErrors = isset($commentFeedback['errors']) && is_array($commentFeedback['errors']) ? $commentFeedback['errors'] : array();
$commentSuccess = isset($commentFeedback['success']) ? (string)$commentFeedback['success'] : '';
$commentOld = isset($commentFeedback['old']['comment']) ? (string)$commentFeedback['old']['comment'] : '';
$isLoggedIn = !empty($isLoggedIn);
$currentUser = isset($user) && is_array($user) ? $user : null;

$renderText = static function ($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '<p class="text-muted">Bilgi bulunmuyor.</p>';
    }

    $lines = preg_split("/\r\n|\r|\n/", $value);
    $html = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $html .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return $html !== '' ? $html : '<p class="text-muted">Bilgi bulunmuyor.</p>';
};

if (!$product) : ?>
    <section class="product-detail product-detail--empty">
        <div class="product-detail__empty">
            <span class="material-icons" aria-hidden="true">inventory_2</span>
            <h1>Urun bulunamadi</h1>
            <p>Aradiginiz urunu bulamadik. Kataloga geri donerek diger secenekleri inceleyebilirsiniz.</p>
            <a class="btn btn-primary" href="/kategori/">Kataloğa Dön</a>
        </div>
    </section>
    <?php return; ?>
<?php endif;

$image = isset($product['image']) && $product['image'] !== '' ? $product['image'] : '/theme/assets/images/placeholder.png';
$priceFormatted = isset($product['price_formatted']) ? $product['price_formatted'] : Helpers::formatCurrency(0, 'TRY');
$stockLabel = isset($product['stock_label']) ? $product['stock_label'] : ($product['in_stock'] ? 'In stock' : 'Out of stock');
$viewsCount = isset($product['views_count']) ? (int)$product['views_count'] : 0;
$viewsMessage = $viewsCount === 1 ? '1 kisi bu urunu inceledi.' : sprintf('%d kisi bu urunu inceledi.', $viewsCount);
$productId = (int)$product['id'];
$productName = (string)$product['name'];
$slug = isset($product['slug']) ? (string)$product['slug'] : '';
$descriptionHtml = $renderText(isset($product['description']) ? $product['description'] : '');
$shortDescriptionHtml = $renderText(isset($product['short_description']) && $product['short_description'] !== '' ? $product['short_description'] : (isset($product['description']) ? $product['description'] : ''));
$commentCount = isset($product['comment_count']) ? (int)$product['comment_count'] : count($comments);

$journeySteps = array(
    array(
        'icon' => 'fact_check',
        'title' => '1. Paketi Secin',
        'text' => 'Ihtiyaciniza uygun lisans veya servisi secip sepete ekleyin.',
    ),
    array(
        'icon' => 'payments',
        'title' => '2. Odeme Adimina Gecin',
        'text' => 'Kart, bakiye, kripto veya banka transferi ile guvenli odeme yapin.',
    ),
    array(
        'icon' => 'rocket_launch',
        'title' => '3. Hemen Teslim Alin',
        'text' => 'Odeme sonrasi urun bilgileri hesabiniza ve e-posta adresinize ulassin.',
    ),
);
?>

<nav class="product-detail__breadcrumbs" aria-label="breadcrumb">
    <ol>
        <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
            <?php
                $label = isset($breadcrumb['label']) ? $breadcrumb['label'] : '';
                $url = isset($breadcrumb['url']) ? $breadcrumb['url'] : null;
                $isLast = $index === count($breadcrumbs) - 1;
            ?>
            <li<?= $isLast ? ' aria-current="page"' : '' ?>>
                <?php if ($url && !$isLast): ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a>
                <?php else: ?>
                    <span><?= htmlspecialchars($label) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>

<section class="product-detail">
    <div class="product-detail__hero">
        <div class="product-detail__media">
            <a href="<?= htmlspecialchars($image) ?>" target="_blank" rel="noopener">
                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($productName) ?>">
            </a>
        </div>
        <div class="product-detail__summary">
            <?php if (!empty($product['category']['name'])): ?>
                <span class="product-detail__category"><?= htmlspecialchars($product['category']['name']) ?></span>
            <?php endif; ?>
            <h1><?= htmlspecialchars($productName) ?></h1>
            <?php if (!empty($product['sku'])): ?>
                <p class="product-detail__sku">SKU: <?= htmlspecialchars($product['sku']) ?></p>
            <?php endif; ?>

            <div class="product-detail__meta">
                <span class="product-detail__meta-item product-detail__meta-item--views">
                    <span class="material-icons" aria-hidden="true">visibility</span>
                    <?= htmlspecialchars($viewsMessage) ?>
                </span>
                <span class="product-detail__meta-item<?= $product['in_stock'] ? ' is-available' : ' is-out' ?>">
                    <span class="material-icons" aria-hidden="true"><?= $product['in_stock'] ? 'check_circle' : 'cancel' ?></span>
                    <?= htmlspecialchars($stockLabel) ?>
                </span>
            </div>

            <div class="product-detail__pricing">
                <span class="product-detail__price"><?= htmlspecialchars($priceFormatted) ?></span>
                <span class="product-detail__badge"><?= $product['in_stock'] ? 'Stokta' : 'Stok Disi' ?></span>
            </div>

            <div class="product-detail__description">
                <h2>Product Description</h2>
                <?= $descriptionHtml ?>
            </div>

            <div class="product-detail__actions">
                <button
                    type="button"
                    class="btn btn-primary product-detail__action"
                    data-add-to-cart
                    data-product-id="<?= $productId ?>"
                    data-product-name="<?= htmlspecialchars($productName) ?>"
                    <?= $product['in_stock'] ? '' : 'disabled' ?>
                >
                    Add to Cart
                </button>
                <button
                    type="button"
                    class="btn btn-success product-detail__action product-detail__action--buy"
                    data-buy-now
                    data-product-id="<?= $productId ?>"
                    data-product-name="<?= htmlspecialchars($productName) ?>"
                    <?= $product['in_stock'] ? '' : 'disabled' ?>
                >
                    Buy Now
                </button>
            </div>
        </div>
    </div>

    <div class="product-detail__journey">
        <?php foreach ($journeySteps as $step): ?>
            <article class="product-journey-card">
                <span class="material-icons" aria-hidden="true"><?= htmlspecialchars($step['icon']) ?></span>
                <h3><?= htmlspecialchars($step['title']) ?></h3>
                <p><?= htmlspecialchars($step['text']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="product-detail__about" id="about-product">
    <h2>About Product</h2>
    <?= $shortDescriptionHtml ?>
</section>

<section class="product-detail__comments" id="product-comments">
    <header class="product-detail__comments-header">
        <h2>Yorumlar</h2>
        <span><?= $commentCount ?> yorum</span>
    </header>

    <?php if ($commentSuccess !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($commentSuccess) ?></div>
    <?php endif; ?>

    <?php if ($commentErrors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($commentErrors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($comments): ?>
        <div class="product-detail__comment-list">
            <?php foreach ($comments as $comment): ?>
                <article class="product-comment">
                    <header>
                        <strong><?= htmlspecialchars($comment['author']) ?></strong>
                        <time datetime="<?= htmlspecialchars($comment['created_at']) ?>"><?= htmlspecialchars($comment['created_at_human']) ?></time>
                    </header>
                    <p><?= nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Bu urun icin henuz yorum yapilmadi. Ilk yorumu siz birakin!</p>
    <?php endif; ?>

    <div class="product-detail__comment-form">
        <?php if ($isLoggedIn && $currentUser): ?>
            <form method="post">
                <input type="hidden" name="action" value="product_comment">
                <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                <label for="commentBody" class="form-label">Yorumunuz</label>
                <textarea id="commentBody" name="comment" rows="4" required placeholder="Deneyiminizi paylasin..."><?= htmlspecialchars($commentOld) ?></textarea>
                <button type="submit" class="btn btn-primary">Yorumu Gonder</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                Yorum yapabilmek icin lutfen <a href="/login.php">giris yapin</a> veya <a href="/register.php">hesap olusturun</a>.
            </div>
        <?php endif; ?>
    </div>
</section>


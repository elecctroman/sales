<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/theme/bootstrap.php';

use App\Auth;
use App\Cart;
use App\Database;
use App\Helpers;
use App\Homepage;
use App\Settings;
use App\Router;
use App\Page;
use App\Blog;
$script = basename($_SERVER['SCRIPT_NAME']);
$authContext = array(
    'errors' => array(),
    'success' => '',
    'old' => array(),
    'redirect' => null,
);

$router = Router::instance();
$requestedUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/';
$requestedPath = parse_url($requestedUri, PHP_URL_PATH);
if ($requestedPath === null || $requestedPath === false || $requestedPath === '') {
    $requestedPath = '/';
}

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
$routeMatch = $router->match($requestedPath, $requestMethod);
$currentRoute = null;
$currentRouteName = null;

if ($routeMatch) {
    $currentRoute = $routeMatch;
    $currentRouteName = $routeMatch['name'];

    if ($script === 'index.php') {
        $script = $routeMatch['script'];
    }

    if (!empty($routeMatch['params']) && is_array($routeMatch['params'])) {
        foreach ($routeMatch['params'] as $key => $value) {
            if (!isset($_GET[$key])) {
                $_GET[$key] = $value;
            }
        }
    }

    if ($requestMethod === 'GET') {
        $redirectTarget = $routeMatch['path'];
        if ($redirectTarget !== $requestedPath) {
            $queryParams = $_GET;
            if (is_array($routeMatch['params'])) {
                foreach ($routeMatch['params'] as $paramKey => $paramValue) {
                    if (isset($queryParams[$paramKey]) && (string)$queryParams[$paramKey] === (string)$paramValue) {
                        unset($queryParams[$paramKey]);
                    }
                }
            }
            if ($queryParams) {
                $redirectTarget .= '?' . http_build_query($queryParams);
            }
            Helpers::permanentRedirect($redirectTarget);
        }
    }
} elseif ($requestMethod === 'GET') {
    $canonical = $router->canonicalFromScript($script, $_GET);
    if ($canonical && isset($canonical['path']) && $canonical['path'] !== $requestedPath) {
        $queryParams = $_GET;
        if (is_array($canonical['params'])) {
            foreach ($canonical['params'] as $paramKey => $paramValue) {
                if (isset($queryParams[$paramKey]) && (string)$queryParams[$paramKey] === (string)$paramValue) {
                    unset($queryParams[$paramKey]);
                }
            }
        }
        $target = $canonical['path'];
        if ($queryParams) {
            $target .= '?' . http_build_query($queryParams);
        }
        Helpers::permanentRedirect($target);
    }
}

if ($script === 'blog.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    if ($action === 'add_comment') {
        $feedback = array(
            'errors' => array(),
            'success' => '',
        );

        $csrf = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        if (!Helpers::verifyCsrf($csrf)) {
            $feedback['errors'][] = 'Lütfen sayfayı yenileyip tekrar deneyin.';
        }

        $slug = isset($_POST['post_slug']) ? trim((string)$_POST['post_slug']) : '';
        $postRecord = null;
        if ($slug !== '') {
            $postRecord = Blog::findPublished($slug);
        }
        if (!$postRecord) {
            $feedback['errors'][] = 'Yorum yapmak istediğiniz blog yazısı bulunamadı.';
        }

        $authorName = isset($_POST['author_name']) ? trim((string)$_POST['author_name']) : '';
        $authorEmail = isset($_POST['author_email']) ? trim((string)$_POST['author_email']) : '';
        $content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';

        if ($content === '') {
            $feedback['errors'][] = 'Yorum içeriği boş olamaz.';
        }

        if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            $feedback['errors'][] = 'Geçerli bir e-posta adresi giriniz.';
        }

        if (!$feedback['errors'] && $postRecord) {
            $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
            Blog::addComment((int)$postRecord['id'], array(
                'author_name' => $authorName !== '' ? $authorName : null,
                'author_email' => $authorEmail !== '' ? $authorEmail : null,
                'content' => $content,
                'status' => 'pending',
            ), $userId);
            $feedback['success'] = 'Yorumunuz onaylandıktan sonra yayınlanacaktır.';
        }

        Helpers::setFlash('blog_comment_feedback', $feedback);

        $redirectPost = $postRecord ?: array('slug' => $slug, 'published_at' => date('Y-m-d H:i:s'));
        $redirectUrl = Helpers::blogPostUrl($redirectPost, true);
        Helpers::redirect($redirectUrl);
    }
}

if ($script === 'cart.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    try {
        switch ($action) {
            case 'add':
                if ($productId <= 0) {
                    throw new \RuntimeException('Geçersiz ürün bilgisi.');
                }
                $cartSnapshot = Cart::add($productId, $quantity);
                $message = 'Ürün sepete eklendi.';
                break;

            case 'update':
                if ($productId <= 0) {
                    throw new \RuntimeException('Geçersiz ürün bilgisi.');
                }
                $cartSnapshot = Cart::update($productId, $quantity);
                $message = 'Sepet güncellendi.';
                break;

            case 'remove':
                if ($productId <= 0) {
                    throw new \RuntimeException('Geçersiz ürün bilgisi.');
                }
                $cartSnapshot = Cart::remove($productId);
                $message = 'Ürün sepetten kaldırıldı.';
                break;

            case 'clear':
                $cartSnapshot = Cart::clear();
                $message = 'Sepet temizlendi.';
                break;

            case 'summary':
                $cartSnapshot = Cart::snapshot();
                $message = '';
                break;

            default:
                throw new \RuntimeException('Geçersiz işlem.');
        }

        echo json_encode(array(
            'success' => true,
            'message' => $message,
            'cart' => $cartSnapshot,
        ));
    } catch (\Throwable $exception) {
        http_response_code(400);
        echo json_encode(array(
            'success' => false,
            'message' => $exception->getMessage(),
        ));
    }

    exit;
}

if ($script === 'login.php' && $_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['register'])) {
    Helpers::redirect(Helpers::registerUrl());
}

if ($script === 'login.php' && isset($_GET['return'])) {
    $authContext['redirect'] = Helpers::normalizeRedirectPath((string)$_GET['return'], '/');
}

if ($script === 'login.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $tokenValue = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $returnTarget = isset($_POST['return']) ? Helpers::normalizeRedirectPath((string)$_POST['return'], '/') : null;

    $authContext['old']['email'] = $identifier;
    if ($returnTarget) {
        $authContext['redirect'] = $returnTarget;
    }

    if (!Helpers::verifyCsrf($tokenValue)) {
        $authContext['errors'][] = 'Oturum doğrulaması başarısız. Lütfen tekrar deneyin.';
    } elseif ($identifier === '' || $password === '') {
        $authContext['errors'][] = 'E-posta ve şifre alanları zorunludur.';
    } else {
        try {
            $user = Auth::attempt($identifier, $password);
            if ($user) {
                $_SESSION['user'] = $user;
                $redirectTo = $returnTarget ?: '/';
                Helpers::redirect($redirectTo);
            } else {
                $authContext['errors'][] = 'Giriş bilgileri doğrulanamadı.';
            }
        } catch (\Throwable $exception) {
            $authContext['errors'][] = 'Giriş işlemi sırasında beklenmedik bir hata oluştu.';
        }
    }
}

if ($script === 'register.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm = isset($_POST['password_confirmation']) ? (string)$_POST['password_confirmation'] : '';
    $tokenValue = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

    $authContext['old']['name'] = $name;
    $authContext['old']['email'] = $email;

    if (!Helpers::verifyCsrf($tokenValue)) {
        $authContext['errors'][] = 'Oturum doğrulaması başarısız. Lütfen tekrar deneyin.';
    }

    if ($name === '') {
        $authContext['errors'][] = 'Ad soyad alanı zorunludur.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $authContext['errors'][] = 'Geçerli bir e-posta adresi girin.';
    }

    if (strlen($password) < 6) {
        $authContext['errors'][] = 'Şifre en az 6 karakter olmalıdır.';
    }

    if ($password !== $confirm) {
        $authContext['errors'][] = 'Şifre ve şifre tekrarı eşleşmiyor.';
    }

    if (!$authContext['errors']) {
        try {
            $pdo = Database::connection();
            $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $existsStmt->execute(array('email' => $email));
            if ((int)$existsStmt->fetchColumn() > 0) {
                $authContext['errors'][] = 'Bu e-posta adresi ile kayıtlı bir hesap zaten mevcut.';
            }
        } catch (\Throwable $exception) {
            $authContext['errors'][] = 'Kayıt kontrolü sırasında hata oluştu.';
        }
    }

    if (!$authContext['errors']) {
        try {
            $userId = Auth::createUser($name, $email, $password, 'customer', 0);
            $user = Auth::findUser($userId);
            if ($user) {
                $_SESSION['user'] = $user;
                Helpers::redirect('/');
            } else {
                $authContext['errors'][] = 'Kayıt başarıyla tamamlandı ancak kullanıcı oturumu başlatılamadı.';
            }
        } catch (\Throwable $exception) {
            $authContext['errors'][] = 'Kayıt işlemi sırasında hata oluştu.';
        }
    }
}

function decodeSettingArray(string $key): array
{
    $raw = Settings::get($key);
    if ($raw === null) {
        return array();
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : array();
}

function defaultFeaturedProducts(): array
{
    return array(
        array(
            'id' => 1,
            'name' => 'Valorant Points 2050',
            'description' => 'Instant delivery to your Riot account.',
            'price' => '$19.99',
            'image' => '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp',
        ),
        array(
            'id' => 2,
            'name' => 'Windows 11 Pro License',
            'description' => 'Lifetime activation key delivered instantly.',
            'price' => '$29.00',
            'image' => '/theme/assets/images/site/eT5jm6xnxfaVz0uZKDLLuNhWtWj5eqtiz9IDNlO0.webp',
        ),
        array(
            'id' => 3,
            'name' => 'Canva Pro 1 Year',
            'description' => 'Plan your content with full premium access.',
            'price' => '$12.50',
            'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp',
        ),
        array(
            'id' => 4,
            'name' => 'Adobe Creative Cloud 3 Months',
            'description' => 'Photoshop, Illustrator and more-one subscription.',
            'price' => '$45.00',
            'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp',
        ),
    );
}

function defaultHomeSections(): array
{
    return array(
        array(
            'id' => 'pubg',
            'title' => 'PUBG',
            'accent' => '#2563eb',
            'products' => array(
                array('id' => 101, 'name' => 'PUBG Mobile 3850 UC', 'price' => '$52.99', 'tag' => '-18%', 'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 102, 'name' => 'PUBG Mobile 1800 UC', 'price' => '$28.99', 'tag' => '-10%', 'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 103, 'name' => 'PUBG 660 UC', 'price' => '$10.99', 'tag' => 'Popular', 'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 104, 'name' => 'PUBG Mobile 325 UC', 'price' => '$5.99', 'tag' => 'New', 'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp', 'description' => Helpers::defaultProductDescription()),
            ),
        ),
        array(
            'id' => 'valorant',
            'title' => 'Valorant',
            'accent' => '#c026d3',
            'products' => array(
                array('id' => 201, 'name' => 'Valorant 5350 VP', 'price' => '$49.99', 'tag' => '-15%', 'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 202, 'name' => 'Valorant 3650 VP', 'price' => '$34.99', 'tag' => 'Hot', 'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 203, 'name' => 'Valorant 2050 VP', 'price' => '$19.99', 'tag' => 'Deal', 'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 204, 'name' => 'Valorant 1000 VP', 'price' => '$9.99', 'tag' => 'New', 'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp', 'description' => Helpers::defaultProductDescription()),
            ),
        ),
        array(
            'id' => 'windows',
            'title' => 'Windows',
            'accent' => '#0ea5e9',
            'products' => array(
                array('id' => 301, 'name' => 'Windows 11 Home', 'price' => '$19.00', 'tag' => 'Digital Key', 'image' => '/theme/assets/images/site/KreWJFMqBI43i90m6TEODfrRY6BJoEjftSp84I5B.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 302, 'name' => 'Windows 11 Pro', 'price' => '$29.00', 'tag' => 'Best Seller', 'image' => '/theme/assets/images/site/KreWJFMqBI43i90m6TEODfrRY6BJoEjftSp84I5B.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 303, 'name' => 'Windows 10 Pro', 'price' => '$15.00', 'tag' => '-20%', 'image' => '/theme/assets/images/site/KreWJFMqBI43i90m6TEODfrRY6BJoEjftSp84I5B.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 304, 'name' => 'Office 365 Family', 'price' => '$39.00', 'tag' => 'Bundle', 'image' => '/theme/assets/images/site/KreWJFMqBI43i90m6TEODfrRY6BJoEjftSp84I5B.webp', 'description' => Helpers::defaultProductDescription()),
            ),
        ),
        array(
            'id' => 'design-tools',
            'title' => 'Design Tools',
            'accent' => '#f97316',
            'products' => array(
                array('id' => 401, 'name' => 'Semrush Guru 1 Month', 'price' => '$39.90', 'tag' => 'Agency', 'image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 402, 'name' => 'Adobe Creative Cloud 1 Month', 'price' => '$19.99', 'tag' => '-25%', 'image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 403, 'name' => 'Canva Pro 1 Month', 'price' => '$6.90', 'tag' => 'Popular', 'image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp', 'description' => Helpers::defaultProductDescription()),
                array('id' => 404, 'name' => 'Freepik Premium 1 Year', 'price' => '$49.00', 'tag' => 'Limited', 'image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp', 'description' => Helpers::defaultProductDescription()),
            ),
        ),
    );
}

function defaultBlogPosts(): array
{
    return array(
        array(
            'title' => 'Launch of our new marketplace',
            'excerpt' => 'All-in-one digital goods platform with instant delivery.',
            'date' => '12 Oct 2025',
            'image' => '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg',
        ),
        array(
            'title' => 'How to sell game credits globally',
            'excerpt' => 'Strategy for scaling cross-border payment flows.',
            'date' => '05 Oct 2025',
            'image' => '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg',
        ),
        array(
            'title' => 'Valorant meta: duelist patch review',
            'excerpt' => 'Tips from top ladder players this season.',
            'date' => '29 Sep 2025',
            'image' => '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg',
        ),
    );
}


function resolveCategoryPresentation(?array $category, array $overrides, array $defaults, string $defaultAccent, string $defaultImage): array
{
    $slug = '';
    $idKey = null;

    if ($category) {
        $slug = Helpers::slugify($category['name']);
        $idKey = (string)(int)$category['id'];
    }

    $style = array();

    if ($slug !== '' && isset($defaults[$slug]) && is_array($defaults[$slug])) {
        $style = $defaults[$slug];
    }

    if ($idKey !== null && isset($overrides[$idKey]) && is_array($overrides[$idKey])) {
        $style = array_merge($style, $overrides[$idKey]);
    } elseif ($slug !== '' && isset($overrides[$slug]) && is_array($overrides[$slug])) {
        $style = array_merge($style, $overrides[$slug]);
    }

    $accent = isset($style['accent']) && is_string($style['accent']) && $style['accent'] !== ''
        ? $style['accent']
        : ($slug !== '' && isset($defaults[$slug]['accent']) ? $defaults[$slug]['accent'] : $defaultAccent);

    $image = $defaultImage;
    if ($category && !empty($category['image'])) {
        $image = (string)$category['image'];
    }
    if (isset($style['image']) && is_string($style['image']) && $style['image'] !== '') {
        $image = $style['image'];
    } elseif ($slug !== '' && isset($defaults[$slug]['image'])) {
        $image = $defaults[$slug]['image'];
    }

    $icon = $category && !empty($category['icon']) ? (string)$category['icon'] : '';
    if (isset($style['icon']) && is_string($style['icon']) && $style['icon'] !== '') {
        $icon = $style['icon'];
    }

    return array(
        'slug' => $slug !== '' ? $slug : ($category ? 'category-' . (int)$category['id'] : ''),
        'accent' => $accent,
        'image' => $image,
        'icon' => $icon,
    );
}

function mapProductCard(array $product, string $image, ?int $categoryId = null, ?string $categorySlug = null): array
{
    $description = isset($product['description']) ? trim((string)$product['description']) : '';
    if ($description === '') {
        $description = Helpers::defaultProductDescription();
    }

    $summary = isset($product['short_description']) ? trim((string)$product['short_description']) : '';
    if ($summary === '') {
        $summary = mb_substr($description, 0, 160);
        if (mb_strlen($description) > 160) {
            $summary = rtrim($summary) . '...';
        }
    }

    $priceCurrency = 'USD';
    $priceValue = isset($product['price']) ? (float)$product['price'] : 0.0;
    if (isset($product['cost_price_try']) && (float)$product['cost_price_try'] > 0) {
        $priceCurrency = 'TRY';
        $priceValue = (float)$product['cost_price_try'];
    }

    $status = isset($product['status']) ? (string)$product['status'] : 'inactive';
    $inStock = $status === 'active';

    $imageSource = $image;
    if (isset($product['image_url']) && trim((string)$product['image_url']) !== '') {
        $imageSource = (string)$product['image_url'];
    }

    $viewsCount = isset($product['views_count']) ? (int)$product['views_count'] : 0;
    $slugBase = $product['name'] !== '' ? Helpers::slugify($product['name']) : 'product';
    if ($slugBase === '') {
        $slugBase = 'product';
    }
    $slug = $slugBase . '-' . (int)$product['id'];

    $card = array(
        'id' => (int)$product['id'],
        'name' => $product['name'],
        'description' => $description,
        'summary' => $summary,
        'price' => Helpers::formatCurrency($priceValue, $priceCurrency),
        'price_formatted' => Helpers::formatCurrency($priceValue, $priceCurrency),
        'price_value' => $priceValue,
        'price_currency' => $priceCurrency,
        'image' => $imageSource,
        'tag' => null,
        'status' => $status,
        'in_stock' => $inStock,
        'stock_label' => $inStock ? 'In stock' : 'Out of stock',
        'views_count' => $viewsCount,
        'slug' => $slug,
    );

    if ($categoryId !== null) {
        $card['category_id'] = $categoryId;
    }

    if ($categorySlug !== null && $categorySlug !== '') {
        $card['category_slug'] = $categorySlug;
    }

    if (isset($product['category_name']) && $product['category_name'] !== '') {
        $card['category_name'] = (string)$product['category_name'];
    }

    return $card;
}

$defaultFeaturedProducts = defaultFeaturedProducts();
$defaultHomeSections = defaultHomeSections();
$defaultBlogPosts = defaultBlogPosts();
$sliderConfig = Homepage::loadSliderConfig();

$productPageContext = array(
    'product' => null,
    'comments' => array(),
    'breadcrumbs' => array(),
    'commentFeedback' => array(
        'errors' => array(),
        'success' => '',
        'old' => array(),
    ),
);

$paymentSuccessContext = array(
    'method' => 'card',
    'reference' => '',
    'orderIds' => array(),
    'orders' => array(),
    'total' => 0.0,
    'currency' => Helpers::activeCurrency(),
    'bankAccounts' => array(),
    'notification' => array(
        'errors' => array(),
        'success' => '',
    ),
    'remaining_balance' => null,
);

$pageContext = array(
    'page' => null,
    'breadcrumbs' => array(),
);

$blogContext = array(
    'view' => 'list',
    'posts' => array(),
    'post' => null,
    'filters' => array(),
    'pagination' => array(
        'page' => 1,
        'per_page' => 9,
        'total' => 0,
        'pages' => 0,
    ),
    'categories' => array(),
    'tags' => array(),
    'recent' => array(),
    'search' => '',
    'related' => array(),
    'feedback' => array(
        'errors' => array(),
        'success' => '',
    ),
);

$defaultCategoryImage = '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp';
$defaultAccent = '#6366f1';

$isLoggedIn = !empty($_SESSION['user']);
$pdo = null;

$defaultCategoryStyles = array(
    'pubg' => array('accent' => '#2563eb', 'image' => '/theme/assets/images/site/843JlXv47N4zBwrqTjLgP9B0kXgNSl3O14oHtESl.webp'),
    'valorant' => array('accent' => '#c026d3', 'image' => '/theme/assets/images/site/FoOS2ikJSxstFsWSfNWZEdnTQl0ziBY0dmn4QnX3.webp'),
    'windows' => array('accent' => '#0ea5e9', 'image' => '/theme/assets/images/site/KreWJFMqBI43i90m6TEODfrRY6BJoEjftSp84I5B.webp'),
    'design-tools' => array('accent' => '#f97316', 'image' => '/theme/assets/images/site/obCqriZHgv5AeK7LzXnQE3DNCm3Vw2wndCflf2mF.webp'),
    'subscriptions' => array('accent' => '#ec4899', 'image' => '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp'),
    'mobile-legends' => array('accent' => '#14b8a6', 'image' => '/theme/assets/images/site/KYQdPJDHaWihG3n7Sf3sHseGTRMT3xtmVGlDvNOj.webp'),
);

$featuredProducts = array();
$homeSections = array();
$homepageBlogPosts = $defaultBlogPosts;
$catalogProducts = array();
$popularCategories = array();
$navCategories = array();

try {
    $pdo = Database::connection();

    if ($script === 'product.php') {
        $productPageContext['commentFeedback']['errors'] = Helpers::getFlash('product_comment_errors', array());
        $productPageContext['commentFeedback']['success'] = Helpers::getFlash('product_comment_success', '');
        $productPageContext['commentFeedback']['old'] = Helpers::getFlash('product_comment_old', array());
    }

    if ($script === 'payment-success.php') {
        $paymentSuccessContext['notification']['errors'] = Helpers::getFlash('bank_notify_errors', array());
        $paymentSuccessContext['notification']['success'] = Helpers::getFlash('bank_notify_success', '');
    }

    $categoryStyleOverrides = decodeSettingArray('homepage_category_styles');
    $categoryImageOverrides = decodeSettingArray('homepage_category_images');

    if ($categoryImageOverrides) {
        foreach ($categoryImageOverrides as $key => $value) {
            if (!isset($categoryStyleOverrides[$key]) || !is_array($categoryStyleOverrides[$key])) {
                $categoryStyleOverrides[$key] = array();
            }
            if (is_string($value) && $value !== '') {
                $categoryStyleOverrides[$key]['image'] = $value;
            }
        }
    }

    $blogContext['feedback'] = Helpers::getFlash('blog_comment_feedback', $blogContext['feedback']);
    $blogContext['categories'] = Blog::categories(true);
    $blogContext['tags'] = Blog::tags(20);

    $recentPosts = Blog::recentPosts(5);
    if ($recentPosts) {
        $mappedRecent = array();
        foreach ($recentPosts as $post) {
            $publishedAt = isset($post['published_at']) && $post['published_at'] ? (string)$post['published_at'] : (isset($post['created_at']) ? (string)$post['created_at'] : date('Y-m-d H:i:s'));
            $timestamp = strtotime($publishedAt) ?: time();
            $summarySource = isset($post['summary']) && $post['summary'] !== '' ? (string)$post['summary'] : strip_tags((string)($post['content'] ?? ''));
            $post['published_human'] = date('d M Y', $timestamp);
            $post['excerpt'] = Helpers::truncate($summarySource, 180);
            $post['url'] = Helpers::blogPostUrl($post);
            if (!isset($post['cover_image']) || $post['cover_image'] === '') {
                $post['cover_image'] = '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg';
            }
            $mappedRecent[] = $post;
        }

        $blogContext['recent'] = $mappedRecent;

        $homepageBlogPosts = array();
        foreach (array_slice($mappedRecent, 0, 3) as $post) {
            $excerptSource = isset($post['summary']) && $post['summary'] !== '' ? (string)$post['summary'] : (string)$post['excerpt'];
            $homepageBlogPosts[] = array(
                'title' => (string)$post['title'],
                'excerpt' => Helpers::truncate($excerptSource, 140),
                'date' => $post['published_human'],
                'image' => (string)$post['cover_image'],
                'url' => (string)$post['url'],
                'category' => isset($post['category_name']) ? (string)$post['category_name'] : null,
            );
        }
    }

    $blogSetting = decodeSettingArray('homepage_blog_posts');
    if ($blogSetting) {
        $customPosts = array();
        foreach ($blogSetting as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $customPosts[] = array(
                'title' => isset($entry['title']) ? (string)$entry['title'] : '',
                'excerpt' => isset($entry['excerpt']) ? (string)$entry['excerpt'] : '',
                'date' => isset($entry['date']) ? (string)$entry['date'] : '',
                'image' => isset($entry['image']) ? (string)$entry['image'] : '',
                'url' => isset($entry['url']) ? (string)$entry['url'] : '#',
            );
        }
        if ($customPosts) {
            $homepageBlogPosts = $customPosts;
        }
    }

    $categoriesById = array();
    $categoryStatement = $pdo->query('SELECT id, parent_id, name, icon, image, description FROM categories ORDER BY created_at ASC, name ASC');
    if ($categoryStatement instanceof \PDOStatement) {
        while ($row = $categoryStatement->fetch(PDO::FETCH_ASSOC)) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] = isset($row['parent_id']) ? (int)$row['parent_id'] : null;
            $row['slug'] = Helpers::slugify($row['name']);
            $categoriesById[$row['id']] = $row;
        }
    }

    $categoryChildren = array();
    foreach ($categoriesById as $category) {
        $parentKey = $category['parent_id'] ?: 0;
        if (!isset($categoryChildren[$parentKey])) {
            $categoryChildren[$parentKey] = array();
        }
        $categoryChildren[$parentKey][] = $category;
    }

    foreach ($categoryChildren as &$childList) {
        usort($childList, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
    }
    unset($childList);

    if ($script === 'page.php') {
        $pageSlug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
        if ($pageSlug === '' && $routeMatch && isset($routeMatch['params']['slug'])) {
            $pageSlug = (string)$routeMatch['params']['slug'];
        }

        if ($pageSlug !== '') {
            $pageRecord = Page::findPublishedBySlug($pageSlug);
            if ($pageRecord) {
                $pageContext['page'] = $pageRecord;
                $pageContext['breadcrumbs'] = array(
                    array('label' => 'Ana Sayfa', 'url' => Helpers::absoluteUrl('/')),
                    array('label' => $pageRecord['title'], 'url' => null),
                );

                if (!empty($pageRecord['meta_description'])) {
                    $GLOBALS['pageMetaDescription'] = (string)$pageRecord['meta_description'];
                }
                if (!empty($pageRecord['meta_keywords'])) {
                    $GLOBALS['pageMetaKeywords'] = (string)$pageRecord['meta_keywords'];
                }

                Helpers::setCanonicalUrl(Helpers::pageUrl($pageRecord['slug'], true));
                $pageTitleValue = !empty($pageRecord['meta_title']) ? (string)$pageRecord['meta_title'] : (string)$pageRecord['title'];
                Helpers::setPageTitle($pageTitleValue);
                $pageContext['page_title'] = $pageTitleValue;
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(404);
        }
    }

    if ($script === 'blog.php') {
        $filters = array();
        $pageNumber = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if (isset($_GET['sayfa'])) {
            $pageNumber = (int)$_GET['sayfa'];
        }
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }

        $perPage = 9;

        if (!empty($_GET['category_slug'])) {
            $categorySlug = Helpers::slugify((string)$_GET['category_slug']);
            if ($categorySlug !== '') {
                $filters['category'] = $categorySlug;
            }
        } elseif (!empty($_GET['category'])) {
            $categorySlug = Helpers::slugify((string)$_GET['category']);
            if ($categorySlug !== '') {
                $filters['category'] = $categorySlug;
            }
        }

        if (!empty($_GET['tag_slug'])) {
            $tagSlug = Helpers::slugify((string)$_GET['tag_slug']);
            if ($tagSlug !== '') {
                $filters['tag'] = $tagSlug;
            }
        } elseif (!empty($_GET['tag'])) {
            $tagSlug = Helpers::slugify((string)$_GET['tag']);
            if ($tagSlug !== '') {
                $filters['tag'] = $tagSlug;
            }
        }

        if (!empty($_GET['search'])) {
            $filters['query'] = trim((string)$_GET['search']);
        } elseif (!empty($_GET['q'])) {
            $filters['query'] = trim((string)$_GET['q']);
        }

        if (isset($filters['query'])) {
            $blogContext['search'] = $filters['query'];
        }

        if (!empty($filters)) {
            $blogContext['filters'] = $filters;
        }

        if ($currentRouteName === 'blog.post') {
            $slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
            if ($slug === '' && $routeMatch && isset($routeMatch['params']['slug'])) {
                $slug = (string)$routeMatch['params']['slug'];
            }

            $activePost = Blog::findPublished($slug);
            if ($activePost) {
                $blogContext['view'] = 'detail';
                $blogContext['post'] = $activePost;

                $related = array();
                foreach ($blogContext['recent'] as $recentPost) {
                    if ((int)$recentPost['id'] === (int)$activePost['id']) {
                        continue;
                    }
                    $related[] = $recentPost;
                }
                $blogContext['related'] = array_slice($related, 0, 3);

                $canonicalUrl = Helpers::blogPostUrl($activePost, true);
                Helpers::setCanonicalUrl($canonicalUrl);
                $postTitle = !empty($activePost['meta_title']) ? (string)$activePost['meta_title'] : (string)$activePost['title'];
                Helpers::setPageTitle($postTitle);
                $blogContext['page_title'] = $postTitle;

                $postMeta = isset($activePost['meta_description']) && $activePost['meta_description'] !== ''
                    ? (string)$activePost['meta_description']
                    : Helpers::truncate(strip_tags((string)($activePost['summary'] ?? $activePost['content'] ?? '')), 160);
                $GLOBALS['pageMetaDescription'] = $postMeta;
                if (!empty($activePost['meta_keywords'])) {
                    $GLOBALS['pageMetaKeywords'] = (string)$activePost['meta_keywords'];
                }
            } else {
                http_response_code(404);
            }
        } else {
            $blogContext['view'] = 'list';
            $blogContext['pagination']['page'] = $pageNumber;
            $blogContext['pagination']['per_page'] = $perPage;

            $offset = ($pageNumber - 1) * $perPage;
            $pagination = Blog::paginatePosts($filters, $perPage, $offset);

            $posts = array();
            foreach ($pagination['items'] as $post) {
                $publishedAt = isset($post['published_at']) && $post['published_at'] ? (string)$post['published_at'] : (isset($post['created_at']) ? (string)$post['created_at'] : date('Y-m-d H:i:s'));
                $timestamp = strtotime($publishedAt) ?: time();
                $summarySource = isset($post['summary']) && $post['summary'] !== '' ? (string)$post['summary'] : strip_tags((string)($post['content'] ?? ''));
                $post['published_human'] = date('d M Y', $timestamp);
                $post['excerpt'] = Helpers::truncate($summarySource, 200);
                $post['url'] = Helpers::blogPostUrl($post);
                if (!isset($post['cover_image']) || $post['cover_image'] === '') {
                    $post['cover_image'] = '/theme/assets/images/blog/HApvChgiIiapIJu5zDkXgSrMsU6C9aZvQpjm3jXt.jpg';
                }
                $posts[] = $post;
            }

            $blogContext['posts'] = $posts;
            $blogContext['pagination']['total'] = (int)$pagination['total'];
            $blogContext['pagination']['pages'] = $blogContext['pagination']['per_page'] > 0
                ? (int)ceil($pagination['total'] / $blogContext['pagination']['per_page'])
                : 1;

            $query = $_GET;
            unset($query['page'], $query['sayfa']);
            if (!empty($filters['query'])) {
                $query['search'] = $filters['query'];
            }
            $baseUrl = Helpers::blogUrl();
            if ($filters) {
                if (isset($filters['category'])) {
                    $baseUrl = Helpers::blogCategoryUrl($filters['category']);
                } elseif (isset($filters['tag'])) {
                    $baseUrl = Helpers::blogTagUrl($filters['tag']);
                }
            }
            $canonicalUrl = rtrim($baseUrl, '/');
            if ($pageNumber > 1) {
                $query['page'] = $pageNumber;
            }
            if ($query) {
                $canonicalUrl .= '?' . http_build_query($query);
            }
            Helpers::setCanonicalUrl(Helpers::absoluteUrl($canonicalUrl));
        }
    }

    if ($script === 'product.php') {
        $requestedProductSlug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
        $requestedProductId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($requestedProductId <= 0 && isset($_GET['product'])) {
            $requestedProductId = (int)$_GET['product'];
        }
        if ($requestedProductId <= 0 && $requestedProductSlug !== '' && preg_match('/-(\d+)$/', $requestedProductSlug, $slugMatches)) {
            $requestedProductId = (int)$slugMatches[1];
        }

        $detailRow = null;
        if ($requestedProductId > 0) {
            $detailStmt = $pdo->prepare('SELECT p.*, cat.name AS category_name, cat.parent_id AS category_parent_id, cat.image AS category_image FROM products p LEFT JOIN categories cat ON cat.id = p.category_id WHERE p.id = :id LIMIT 1');
            $detailStmt->execute(array('id' => $requestedProductId));
            $detailRow = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($detailRow) {
            $canonicalSlug = Helpers::slugify($detailRow['name']);
            if ($canonicalSlug === '') {
                $canonicalSlug = 'product';
            }
            $canonicalSlug .= '-' . (int)$detailRow['id'];
            $canonicalUrl = Helpers::productUrl($canonicalSlug, true);
            Helpers::setCanonicalUrl($canonicalUrl);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                if ($requestedProductSlug === '' || $requestedProductSlug !== $canonicalSlug) {
                    Helpers::redirect(Helpers::productUrl($canonicalSlug));
                }
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
                if ($action === 'product_comment') {
                    $csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
                    $commentBody = isset($_POST['comment']) ? trim((string)$_POST['comment']) : '';
                    $commentErrors = array();

                    if (!Helpers::verifyCsrf($csrfToken)) {
                        $commentErrors[] = 'Oturum doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
                    }

                    if (!$isLoggedIn) {
                        $commentErrors[] = 'Yorum yapabilmek için giriş yapmalısınız.';
                    }

                    if ($commentBody === '' || mb_strlen($commentBody) < 5) {
                        $commentErrors[] = 'Yorumunuz en az 5 karakter olmalıdır.';
                    }

                    if ($commentErrors) {
                        Helpers::setFlash('product_comment_errors', $commentErrors);
                        Helpers::setFlash('product_comment_old', array('comment' => $commentBody));
                        Helpers::redirect(Helpers::productUrl($canonicalSlug) . '#product-comments');
                    }

                    $userId = $_SESSION['user']['id'];
                    $authorName = isset($_SESSION['user']['name']) ? trim((string)$_SESSION['user']['name']) : 'Customer';
                    $authorEmail = isset($_SESSION['user']['email']) ? trim((string)$_SESSION['user']['email']) : null;

                    $insertComment = $pdo->prepare('INSERT INTO product_comments (product_id, user_id, author_name, author_email, content, created_at) VALUES (:product_id, :user_id, :author_name, :author_email, :content, NOW())');
                    $insertComment->execute(array(
                        'product_id' => (int)$detailRow['id'],
                        'user_id' => (int)$userId,
                        'author_name' => $authorName !== '' ? $authorName : 'Customer',
                        'author_email' => $authorEmail !== '' ? $authorEmail : null,
                        'content' => $commentBody,
                    ));

                    Helpers::setFlash('product_comment_success', 'Yorumunuz kaydedildi.');
                    Helpers::setFlash('product_comment_old', array());
                    Helpers::redirect($canonicalUrl . '#product-comments');
                }
            }

            $viewsCount = isset($detailRow['views_count']) ? (int)$detailRow['views_count'] : 0;
            $updateViews = $pdo->prepare('UPDATE products SET views_count = views_count + 1 WHERE id = :id');
            $updateViews->execute(array('id' => (int)$detailRow['id']));
            $viewsCount++;

            $priceCurrency = 'USD';
            $priceValue = isset($detailRow['price']) ? (float)$detailRow['price'] : 0.0;
            if (isset($detailRow['cost_price_try']) && (float)$detailRow['cost_price_try'] > 0) {
                $priceCurrency = 'TRY';
                $priceValue = (float)$detailRow['cost_price_try'];
            }

            $status = isset($detailRow['status']) ? (string)$detailRow['status'] : 'inactive';
            $inStock = $status === 'active';

            $imageSource = $defaultCategoryImage;
            if (!empty($detailRow['image_url'])) {
                $imageSource = (string)$detailRow['image_url'];
            } elseif (!empty($detailRow['category_image'])) {
                $imageSource = (string)$detailRow['category_image'];
            }

            $description = isset($detailRow['description']) ? trim((string)$detailRow['description']) : '';
            if ($description === '') {
                $description = Helpers::defaultProductDescription();
            }

            $shortDescription = isset($detailRow['short_description']) ? trim((string)$detailRow['short_description']) : '';

            $productPageContext['product'] = array(
                'id' => (int)$detailRow['id'],
                'name' => (string)$detailRow['name'],
                'sku' => isset($detailRow['sku']) ? (string)$detailRow['sku'] : null,
                'description' => $description,
                'short_description' => $shortDescription,
                'image' => $imageSource,
                'price_value' => $priceValue,
                'price_currency' => $priceCurrency,
                'price_formatted' => Helpers::formatCurrency($priceValue, $priceCurrency),
                'status' => $status,
                'in_stock' => $inStock,
                'stock_label' => $inStock ? 'In stock' : 'Out of stock',
                'views_count' => $viewsCount,
                'slug' => $canonicalSlug,
                'category' => array(
                    'id' => isset($detailRow['category_id']) ? (int)$detailRow['category_id'] : null,
                    'name' => isset($detailRow['category_name']) ? (string)$detailRow['category_name'] : null,
                    'slug' => null,
                ),
            );

            if ($productPageContext['product']['category']['id'] && isset($categoriesById[$productPageContext['product']['category']['id']]['slug'])) {
                $productPageContext['product']['category']['slug'] = $categoriesById[$productPageContext['product']['category']['id']]['slug'];
            }

            $breadcrumbs = array(
                array('label' => 'Home', 'url' => Helpers::absoluteUrl('/')),
                array('label' => 'Catalog', 'url' => Helpers::catalogUrl()),
            );

            $categoryTrail = array();
            $trailCursor = isset($detailRow['category_id']) ? (int)$detailRow['category_id'] : null;
            $guard = 0;
            while ($trailCursor && isset($categoriesById[$trailCursor]) && $guard < 10) {
                $categoryNode = $categoriesById[$trailCursor];
                $categorySlug = isset($categoryNode['slug']) ? (string)$categoryNode['slug'] : '';
                if ($categorySlug === '') {
                    $categorySlug = Helpers::slugify($categoryNode['name']);
                }
                array_unshift($categoryTrail, array(
                    'label' => $categoryNode['name'],
                    'url' => Helpers::categoryUrl($categorySlug),
                ));
                $trailCursor = isset($categoryNode['parent_id']) ? (int)$categoryNode['parent_id'] : 0;
                $guard++;
            }
            if ($categoryTrail) {
                $breadcrumbs = array_merge($breadcrumbs, $categoryTrail);
            }
            $breadcrumbs[] = array('label' => (string)$detailRow['name'], 'url' => null);
            $productPageContext['breadcrumbs'] = $breadcrumbs;

            $commentsStmt = $pdo->prepare('SELECT pc.id, pc.content, pc.created_at, pc.author_name, u.name AS user_name FROM product_comments pc LEFT JOIN users u ON u.id = pc.user_id WHERE pc.product_id = :product_id AND pc.is_approved = 1 ORDER BY pc.created_at DESC');
            $commentsStmt->execute(array('product_id' => (int)$detailRow['id']));
            $comments = array();
            while ($commentRow = $commentsStmt->fetch(PDO::FETCH_ASSOC)) {
                $author = isset($commentRow['author_name']) && trim((string)$commentRow['author_name']) !== ''
                    ? trim((string)$commentRow['author_name'])
                    : (isset($commentRow['user_name']) && trim((string)$commentRow['user_name']) !== '' ? trim((string)$commentRow['user_name']) : 'Customer');

                $createdAt = isset($commentRow['created_at']) ? (string)$commentRow['created_at'] : date('Y-m-d H:i:s');
                $comments[] = array(
                    'id' => (int)$commentRow['id'],
                    'author' => $author,
                    'content' => (string)$commentRow['content'],
                    'created_at' => $createdAt,
                    'created_at_human' => date('d.m.Y H:i', strtotime($createdAt)),
                );
            }
            $productPageContext['comments'] = $comments;
            $productPageContext['product']['comment_count'] = count($comments);
        } else {
            http_response_code(404);
        }
    }

    if ($script === 'payment-success.php') {
        if (!$isLoggedIn) {
            $fallbackPaymentPath = Helpers::routeUrl('payment.success') ?: '/';
            $returnTo = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : $fallbackPaymentPath;
            Helpers::redirect(Helpers::loginUrl(false, $returnTo));
        }

        $allowedMethods = array('card', 'balance', 'eft', 'crypto');
        $methodParam = isset($_GET['method']) ? strtolower(trim((string)$_GET['method'])) : 'card';
        if (!in_array($methodParam, $allowedMethods, true)) {
            $methodParam = 'card';
        }
        $paymentSuccessContext['method'] = $methodParam;

        $reference = isset($_GET['reference']) ? trim((string)$_GET['reference']) : '';
        $paymentSuccessContext['reference'] = $reference;

        $ordersParam = isset($_GET['orders']) ? trim((string)$_GET['orders']) : '';
        $orderIds = array();
        if ($ordersParam !== '') {
            foreach (preg_split('/[\\s,]+/', $ordersParam) as $part) {
                $value = (int)$part;
                if ($value > 0) {
                    $orderIds[] = $value;
                }
            }
        }

        $orderIds = array_values(array_unique($orderIds));

        $canonicalBase = Helpers::routeUrl('payment.success') ?: '/odeme/basarili/';
        $canonicalQuery = array('method' => $methodParam);
        if ($orderIds) {
            $canonicalQuery['orders'] = implode(',', $orderIds);
        }
        if ($reference !== '') {
            $canonicalQuery['reference'] = $reference;
        }
        if ($methodParam === 'balance' && isset($_GET['balance'])) {
            $canonicalQuery['balance'] = (string)$_GET['balance'];
        }
        $canonicalUrl = Helpers::urlWithQuery($canonicalBase, $canonicalQuery);
        Helpers::setCanonicalUrl(Helpers::absoluteUrl($canonicalUrl));

        if ($methodParam === 'balance' && isset($_GET['balance'])) {
            $remainingBalance = (float)str_replace(',', '.', (string)$_GET['balance']);
            $paymentSuccessContext['remaining_balance'] = $remainingBalance;
        }

        $orders = array();
        $orderTotal = 0.0;
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $params = $orderIds;
            $params[] = $userId = (int)$_SESSION['user']['id'];

            $ordersStmt = $pdo->prepare('
                SELECT
                    po.id,
                    po.product_id,
                    po.quantity,
                    po.price,
                    po.status,
                    po.created_at,
                    po.source,
                    pr.name AS product_name,
                    pr.sku AS product_sku
                FROM product_orders po
                INNER JOIN products pr ON pr.id = po.product_id
                WHERE po.id IN (' . $placeholders . ')
                  AND po.user_id = ?
                ORDER BY po.created_at DESC
            ');
            $ordersStmt->execute($params);
            while ($row = $ordersStmt->fetch(PDO::FETCH_ASSOC)) {
                $orders[] = array(
                    'id' => (int)$row['id'],
                    'product_name' => (string)$row['product_name'],
                    'product_sku' => isset($row['product_sku']) ? (string)$row['product_sku'] : null,
                    'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 1,
                    'price' => (float)$row['price'],
                    'price_formatted' => Helpers::formatCurrency((float)$row['price'], Helpers::activeCurrency()),
                    'status' => (string)$row['status'],
                    'created_at' => (string)$row['created_at'],
                    'source' => isset($row['source']) ? (string)$row['source'] : 'panel',
                );
                $orderTotal += (float)$row['price'];
            }
        }

        $paymentSuccessContext['orders'] = $orders;
        $paymentSuccessContext['orderIds'] = $orderIds;
        $paymentSuccessContext['total'] = $orderTotal;
        $paymentSuccessContext['total_formatted'] = Helpers::formatCurrency($orderTotal, Helpers::activeCurrency());

        if (in_array($methodParam, array('eft'), true)) {
            $bankStmt = $pdo->query('SELECT id, bank_name, account_holder, iban, branch, description FROM bank_accounts WHERE is_active = 1 ORDER BY sort_order ASC, bank_name ASC');
            $bankAccounts = array();
            while ($bank = $bankStmt->fetch(PDO::FETCH_ASSOC)) {
                $bankAccounts[] = array(
                    'id' => (int)$bank['id'],
                    'bank_name' => (string)$bank['bank_name'],
                    'account_holder' => (string)$bank['account_holder'],
                    'iban' => (string)$bank['iban'],
                    'branch' => isset($bank['branch']) ? (string)$bank['branch'] : null,
                    'description' => isset($bank['description']) ? (string)$bank['description'] : null,
                );
            }
            $paymentSuccessContext['bankAccounts'] = $bankAccounts;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
            if ($action === 'bank_transfer_notify') {
                $errors = array();
                $csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
                if (!Helpers::verifyCsrf($csrfToken)) {
                    $errors[] = 'Gecersiz istek. Lutfen sayfayi yenileyip tekrar deneyin.';
                }

                $bankAccountId = isset($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : 0;
                $amountInput = isset($_POST['amount']) ? preg_replace('/[^0-9.,-]/', '', (string)$_POST['amount']) : '';
                $amountInput = str_replace(',', '.', $amountInput);
                $amountValue = $amountInput !== '' ? (float)$amountInput : 0.0;
                if ($amountValue <= 0) {
                    $errors[] = 'Gecerli bir tutar giriniz.';
                }

                $transferRaw = isset($_POST['transfer_datetime']) ? trim((string)$_POST['transfer_datetime']) : '';
                $transferDatetime = null;
                if ($transferRaw !== '') {
                    $normalised = str_replace('T', ' ', $transferRaw);
                    $timestamp = strtotime($normalised);
                    if ($timestamp === false) {
                        $errors[] = 'Transfer tarihi gecersiz.';
                    } else {
                        $transferDatetime = date('Y-m-d H:i:s', $timestamp);
                    }
                } else {
                    $errors[] = 'Transfer tarihini seciniz.';
                }

                $note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';

                if (!$errors && in_array($methodParam, array('eft'), true)) {
                    if ($bankAccountId <= 0) {
                        $errors[] = 'Banka secimi zorunludur.';
                    } else {
                        $bankCheck = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = :id AND is_active = 1 LIMIT 1');
                        $bankCheck->execute(array('id' => $bankAccountId));
                        if (!$bankCheck->fetchColumn()) {
                            $errors[] = 'Secilen banka bilgisi gecerli degil.';
                        }
                    }
                }

                $receiptPath = null;
                if (isset($_FILES['receipt']) && isset($_FILES['receipt']['tmp_name']) && $_FILES['receipt']['tmp_name'] !== '') {
                    $uploadError = (int)$_FILES['receipt']['error'];
                    if ($uploadError === UPLOAD_ERR_OK) {
                        $allowedExtensions = array('jpg', 'jpeg', 'png', 'pdf');
                        $originalName = isset($_FILES['receipt']['name']) ? (string)$_FILES['receipt']['name'] : '';
                        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowedExtensions, true)) {
                            $errors[] = 'Yalnizca JPG, PNG veya PDF dosyalari yukleyebilirsiniz.';
                        } else {
                            $uploadDirectory = __DIR__ . '/storage/receipts';
                            if (!is_dir($uploadDirectory)) {
                                @mkdir($uploadDirectory, 0775, true);
                            }
                            if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
                                $errors[] = 'Dekont klasoru olusturulamadi. Lutfen sistem yoneticisine basvurun.';
                            } else {
                                $fileName = bin2hex(random_bytes(12)) . '.' . $extension;
                                $destination = $uploadDirectory . '/' . $fileName;
                                if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $destination)) {
                                    $errors[] = 'Dekont dosyasi yuklenemedi.';
                                } else {
                                    $receiptPath = '/storage/receipts/' . $fileName;
                                }
                            }
                        }
                    } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
                        $errors[] = 'Dekont dosyasi yuklenirken hata olustu.';
                    }
                }

                if (!$orderIds) {
                    $errors[] = 'Bildirim icin gecerli siparis bulunamadi.';
                }

                if (!$errors) {
                    $notifyStmt = $pdo->prepare('
                        INSERT INTO bank_transfer_notifications
                            (bank_account_id, user_id, order_reference, amount, transfer_datetime, receipt_path, status, notes, created_at)
                        VALUES
                            (:bank_account_id, :user_id, :order_reference, :amount, :transfer_datetime, :receipt_path, :status, :notes, NOW())
                    ');
                    $notifyStmt->execute(array(
                        'bank_account_id' => $bankAccountId > 0 ? $bankAccountId : null,
                        'user_id' => (int)$_SESSION['user']['id'],
                        'order_reference' => $reference !== '' ? $reference : ('ORD-' . implode('-', $orderIds)),
                        'amount' => $amountValue,
                        'transfer_datetime' => $transferDatetime,
                        'receipt_path' => $receiptPath,
                        'status' => 'pending',
                        'notes' => $note !== '' ? $note : null,
                    ));

                    Helpers::setFlash('bank_notify_success', 'Transfer bildiriminiz alindi. Finans ekibimiz en kisa surede inceleyecektir.');
                    Helpers::setFlash('bank_notify_errors', array());
                    Helpers::redirect($canonicalUrl . '&notified=1');
                }

                Helpers::setFlash('bank_notify_errors', $errors);
                Helpers::redirect($canonicalUrl . '&notify_error=1');
            }
        }
    }

    $products = array();
    $productsById = array();
    $productsByCategory = array();

    $hasShortDescriptionColumn = Database::tableHasColumn('products', 'short_description');
    $hasImageUrlColumn = Database::tableHasColumn('products', 'image_url');
    $hasViewsCountColumn = Database::tableHasColumn('products', 'views_count');

    $productColumns = array(
        'p.id',
        'p.category_id',
        'p.name',
        'p.description',
        'p.price',
        'p.cost_price_try',
        'p.status',
        'p.created_at',
        'p.updated_at',
        'c.name AS category_name',
    );

    $productColumns[] = $hasShortDescriptionColumn ? 'p.short_description' : 'NULL AS short_description';
    $productColumns[] = $hasImageUrlColumn ? 'p.image_url' : 'NULL AS image_url';
    $productColumns[] = $hasViewsCountColumn ? 'p.views_count' : '0 AS views_count';

    $productSql = 'SELECT ' . implode(', ', $productColumns) .
        " FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = 'active' ORDER BY COALESCE(p.updated_at, p.created_at) DESC";
    $productStatement = $pdo->query($productSql);
    if ($productStatement instanceof \PDOStatement) {
        while ($row = $productStatement->fetch(PDO::FETCH_ASSOC)) {
            $productId = (int)$row['id'];
            $categoryId = isset($row['category_id']) ? (int)$row['category_id'] : 0;

            $products[] = $row;
            $productsById[$productId] = $row;

            if (!isset($productsByCategory[$categoryId])) {
                $productsByCategory[$categoryId] = array();
            }
            $productsByCategory[$categoryId][] = $row;
        }
    }

    $sectionsConfig = decodeSettingArray('homepage_sections');
    $sections = array();
    $usedCategoryIds = array();

    if ($sectionsConfig) {
        foreach ($sectionsConfig as $config) {
            if (!is_array($config)) {
                continue;
            }

            $categoryId = isset($config['category_id']) ? (int)$config['category_id'] : 0;
            if ($categoryId <= 0 || !isset($categoriesById[$categoryId]) || empty($productsByCategory[$categoryId])) {
                continue;
            }

            $category = $categoriesById[$categoryId];
            $presentation = resolveCategoryPresentation($category, $categoryStyleOverrides, $defaultCategoryStyles, $defaultAccent, $defaultCategoryImage);
            $sectionId = isset($config['id']) && $config['id'] !== '' ? Helpers::slugify($config['id']) : $presentation['slug'];
            $title = isset($config['title']) && trim((string)$config['title']) !== '' ? trim((string)$config['title']) : $category['name'];
            $accent = isset($config['accent']) && trim((string)$config['accent']) !== '' ? trim((string)$config['accent']) : $presentation['accent'];

            $productCards = array();
            foreach (array_slice($productsByCategory[$categoryId], 0, 4) as $product) {
                $productCards[] = mapProductCard($product, $presentation['image'], $categoryId, $presentation['slug']);
            }

            if ($productCards) {
                $sections[] = array(
                    'id' => $sectionId,
                    'category_id' => $categoryId,
                    'title' => $title,
                    'accent' => $accent,
                    'products' => $productCards,
                );
                $usedCategoryIds[$categoryId] = true;
            }
        }
    }

    if (!$sections) {
        $ranked = array();

        foreach ($productsByCategory as $categoryId => $items) {
            if (!isset($categoriesById[$categoryId]) || !$items) {
                continue;
            }

            $ranked[] = array(
                'category' => $categoriesById[$categoryId],
                'products' => $items,
                'count' => count($items),
            );
        }

        usort($ranked, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        foreach (array_slice($ranked, 0, 4) as $entry) {
            $category = $entry['category'];
            $categoryId = (int)$category['id'];
            $presentation = resolveCategoryPresentation($category, $categoryStyleOverrides, $defaultCategoryStyles, $defaultAccent, $defaultCategoryImage);

            $productCards = array();
            foreach (array_slice($entry['products'], 0, 4) as $product) {
                $productCards[] = mapProductCard($product, $presentation['image'], $categoryId, $presentation['slug']);
            }

            if ($productCards) {
                $sections[] = array(
                    'id' => $presentation['slug'],
                    'category_id' => $categoryId,
                    'title' => $category['name'],
                    'accent' => $presentation['accent'],
                    'products' => $productCards,
                );
                $usedCategoryIds[$categoryId] = true;
            }

            if (count($sections) >= 4) {
                break;
            }
        }
    }

    $homeSections = $sections;

    $featuredConfig = decodeSettingArray('homepage_featured_products');
    $featured = array();

    if ($featuredConfig) {
        foreach ($featuredConfig as $entry) {
            $productId = null;
            $customDescription = null;

            if (is_array($entry)) {
                if (isset($entry['product_id'])) {
                    $productId = (int)$entry['product_id'];
                } elseif (isset($entry['id'])) {
                    $productId = (int)$entry['id'];
                } elseif (isset($entry['product'])) {
                    $productId = (int)$entry['product'];
                }

                if (isset($entry['description']) && trim((string)$entry['description']) !== '') {
                    $customDescription = trim((string)$entry['description']);
                }
            } else {
                $productId = (int)$entry;
            }

            if ($productId && isset($productsById[$productId])) {
                $product = $productsById[$productId];
                $categoryId = isset($product['category_id']) ? (int)$product['category_id'] : null;
                $category = $categoryId !== null && isset($categoriesById[$categoryId]) ? $categoriesById[$categoryId] : null;
                $presentation = resolveCategoryPresentation($category, $categoryStyleOverrides, $defaultCategoryStyles, $defaultAccent, $defaultCategoryImage);

                $card = mapProductCard($product, $presentation['image'], $categoryId, $presentation['slug']);
                if ($customDescription !== null) {
                    $card['description'] = $customDescription;
                }

                $featured[] = $card;
            }
        }
    }

    if (!$featured) {
        foreach (array_slice($products, 0, 4) as $product) {
            $categoryId = isset($product['category_id']) ? (int)$product['category_id'] : null;
            $category = $categoryId !== null && isset($categoriesById[$categoryId]) ? $categoriesById[$categoryId] : null;
            $presentation = resolveCategoryPresentation($category, $categoryStyleOverrides, $defaultCategoryStyles, $defaultAccent, $defaultCategoryImage);
            $featured[] = mapProductCard($product, $presentation['image'], $categoryId, $presentation['slug']);
        }
    }

    $featuredProducts = $featured;

    $catalog = array();
    foreach ($products as $product) {
        $categoryId = isset($product['category_id']) ? (int)$product['category_id'] : null;
        $category = $categoryId !== null && isset($categoriesById[$categoryId]) ? $categoriesById[$categoryId] : null;
        $presentation = resolveCategoryPresentation($category, $categoryStyleOverrides, $defaultCategoryStyles, $defaultAccent, $defaultCategoryImage);
        $catalog[] = mapProductCard($product, $presentation['image'], $categoryId, $presentation['slug']);
    }

    if (!$catalog && $homeSections) {
        foreach ($homeSections as $section) {
            foreach ($section['products'] as $product) {
                $catalog[] = $product;
            }
        }
    }

    $catalogProducts = $catalog;

    $popularCategories = array();
    foreach ($homeSections as $section) {
        $popularCategories[] = $section['title'];
    }

    if (!$popularCategories) {
        foreach (array_slice($categoriesById, 0, 6) as $category) {
            $popularCategories[] = $category['name'];
        }
    }

    $navCategories = array();
    $topCategories = isset($categoryChildren[0]) ? $categoryChildren[0] : array();

    $topCategories = array_slice($topCategories, 0, 12);

    foreach ($topCategories as $category) {
        $presentation = resolveCategoryPresentation($category, $categoryStyleOverrides, $defaultCategoryStyles, $defaultAccent, $defaultCategoryImage);
        $children = array();
        if (isset($categoryChildren[$category['id']])) {
            foreach ($categoryChildren[$category['id']] as $child) {
                $childSlug = $child['slug'] !== '' ? $child['slug'] : ('category-' . (int)$child['id']);
                $children[] = array(
                    'id' => (int)$child['id'],
                    'name' => $child['name'],
                    'slug' => $childSlug,
                );
            }
        }

        $navCategories[] = array(
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'slug' => $presentation['slug'],
            'icon' => $category['icon'] ?? $presentation['icon'],
            'image' => !empty($category['image']) ? $category['image'] : $presentation['image'],
            'children' => $children,
        );
    }

    if (!$navCategories) {
        foreach ($defaultHomeSections as $section) {
            $navCategories[] = array(
                'id' => isset($section['id']) ? $section['id'] : 0,
                'name' => $section['title'],
                'slug' => isset($section['id']) ? $section['id'] : Helpers::slugify($section['title']),
                'icon' => '',
                'image' => $section['products'][0]['image'] ?? $defaultCategoryImage,
                'children' => array(),
            );
        }
    }
} catch (\Throwable $exception) {
    // Silently fall back to static data if live integration is not available.
}

if (!$featuredProducts) {
    $featuredProducts = $defaultFeaturedProducts;
}

if (!$homeSections) {
    $homeSections = $defaultHomeSections;
}

if (!$homepageBlogPosts) {
    $homepageBlogPosts = $defaultBlogPosts;
}

if (!$catalogProducts) {
    foreach ($homeSections as $section) {
        if (!empty($section['products'])) {
            $catalogProducts = array_merge($catalogProducts, $section['products']);
        }
    }
}

if (!$popularCategories) {
    $popularCategories = array_column($homeSections, 'title');
}

if ($navCategories) {
    $GLOBALS['theme_nav_categories'] = $navCategories;
} elseif (isset($GLOBALS['theme_nav_categories'])) {
    unset($GLOBALS['theme_nav_categories']);
}

$cartSnapshot = Cart::snapshot();
$GLOBALS['theme_cart_summary'] = $cartSnapshot;

$accountTabs = array('profile', 'password', 'orders', 'balance', 'support', 'sessions');
$accountFeedback = array(
    'activeTab' => 'profile',
    'messages' => array(),
);
foreach ($accountTabs as $tabKey) {
    $accountFeedback['messages'][$tabKey] = array(
        'errors' => array(),
        'success' => '',
    );
}

$accountData = array(
    'user' => $isLoggedIn ? $_SESSION['user'] : null,
    'orders' => array(),
    'transactions' => array(),
    'balanceRequests' => array(),
    'tickets' => array(),
    'ticketMessages' => array(),
    'sessions' => array(),
    'balance' => $isLoggedIn && isset($_SESSION['user']['balance']) ? (float)$_SESSION['user']['balance'] : 0.0,
);

if ($script === 'account.php') {
    if (!$isLoggedIn) {
        $fallbackAccountPath = Helpers::accountUrl() ?: '/';
        $returnTo = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : $fallbackAccountPath;
        Helpers::redirect(Helpers::loginUrl(false, $returnTo));
    }

    $requestedTab = isset($_GET['tab']) ? strtolower(trim((string)$_GET['tab'])) : 'profile';
    if (!in_array($requestedTab, $accountTabs, true)) {
        $requestedTab = 'profile';
    }
    $accountFeedback['activeTab'] = $requestedTab;

    if (!$pdo) {
        try {
            $pdo = Database::connection();
        } catch (\Throwable $exception) {
            $pdo = null;
            $accountFeedback['messages'][$requestedTab]['errors'][] = 'Veritabani baglantisi kurulamadigindan islem tamamlanamadi.';
        }
    }

    $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
    if ($pdo && $userId > 0) {
        $freshUser = Auth::findUser($userId);
        if ($freshUser) {
            $_SESSION['user'] = $freshUser;
            $accountData['user'] = $freshUser;
            $accountData['balance'] = isset($freshUser['balance']) ? (float)$freshUser['balance'] : 0.0;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postTab = isset($_POST['tab']) ? strtolower(trim((string)$_POST['tab'])) : $accountFeedback['activeTab'];
        if (in_array($postTab, $accountTabs, true)) {
            $accountFeedback['activeTab'] = $postTab;
        } else {
            $postTab = $accountFeedback['activeTab'];
        }

        $messageBag =& $accountFeedback['messages'][$postTab];
        $tokenValue = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

        if (!Helpers::verifyCsrf($tokenValue)) {
            $messageBag['errors'][] = 'Oturum dogrulamasi basarisiz oldu. Lutfen tekrar deneyin.';
        } elseif (!$pdo || $userId <= 0) {
            $messageBag['errors'][] = 'Islem su anda tamamlanamadi. Lutfen daha sonra tekrar deneyin.';
        } else {
            switch ($action) {
                case 'update_profile':
                    $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
                    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

                    if ($name === '') {
                        $messageBag['errors'][] = 'Ad soyad alani bos birakilamaz.';
                    }

                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $messageBag['errors'][] = 'Gecerli bir e-posta adresi girin.';
                    }

                    if (!$messageBag['errors']) {
                        $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :id');
                        $existsStmt->execute(array('email' => $email, 'id' => $userId));
                        if ((int)$existsStmt->fetchColumn() > 0) {
                            $messageBag['errors'][] = 'Bu e-posta adresi baska bir hesap tarafindan kullaniliyor.';
                        }
                    }

                    if (!$messageBag['errors']) {
                        $pdo->prepare('UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id')->execute(array(
                            'name' => $name,
                            'email' => $email,
                            'id' => $userId,
                        ));
                        $freshUser = Auth::findUser($userId);
                        if ($freshUser) {
                            $_SESSION['user'] = $freshUser;
                            $accountData['user'] = $freshUser;
                            $accountData['balance'] = isset($freshUser['balance']) ? (float)$freshUser['balance'] : 0.0;
                        }
                        $messageBag['success'] = 'Profil bilgileriniz guncellendi.';
                    }
                    break;

                case 'change_password':
                    $currentPassword = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
                    $newPassword = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
                    $confirmPassword = isset($_POST['new_password_confirmation']) ? (string)$_POST['new_password_confirmation'] : '';

                    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                        $messageBag['errors'][] = 'Tum parola alanlarini doldurun.';
                    }

                    $storedHash = isset($_SESSION['user']['password_hash']) ? (string)$_SESSION['user']['password_hash'] : '';
                    if (!$messageBag['errors'] && !password_verify($currentPassword, $storedHash)) {
                        $messageBag['errors'][] = 'Mevcut parolaniz dogrulanamadi.';
                    }

                    if (!$messageBag['errors'] && strlen($newPassword) < 6) {
                        $messageBag['errors'][] = 'Yeni parola en az 6 karakter olmalidir.';
                    }

                    if (!$messageBag['errors'] && $newPassword !== $confirmPassword) {
                        $messageBag['errors'][] = 'Yeni parola onayi eslesmiyor.';
                    }

                    if (!$messageBag['errors']) {
                        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id')->execute(array(
                            'hash' => $newHash,
                            'id' => $userId,
                        ));
                        $_SESSION['user']['password_hash'] = $newHash;
                        $messageBag['success'] = 'Parolaniz guncellendi.';
                    }
                    break;

                case 'create_balance_request':
                    $amountInput = isset($_POST['amount']) ? trim((string)$_POST['amount']) : '0';
                    $normalizedAmount = str_replace(array(',', ' '), array('.', ''), $amountInput);
                    $amountValue = (float)$normalizedAmount;
                    $paymentMethod = isset($_POST['payment_method']) ? trim((string)$_POST['payment_method']) : '';
                    $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

                    if ($amountValue <= 0) {
                        $messageBag['errors'][] = 'Yuklenecek tutar 0 dan buyuk olmalidir.';
                    }

                    if ($paymentMethod === '') {
                        $messageBag['errors'][] = 'Odeme yontemi belirtmelisiniz.';
                    }

                    if (!$messageBag['errors']) {
                        $stmt = $pdo->prepare('INSERT INTO balance_requests (user_id, amount, payment_method, notes) VALUES (:user_id, :amount, :payment_method, :notes)');
                        $stmt->execute(array(
                            'user_id' => $userId,
                            'amount' => $amountValue,
                            'payment_method' => $paymentMethod,
                            'notes' => $notes !== '' ? $notes : null,
                        ));
                        $messageBag['success'] = 'Bakiye yukleme talebiniz alindi.';
                    }
                    break;

                case 'create_ticket':
                    $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
                    $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
                    $priority = isset($_POST['priority']) ? strtolower(trim((string)$_POST['priority'])) : 'normal';
                    if (!in_array($priority, array('low', 'normal', 'high'), true)) {
                        $priority = 'normal';
                    }

                    if ($subject === '') {
                        $messageBag['errors'][] = 'Destek konusu bos birakilamaz.';
                    }

                    if ($message === '') {
                        $messageBag['errors'][] = 'Mesaj icerigi bos birakilamaz.';
                    }

                    if (!$messageBag['errors']) {
                        $stmt = $pdo->prepare('INSERT INTO support_tickets (user_id, subject, priority, status, created_at) VALUES (:user_id, :subject, :priority, :status, NOW())');
                        $stmt->execute(array(
                            'user_id' => $userId,
                            'subject' => $subject,
                            'priority' => $priority,
                            'status' => 'open',
                        ));
                        $ticketId = (int)$pdo->lastInsertId();
                        $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute(array(
                            'ticket_id' => $ticketId,
                            'user_id' => $userId,
                            'message' => $message,
                        ));
                        $messageBag['success'] = 'Destek talebiniz olusturuldu.';
                    }
                    break;

                case 'reply_ticket':
                    $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
                    $replyMessage = isset($_POST['message']) ? trim((string)$_POST['message']) : '';

                    if ($ticketId <= 0 || $replyMessage === '') {
                        $messageBag['errors'][] = 'Gecerli bir destek talebi ve mesaj giriniz.';
                        break;
                    }

                    $ticketStmt = $pdo->prepare('SELECT id FROM support_tickets WHERE id = :id AND user_id = :user_id');
                    $ticketStmt->execute(array('id' => $ticketId, 'user_id' => $userId));
                    if (!$ticketStmt->fetch(PDO::FETCH_ASSOC)) {
                        $messageBag['errors'][] = 'Destek talebi bulunamadi.';
                        break;
                    }

                    $pdo->prepare('INSERT INTO support_messages (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())')->execute(array(
                        'ticket_id' => $ticketId,
                        'user_id' => $userId,
                        'message' => $replyMessage,
                    ));
                    $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id')->execute(array(
                        'status' => 'open',
                        'id' => $ticketId,
                    ));
                    $messageBag['success'] = 'Mesajiniz gonderildi.';
                    break;

                default:
                    $messageBag['errors'][] = 'Bilinmeyen bir islem secildi.';
                    break;
            }
        }
    }

    if ($pdo && $userId > 0) {
        try {
            $ordersStmt = $pdo->prepare('SELECT po.*, p.name AS product_name FROM product_orders po INNER JOIN products p ON po.product_id = p.id WHERE po.user_id = :user_id ORDER BY po.created_at DESC LIMIT 20');
            $ordersStmt->execute(array('user_id' => $userId));
            $accountData['orders'] = $ordersStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (\Throwable $exception) {
            $accountFeedback['messages']['orders']['errors'][] = 'Siparisler yuklenirken bir hata olustu.';
        }

        try {
            $transactionsStmt = $pdo->prepare('SELECT * FROM balance_transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
            $transactionsStmt->execute(array('user_id' => $userId));
            $accountData['transactions'] = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (\Throwable $exception) {
            $accountFeedback['messages']['balance']['errors'][] = 'Bakiye hareketleri yuklenemedi.';
        }

        try {
            $requestStmt = $pdo->prepare('SELECT * FROM balance_requests WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
            $requestStmt->execute(array('user_id' => $userId));
            $accountData['balanceRequests'] = $requestStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (\Throwable $exception) {
            $accountFeedback['messages']['balance']['errors'][] = 'Bakiye talepleri yuklenemedi.';
        }

        try {
            $ticketStmt = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
            $ticketStmt->execute(array('user_id' => $userId));
            $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
            $accountData['tickets'] = $tickets;

            if ($tickets) {
                $ticketIds = array_map(function ($ticket) {
                    return (int)$ticket['id'];
                }, $tickets);
                $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                $messagesStmt = $pdo->prepare('SELECT sm.*, u.name AS author_name, u.role AS author_role FROM support_messages sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.ticket_id IN (' . $placeholders . ') ORDER BY sm.created_at ASC');
                $messagesStmt->execute($ticketIds);
                $grouped = array();
                while ($row = $messagesStmt->fetch(PDO::FETCH_ASSOC)) {
                    $ticketId = (int)$row['ticket_id'];
                    if (!isset($grouped[$ticketId])) {
                        $grouped[$ticketId] = array();
                    }
                    $grouped[$ticketId][] = $row;
                }
                $accountData['ticketMessages'] = $grouped;
            }
        } catch (\Throwable $exception) {
            $accountFeedback['messages']['support']['errors'][] = 'Destek kayitlari yuklenemedi.';
        }

        try {
            $sessionStmt = $pdo->prepare('SELECT * FROM user_sessions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
            $sessionStmt->execute(array('user_id' => $userId));
            $accountData['sessions'] = $sessionStmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (\Throwable $exception) {
            $accountFeedback['messages']['sessions']['errors'][] = 'Oturum gecmisi yuklenemedi.';
        }
    }
}

switch ($script) {
    case 'product.php':
        if (!$productPageContext['product']) {
            http_response_code(404);
            theme_render('404', array(
                'pageTitle' => 'Product Not Found',
                'isLoggedIn' => $isLoggedIn,
            ));
        } else {
            theme_render('product', array(
                'pageTitle' => $productPageContext['product']['name'],
                'isLoggedIn' => $isLoggedIn,
                'productPage' => $productPageContext,
                'user' => isset($_SESSION['user']) ? $_SESSION['user'] : null,
            ));
        }
        exit;

    case 'payment-success.php':
        theme_render('payment-success', array(
            'pageTitle' => 'Payment Status',
            'isLoggedIn' => $isLoggedIn,
            'payment' => $paymentSuccessContext,
            'user' => isset($_SESSION['user']) ? $_SESSION['user'] : null,
        ));
        exit;

    case 'catalog.php':
        theme_render('catalog', array(
            'pageTitle' => 'Catalog',
            'products' => $catalogProducts,
            'isLoggedIn' => $isLoggedIn,
        ));
        exit;

    case 'blog.php':
        $blogTitle = 'Blog';
        if ($blogContext['view'] === 'detail' && isset($blogContext['post']['title'])) {
            $blogTitle = (string)$blogContext['post']['title'];
        } elseif ($blogContext['view'] === 'list' && isset($blogContext['filters']['category'])) {
            $blogTitle = 'Kategori: ' . ucfirst(str_replace('-', ' ', $blogContext['filters']['category']));
        } elseif ($blogContext['view'] === 'list' && isset($blogContext['filters']['tag'])) {
            $blogTitle = 'Etiket: ' . ucfirst(str_replace('-', ' ', $blogContext['filters']['tag']));
        }

        $resolvedBlogTitle = isset($blogContext['page_title']) && $blogContext['page_title'] !== ''
            ? (string)$blogContext['page_title']
            : $blogTitle;
        Helpers::setPageTitle($resolvedBlogTitle);

        theme_render('blog', array(
            'pageTitle' => $resolvedBlogTitle,
            'isLoggedIn' => $isLoggedIn,
            'blog' => $blogContext,
        ));
        exit;

    case 'page.php':
        if (!$pageContext['page']) {
            Helpers::setPageTitle('Sayfa bulunamadı');
            http_response_code(404);
            theme_render('404', array(
                'pageTitle' => 'Sayfa bulunamadı',
                'isLoggedIn' => $isLoggedIn,
            ));
        } else {
            $pageResolvedTitle = isset($pageContext['page_title']) && $pageContext['page_title'] !== ''
                ? (string)$pageContext['page_title']
                : (string)$pageContext['page']['title'];
            Helpers::setPageTitle($pageResolvedTitle);
            theme_render('page', array(
                'pageTitle' => $pageResolvedTitle,
                'isLoggedIn' => $isLoggedIn,
                'pageContext' => $pageContext,
            ));
        }
        exit;

    case 'login.php':
        theme_render('login', array(
            'pageTitle' => 'Sign In',
            'isLoggedIn' => $isLoggedIn,
            'auth' => $authContext,
        ));
        exit;

    case 'register.php':
        theme_render('register', array(
            'pageTitle' => 'Register',
            'isLoggedIn' => $isLoggedIn,
            'auth' => $authContext,
        ));
        exit;

    case 'account.php':
        theme_render('account', array(
            'pageTitle' => 'Hesabim',
            'isLoggedIn' => $isLoggedIn,
            'account' => $accountData,
            'accountFeedback' => $accountFeedback,
            'accountTabs' => $accountTabs,
        ));
        exit;

    case 'cart.php':
        theme_render('cart', array(
            'pageTitle' => 'Sepetim',
            'isLoggedIn' => $isLoggedIn,
            'cart' => $cartSnapshot,
            'user' => isset($_SESSION['user']) ? $_SESSION['user'] : null,
        ));
        exit;

    case 'support.php':
        theme_render('support', array(
            'pageTitle' => 'Support',
            'isLoggedIn' => $isLoggedIn,
        ));
        exit;

    case 'contact.php':
        theme_render('contact', array(
            'pageTitle' => 'Contact',
            'isLoggedIn' => $isLoggedIn,
        ));
        exit;

    case '404.php':
        theme_render('404', array(
            'pageTitle' => 'Not Found',
            'isLoggedIn' => $isLoggedIn,
        ));
        exit;
}

theme_render('index', array(
    'pageTitle' => 'Home',
    'slider' => $sliderConfig,
    'featuredProducts' => $featuredProducts,
    'popularCategories' => $popularCategories,
    'sections' => $homeSections,
    'blogPosts' => $homepageBlogPosts,
    'isLoggedIn' => $isLoggedIn,
));


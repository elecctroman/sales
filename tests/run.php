<?php
session_start();

require_once __DIR__ . '/../app/Lang.php';
require_once __DIR__ . '/../app/Helpers.php';

use App\Lang;
use App\Helpers;

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'off';

Lang::boot();
Lang::setLocale('en');

$assertions = array();
$assertions[] = Lang::locale() === 'tr';
$assertions[] = Lang::htmlLocale() === 'tr';

$slug = Helpers::slugify('Çılgın Şövalye 2024');
$assertions[] = $slug === 'cilgin-sovalye-2024';

$categoryPath = Helpers::categoryUrl('oyun/test');
$assertions[] = $categoryPath === '/kategori/oyun/test';

$absoluteCategory = Helpers::categoryUrl('deneme', true);
$assertions[] = strpos($absoluteCategory, '/kategori/deneme') !== false;

$sanitized = Helpers::sanitize('<script>alert("x")</script>');
$assertions[] = $sanitized === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;';

$invalidInput = "Ge\xffi";
$assertions[] = mb_check_encoding(htmlspecialchars_decode(Helpers::sanitize($invalidInput)), 'UTF-8');

$pageUrl = Helpers::pageUrl('Gizlilik Politikası');
$assertions[] = $pageUrl === '/page/gizlilik-politikasi';

$unsafeHtml = '<p onclick="evil()">Merhaba <a href="javascript:alert(1)">test</a></p><script>alert(1)</script>';
$cleanHtml = Helpers::sanitizePageHtml($unsafeHtml);
$assertions[] = strpos($cleanHtml, 'onclick') === false;
$assertions[] = strpos($cleanHtml, 'javascript:') === false;
$assertions[] = strpos($cleanHtml, '<script') === false;

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "Testler başarısız oldu.\n");
    exit(1);
}

echo "Tüm testler başarıyla tamamlandı.\n";

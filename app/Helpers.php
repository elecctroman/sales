<?php

namespace App;

class Helpers
{
    /**
     * @return string
     */
    public static function csrfToken()
    {
        self::ensureSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * @param string $token
     * @return bool
     */
    public static function verifyCsrf($token)
    {
        self::ensureSession();

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * @param string $path
     * @return void
     */
    public static function redirect($path)
    {
        header('Location: ' . $path);
        exit;
    }

    /**
     * @return void
     */
    private static function ensureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * @return string
     */
    public static function siteName()
    {
        try {
            $value = Settings::get('site_name');
            $value = $value !== null ? trim($value) : '';
        } catch (\Throwable $exception) {
            return 'Dijital Satış Platformu';
        }

        return $value !== '' ? $value : 'Dijital Satış Platformu';
    }

    /**
     * @return string
     */
    public static function siteTagline()
    {
        try {
            $value = Settings::get('site_tagline');
            $value = $value !== null ? trim($value) : '';
        } catch (\Throwable $exception) {
            return 'Dijital ürün satışlarınızı yönetin.';
        }

        return $value !== '' ? $value : 'Dijital ürün satışlarınızı yönetin.';
    }

    /**
     * @return string
     */
    public static function seoDescription()
    {
        if (isset($GLOBALS['pageMetaDescription']) && is_string($GLOBALS['pageMetaDescription'])) {
            $override = trim($GLOBALS['pageMetaDescription']);
            if ($override !== '') {
                return $override;
            }
        }

        try {
            $value = Settings::get('seo_meta_description');
            $value = $value !== null ? trim($value) : '';
        } catch (\Throwable $exception) {
            $value = '';
        }

        if ($value !== '') {
            return $value;
        }

        return 'Dijital ürün ve oyun kodu satışınızı tek panelden yönetin.';
    }

    /**
     * @return string
     */
    public static function seoKeywords()
    {
        if (isset($GLOBALS['pageMetaKeywords']) && is_string($GLOBALS['pageMetaKeywords'])) {
            $override = trim($GLOBALS['pageMetaKeywords']);
            if ($override !== '') {
                return $override;
            }
        }

        try {
            $value = Settings::get('seo_meta_keywords');
            $value = $value !== null ? trim($value) : '';
        } catch (\Throwable $exception) {
            $value = '';
        }

        return $value !== '' ? $value : 'dijital ürün, oyun kodu, satış platformu';
    }

    /**
     * @param string $feature
     * @return bool
     */
    public static function featureEnabled($feature)
    {
        return FeatureToggle::isEnabled($feature);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function setFlash($key, $value)
    {
        self::ensureSession();

        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = array();
        }

        $_SESSION['flash'][$key] = $value;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash($key, $default = null)
    {
        self::ensureSession();

        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return $default;
        }

        if (!array_key_exists($key, $_SESSION['flash'])) {
            return $default;
        }

        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);

        if (!$_SESSION['flash']) {
            unset($_SESSION['flash']);
        }

        return $value;
    }

    /**
     * @param string $path
     * @param array $flashes
     * @return void
     */
    public static function redirectWithFlash($path, $flashes = array())
    {
        if (!is_array($flashes)) {
            $flashes = array();
        }

        foreach ($flashes as $key => $value) {
            self::setFlash($key, $value);
        }

        self::redirect($path);
    }

    /**
     * @param string $path
     * @param string $default
     * @return string
     */
    public static function normalizeRedirectPath($path, $default = '/')
    {
        if (!$path) {
            return $default;
        }

        $path = trim($path);

        if ($path === '' || $path === '#') {
            return $default;
        }

        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $default;
        }

        if ($path[0] !== '/') {
            return $default;
        }

        if (strpos($path, '//') === 0) {
            return $default;
        }

        $path = strtok($path, "\r\n");

        return $path ?: $default;
    }

    /**
     * @param string $value
     * @return string
     */
    public static function sanitize($value)
    {
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }
        }

        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param string $html
     * @return string
     */
    public static function sanitizePageHtml($html): string
    {
        $html = is_string($html) ? trim($html) : '';
        if ($html === '') {
            return '';
        }

        if (!class_exists('\\DOMDocument')) {
            return self::sanitize($html);
        }

        $document = new \DOMDocument();
        $previousState = libxml_use_internal_errors(true);

        $wrapper = '<div>' . $html . '</div>';

        $options = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $options |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $options |= LIBXML_HTML_NODEFDTD;
        }

        if (@$document->loadHTML($wrapper, $options) === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            return self::sanitize($html);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $allowed = array(
            'a' => array('href', 'title', 'target', 'rel'),
            'div' => array(),
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'u' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'blockquote' => array(),
            'code' => array(),
            'pre' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'table' => array(),
            'thead' => array(),
            'tbody' => array(),
            'tr' => array(),
            'th' => array(),
            'td' => array(),
            'img' => array('src', 'alt', 'title', 'width', 'height', 'loading'),
        );

        $nodes = array();
        $all = $document->getElementsByTagName('*');
        foreach ($all as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            $tagName = strtolower($node->nodeName);

            if ($tagName === 'script' || $tagName === 'style') {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
                continue;
            }

            if (!isset($allowed[$tagName])) {
                self::unwrapDomNode($node);
                continue;
            }

            for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                $attribute = $node->attributes->item($i);
                if (!$attribute) {
                    continue;
                }

                $attrName = strtolower($attribute->nodeName);
                if (!in_array($attrName, $allowed[$tagName], true)) {
                    $node->removeAttribute($attribute->nodeName);
                    continue;
                }

                $value = trim((string)$attribute->nodeValue);

                if ($attrName === 'href' || $attrName === 'src') {
                    if ($value === '' || preg_match('/^(javascript|data):/i', $value)) {
                        $node->removeAttribute($attribute->nodeName);
                        continue;
                    }

                    if ($attrName === 'href') {
                        if (stripos($value, 'mailto:') === 0 || stripos($value, 'tel:') === 0 || strpos($value, '#') === 0) {
                            // allowed as-is
                        } elseif (!preg_match('#^https?://#i', $value) && strpos($value, '/') !== 0) {
                            $value = '/' . ltrim($value, '/');
                        }
                    }

                    $node->setAttribute($attribute->nodeName, $value);
                }

                if ($attrName === 'target') {
                    $target = strtolower($value);
                    if ($target === '_blank') {
                        $existingRel = $node->getAttribute('rel');
                        $tokens = preg_split('/\s+/', $existingRel, -1, PREG_SPLIT_NO_EMPTY);
                        $tokens = array_map('strtolower', $tokens);
                        if (!in_array('noopener', $tokens, true)) {
                            $tokens[] = 'noopener';
                        }
                        if (!in_array('noreferrer', $tokens, true)) {
                            $tokens[] = 'noreferrer';
                        }
                        $node->setAttribute('rel', implode(' ', array_unique($tokens)));
                    }
                }
            }
        }

        $body = $document->getElementsByTagName('div')->item(0);
        if (!$body) {
            return self::sanitize($html);
        }

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    /**
     * @param string $text
     * @param string|null $key
     * @return string
     */
    public static function translate($text, $key = null)
    {
        if (!is_string($text)) {
            return $key !== null ? (string)$key : '';
        }

        return $text;
    }

    /**
     * @return string
     */
    public static function activeCurrency()
    {
        return 'TRY';
    }

    /**
     * @param float $amount
     * @param string $baseCurrency
     * @return string
     */
    public static function formatCurrency($amount, $baseCurrency = 'USD')
    {
        $activeCurrency = self::activeCurrency();

        if ($activeCurrency !== $baseCurrency) {
            $amount = Currency::convert((float)$amount, $baseCurrency, $activeCurrency);
        }

        return Currency::format((float)$amount, $activeCurrency);
    }

    /**
     * @return float
     */
    public static function commissionRate()
    {
        $stored = Settings::get('pricing_commission_rate');
        $rate = $stored !== null ? (float)$stored : 0.0;

        if ($rate < 0) {
            $rate = 0.0;
        }

        return $rate;
    }

    /**
     * @param float $costTry
     * @return float
     */
    public static function priceFromCostTry($costTry)
    {
        $cost = max(0.0, (float)$costTry);
        $usd = Currency::convert($cost, 'TRY', 'USD');
        $rate = self::commissionRate();

        if ($rate > 0) {
            $usd += $usd * ($rate / 100);
        }

        return round($usd, 2);
    }

    /**
     * @param float $salePriceUsd
     * @return float
     */
    public static function costTryFromSalePrice($salePriceUsd)
    {
        $price = max(0.0, (float)$salePriceUsd);
        $rate = self::commissionRate();

        if ($rate > 0) {
            $price = $price / (1 + ($rate / 100));
        }

        $costTry = Currency::convert($price, 'USD', 'TRY');

        return round($costTry, 2);
    }

    /**
     * @return string
     */
    public static function currencySymbol()
    {
        return Currency::symbol(self::activeCurrency());
    }

    /**
     * @param string|null $value
     * @param int $limit
     * @param string $suffix
     * @return string
     */
    public static function truncate($value = null, $limit = 100, $suffix = '…')
    {
        $value = (string)$value;

        if ($limit <= 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= $limit) {
                return $value;
            }

            return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $suffix;
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, $limit)) . $suffix;
    }

    /**
     * Create a URL friendly slug from a string.
     *
     * @param string $value
     * @param string $separator
     * @return string
     */
    public static function slugify($value, $separator = '-')
    {
        $value = (string)$value;
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $map = array(
            'Ç' => 'C', 'ç' => 'c',
            'Ğ' => 'G', 'ğ' => 'g',
            'İ' => 'I', 'I' => 'I', 'ı' => 'i',
            'Ö' => 'O', 'ö' => 'o',
            'Ş' => 'S', 'ş' => 's',
            'Ü' => 'U', 'ü' => 'u',
        );

        $value = strtr($value, $map);

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($transliterated !== false && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/iu', $separator, $value);
        $value = trim($value, $separator);

        return $value;
    }

    /**
     * Render an icon, supporting both legacy font classes and Iconify identifiers.
     *
     * When the supplied icon value begins with "iconify:" the remainder will be
     * used as the Iconify data-icon attribute. Otherwise the value is treated as
     * a raw class name for an <i> element.
     *
     * @param string|null $icon
     * @param array $attributes
     * @return string
     */
    public static function iconHtml($icon, array $attributes = array())
    {
        $icon = trim((string)$icon);

        if ($icon === '') {
            return '';
        }

        $attributes = array_change_key_case($attributes, CASE_LOWER);
        $extraClass = '';

        if (isset($attributes['class']) && is_string($attributes['class'])) {
            $extraClass = trim($attributes['class']);
            unset($attributes['class']);
        }

        $buildAttributes = static function (array $attrs) {
            if (!$attrs) {
                return '';
            }

            $fragments = array();
            foreach ($attrs as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $fragments[] = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') .
                    '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
            }

            return $fragments ? ' ' . implode(' ', $fragments) : '';
        };

        if (strpos($icon, 'iconify:') === 0) {
            $iconName = substr($icon, 8);
            $iconName = trim($iconName);

            if ($iconName === '') {
                return '';
            }

            $classes = trim('iconify ' . $extraClass);
            $iconAttributes = $attributes;

            if ($classes !== '') {
                $iconAttributes['class'] = $classes;
            }

            $iconAttributes['data-icon'] = $iconName;

            return '<span' . $buildAttributes($iconAttributes) . '></span>';
        }

        $classes = trim($icon . ' ' . $extraClass);
        $iconAttributes = $attributes;
        if ($classes !== '') {
            $iconAttributes['class'] = $classes;
        }

        return '<i' . $buildAttributes($iconAttributes) . '></i>';
    }

    /**
     * @param string $title
     * @return void
     */
    public static function setPageTitle($title)
    {
        $title = is_string($title) ? trim($title) : '';
        if ($title === '') {
            if (isset($GLOBALS['pageTitle'])) {
                unset($GLOBALS['pageTitle']);
            }
            return;
        }

        $GLOBALS['pageTitle'] = $title;
    }

    /**
     * @return string
     */
    public static function defaultProductDescription()
    {
        return 'Detaylı bilgi için lütfen destek ekibimizle iletişime geçin.';
    }

    /**
     * Allow controllers to override the canonical URL for the active response.
     *
     * @param string|null $url
     * @return void
     */
    public static function setCanonicalUrl(?string $url): void
    {
        if (!is_string($url)) {
            return;
        }

        $url = trim($url);
        if ($url === '') {
            return;
        }

        if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
            $url = self::absoluteUrl($url);
        }

        $GLOBALS['pageCanonicalUrl'] = $url;
    }

    /**
     * Build an absolute URL for a relative path.
     *
     * @param string $path
     * @return string
     */
    public static function absoluteUrl($path)
    {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return self::canonicalUrl();
        }

        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            return $path;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');

        return $scheme . '://' . $host . $path;
    }

    /**
     * Build a URL by replacing query string parameters while optionally preserving
     * those from the current request.
     *
     * @param string $path
     * @param array<string,mixed> $parameters
     * @param array<string,mixed> $options
     * @return string
     */
    public static function replaceQueryParameters($path, array $parameters = array(), array $options = array())
    {
        $defaults = array(
            'preserve' => true,
            'remove' => array(),
            'absolute' => false,
            'source' => null,
        );

        $options = array_merge($defaults, $options);
        $source = $options['source'];
        if (!is_array($source)) {
            $source = $_GET;
        }

        $query = array();
        if ($options['preserve'] === true) {
            $query = is_array($source) ? $source : array();
        } elseif (is_array($options['preserve'])) {
            foreach ($options['preserve'] as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                if (is_array($source) && array_key_exists($key, $source)) {
                    $query[$key] = $source[$key];
                }
            }
        }

        $removeKeys = array();
        if (is_array($options['remove'])) {
            foreach ($options['remove'] as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $removeKeys[] = $key;
            }
        }

        foreach ($removeKeys as $key) {
            unset($query[$key]);
        }

        foreach ($parameters as $key => $value) {
            $normalizedKey = is_int($key) ? (string)$key : trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }

            if ($value === null || $value === '') {
                unset($query[$normalizedKey]);
                continue;
            }

            $query[$normalizedKey] = $value;
        }

        ksort($query);
        $queryString = $query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            $path = '/';
        }

        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            $url = $path . $queryString;
        } else {
            if ($path[0] !== '/') {
                $path = '/' . ltrim($path, '/');
            }
            $url = $path . $queryString;
            if (!empty($options['absolute'])) {
                $url = self::absoluteUrl($url);
            }
        }

        return $url;
    }

    /**
     * Generate a canonical URL for the current request.
     *
     * @param array $overrides
     * @return string
     */
    public static function canonicalUrl(array $overrides = array())
    {
        $scheme = isset($overrides['scheme']) ? strtolower((string)$overrides['scheme']) : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $scheme = $scheme === 'https' ? 'https' : 'http';

        $host = isset($overrides['host']) ? trim((string)$overrides['host']) : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        if (isset($overrides['path'])) {
            $path = trim((string)$overrides['path']);
        } else {
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
            $path = parse_url($requestUri, PHP_URL_PATH);
            $path = $path !== null ? $path : '/';
        }

        if ($path === '') {
            $path = '/';
        } elseif ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        $queryData = null;
        if (array_key_exists('query', $overrides)) {
            $queryData = $overrides['query'];
        } else {
            $queryData = $_GET;
        }

        $queryString = '';
        if ($queryData !== false) {
            if (is_string($queryData)) {
                parse_str($queryData, $queryData);
            }

            if (!is_array($queryData)) {
                $queryData = array();
            }

            $queryData = self::filterCanonicalQuery($queryData);

            if ($queryData) {
                ksort($queryData);
                $queryString = '?' . http_build_query($queryData, '', '&', PHP_QUERY_RFC3986);
            }
        }

        return $scheme . '://' . $host . $path . $queryString;
    }

    /**
     * @param array $query
     * @return array
     */
    private static function filterCanonicalQuery(array $query)
    {
        $blocked = array(
            'gclid',
            'fbclid',
            'msclkid',
            'yclid',
            'gbraid',
            'wbraid',
            'ref',
            'ref_id'
        );

        $filtered = array();
        foreach ($query as $key => $value) {
            $normalizedKey = is_int($key) ? (string)$key : trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }

            $lowerKey = strtolower($normalizedKey);

            if (strpos($lowerKey, 'utm_') === 0 || in_array($lowerKey, $blocked, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$normalizedKey] = $value;
        }

        return $filtered;
    }

    /**
     * @return string
     */
    public static function currentPath()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return $path ?: '/';
    }

    /**
     * @param string $pattern
     * @return bool
     */
    public static function isActive($pattern)
    {
        $current = self::currentPath();

        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $current);
        }

        if ($pattern === $current) {
            return true;
        }

        $escaped = preg_quote($pattern, '#');
        $escaped = str_replace(['\*', '\?'], ['.*', '.'], $escaped);

        return (bool)preg_match('#^' . $escaped . '$#', $current);
    }

    /**
     * Detect the most reliable client IP address.
     *
     * @return string|null
     */
    public static function ipAddress()
    {
        $candidates = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

        foreach ($candidates as $key) {
            if (!isset($_SERVER[$key]) || !$_SERVER[$key]) {
                continue;
            }

            $value = trim((string)$_SERVER[$key]);

            if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }

            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Generate a product detail URL for the given slug.
     *
     * @param string $slug
     * @param bool $absolute
     * @return string
     */
    public static function productUrl(string $slug, bool $absolute = false): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '#';
        }

        $path = '/product/' . rawurlencode($slug) . '/';

        return $absolute ? self::absoluteUrl($path) : $path;
    }

    /**
     * Generate a static page URL for the given slug.
     *
     * @param string $slug
     * @param bool $absolute
     * @return string
     */
    public static function pageUrl(string $slug, bool $absolute = false): string
    {
        $slug = self::slugify($slug);
        if ($slug === '') {
            return '#';
        }

        $path = '/page/' . rawurlencode($slug);

        return $absolute ? self::absoluteUrl($path) : $path;
    }

    /**
     * Generate a category URL for the given category path or data.
     *
     * @param mixed $category
     * @param bool $absolute
     * @return string
     */
    public static function categoryUrl($category, bool $absolute = false): string
    {
        $path = '';


        if (is_array($category)) {
            if (isset($category['path']) && $category['path'] !== '') {
                $path = (string)$category['path'];
            } elseif (isset($category['slug']) && $category['slug'] !== '') {
                $path = (string)$category['slug'];
            } elseif (isset($category['name']) && $category['name'] !== '') {
                $path = self::slugify((string)$category['name']);
            }
        } elseif (is_string($category) || is_numeric($category)) {
            $path = (string)$category;
        }

        $path = trim(str_replace('\\', '/', $path), '/');
        $segments = array();

        if ($path !== '') {
            foreach (explode('/', $path) as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }

                $segments[] = strtolower($segment);
            }
        }

        $urlPath = '/kategori';
        if ($segments) {
            $encoded = array_map('rawurlencode', $segments);
            $urlPath .= '/' . implode('/', $encoded);
        }

        return $absolute ? self::absoluteUrl($urlPath) : $urlPath;
    }

    /**
     * @param \DOMNode $node
     * @return void
     */
    private static function unwrapDomNode(\DOMNode $node): void
    {
        if (!$node->parentNode) {
            return;
        }

        $parent = $node->parentNode;
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}

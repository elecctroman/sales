<?php

namespace App;

class Router
{
    /** @var Router|null */
    private static $instance;

    /** @var array<int, array<string, mixed>> */
    private $routes = array();

    /** @var array<string, array<int, string>> */
    private $scriptMap = array();

    private function __construct()
    {
        $this->registerDefaultRoutes();
    }

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Attempt to match the provided path to a registered route.
     *
     * @param string $path
     * @param string $method
     * @return array<string, mixed>|null
     */
    public function match(string $path, string $method = 'GET')
    {
        $method = strtoupper($method);
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = array();
            if ($route['parameters']) {
                foreach ($route['parameters'] as $index => $name) {
                    if (isset($matches[$index + 1])) {
                        $params[$name] = $matches[$index + 1];
                    }
                }
            }

            if (isset($route['normalizer']) && is_callable($route['normalizer'])) {
                $params = call_user_func($route['normalizer'], $params, $path) ?: $params;
            }

            $pathBuilder = $route['builder'];
            $canonicalPath = is_callable($pathBuilder) ? call_user_func($pathBuilder, $params) : $route['template'];

            return array(
                'name' => $route['name'],
                'script' => $route['script'],
                'params' => $params,
                'path' => $canonicalPath,
                'methods' => $route['methods'],
            );
        }

        return null;
    }

    /**
     * Build a canonical path for a named route.
     *
     * @param string $name
     * @param array<string, mixed> $params
     * @return string|null
     */
    public function url(string $name, array $params = array())
    {
        foreach ($this->routes as $route) {
            if ($route['name'] !== $name) {
                continue;
            }

            $builder = $route['builder'];
            $path = is_callable($builder) ? call_user_func($builder, $params) : $route['template'];

            return $path;
        }

        return null;
    }

    /**
     * Determine the canonical route definition for a given script.
     *
     * @param string $script
     * @return array<string, mixed>|null
     */
    public function canonicalRouteForScript(string $script)
    {
        if (!isset($this->scriptMap[$script])) {
            return null;
        }

        $routeNames = $this->scriptMap[$script];
        foreach ($this->routes as $route) {
            if (in_array($route['name'], $routeNames, true)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function canonicalFromScript(string $script, array $params = array())
    {
        $route = $this->canonicalRouteForScript($script);
        if (!$route) {
            return null;
        }

        $path = is_callable($route['builder']) ? call_user_func($route['builder'], $params) : $route['template'];

        return array(
            'name' => $route['name'],
            'script' => $route['script'],
            'path' => $path,
            'params' => $params,
        );
    }

    private function registerDefaultRoutes(): void
    {
        $this->routes = array();
        $this->scriptMap = array();

        $this->register(
            'home',
            array('GET'),
            '#^/$#',
            'index.php',
            array(),
            '/'
        );

        $this->register(
            'catalog.index',
            array('GET'),
            '#^/urunler/?$#i',
            'catalog.php',
            array(),
            '/urunler/'
        );

        $this->register(
            'catalog.category',
            array('GET'),
            '#^/kategori/([^/]+)/?$#i',
            'catalog.php',
            array('category_slug'),
            function (array $params) {
                return '/kategori/' . rawurlencode($params['category_slug']) . '/';
            }
        );

        $this->register(
            'product.show',
            array('GET'),
            '#^/urun/([^/]+)/?$#i',
            'product.php',
            array('slug'),
            function (array $params) {
                return '/urun/' . rawurlencode($params['slug']) . '/';
            }
        );

        $this->register(
            'cart.view',
            array('GET', 'POST'),
            '#^/sepet/?$#i',
            'cart.php',
            array(),
            '/sepet/'
        );

        $this->register(
            'checkout.index',
            array('GET', 'POST'),
            '#^/odeme/?$#i',
            'checkout.php',
            array(),
            '/odeme/'
        );

        $this->register(
            'payment.success',
            array('GET', 'POST'),
            '#^/odeme/basarili/?$#i',
            'payment-success.php',
            array(),
            '/odeme/basarili/'
        );

        $this->register(
            'login',
            array('GET', 'POST'),
            '#^/giris/?$#i',
            'login.php',
            array(),
            '/giris/'
        );

        $this->register(
            'register',
            array('GET', 'POST'),
            '#^/kayit/?$#i',
            'register.php',
            array(),
            '/kayit/'
        );

        $this->register(
            'logout',
            array('GET'),
            '#^/cikis/?$#i',
            'logout.php',
            array(),
            '/cikis/'
        );

        $this->register(
            'account.dashboard',
            array('GET'),
            '#^/hesabim/?$#i',
            'account.php',
            array(),
            '/hesabim/'
        );

        $this->register(
            'support.index',
            array('GET', 'POST'),
            '#^/destek/?$#i',
            'support.php',
            array(),
            '/destek/'
        );

        $this->register(
            'contact',
            array('GET', 'POST'),
            '#^/iletisim/?$#i',
            'contact.php',
            array(),
            '/iletisim/'
        );

        $this->register(
            'blog.index',
            array('GET'),
            '#^/blog/?$#i',
            'blog.php',
            array(),
            '/blog/'
        );

        $this->register(
            'blog.category',
            array('GET'),
            '#^/blog/kategori/([^/]+)/?$#i',
            'blog.php',
            array('category_slug'),
            function (array $params) {
                return '/blog/kategori/' . rawurlencode($params['category_slug']) . '/';
            }
        );

        $this->register(
            'blog.tag',
            array('GET'),
            '#^/blog/etiket/([^/]+)/?$#i',
            'blog.php',
            array('tag_slug'),
            function (array $params) {
                return '/blog/etiket/' . rawurlencode($params['tag_slug']) . '/';
            }
        );

        $this->register(
            'blog.post',
            array('GET'),
            '#^/blog/([0-9]{4})/([0-9]{2})/([^/]+)/?$#',
            'blog.php',
            array('year', 'month', 'slug'),
            function (array $params) {
                $year = isset($params['year']) ? (int)$params['year'] : (int)date('Y');
                $month = isset($params['month']) ? (int)$params['month'] : (int)date('m');
                $slug = isset($params['slug']) ? $params['slug'] : '';
                $monthPadded = str_pad((string)$month, 2, '0', STR_PAD_LEFT);

                return '/blog/' . $year . '/' . $monthPadded . '/' . rawurlencode($slug) . '/';
            }
        );

        $this->register(
            'page.show',
            array('GET'),
            '#^/sayfa/([^/]+)/?$#i',
            'page.php',
            array('slug'),
            function (array $params) {
                return '/sayfa/' . rawurlencode($params['slug']) . '/';
            }
        );
    }

    /**
     * @param string $name
     * @param array<int, string> $methods
     * @param string $pattern
     * @param string $script
     * @param array<int, string> $parameters
     * @param callable|string $builder
     * @return void
     */
    private function register(string $name, array $methods, string $pattern, string $script, array $parameters, $builder): void
    {
        $route = array(
            'name' => $name,
            'methods' => $methods,
            'pattern' => $pattern,
            'script' => $script,
            'parameters' => $parameters,
            'builder' => $builder,
            'template' => is_string($builder) ? $builder : '',
        );

        $this->routes[] = $route;

        if (!isset($this->scriptMap[$script])) {
            $this->scriptMap[$script] = array();
        }
        $this->scriptMap[$script][] = $name;
    }
}

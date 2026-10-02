<?php
/**
 * DEV ONLY — router for PHP's built-in server, mirroring the OpenLiteSpeed
 * vhost rewrite rules. Never deployed to live.
 *
 *   php -S 127.0.0.1:8085 -t . dev-router.php
 */

declare(strict_types=1);

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__;

// Mirror of the production rewrite rules
if (preg_match('#^/estimate/([a-z0-9-]+)/?$#i', $uri, $m) && !is_file($root . $uri)) {
    $_GET['slug'] = strtolower($m[1]);
    $_SERVER['SCRIPT_NAME'] = '/estimate/index.php';
    require $root . '/estimate/index.php';
    return true;
}
$map = [
    '/robots.txt'  => '/robots.php',
    '/sitemap.xml' => '/sitemap.php',
    '/llms.txt'    => '/llms.php',
];
if (isset($map[$uri])) {
    require $root . $map[$uri];
    return true;
}

// Directory URLs → index.php
if ($uri !== '/' && is_dir($root . $uri)) {
    $index = rtrim($uri, '/') . '/index.php';
    if (is_file($root . $index)) {
        require $root . $index;
        return true;
    }
}

return false; // static files + direct .php hits: let the built-in server handle it

<?php
/**
 * Dynamic robots.txt (served at /robots.txt via rewrite). Public marketing
 * and estimator pages are crawlable; dashboard, super-admin, API, and
 * internals are not. AI crawlers get explicit groups with the same rules
 * so the marketing pages stay visible to answer engines.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$disallows = ['/dashboard/', '/super-admin/', '/api/', '/includes/', '/config/'];
$agents    = ['*', 'GPTBot', 'ClaudeBot', 'Google-Extended', 'PerplexityBot'];

foreach ($agents as $agent) {
    echo 'User-agent: ' . $agent . "\n";
    echo "Allow: /\n";
    foreach ($disallows as $path) {
        echo 'Disallow: ' . $path . "\n";
    }
    echo "\n";
}

echo 'Sitemap: ' . SITE_URL . '/sitemap.xml' . "\n";

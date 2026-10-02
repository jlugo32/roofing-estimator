<?php
/**
 * Dynamic XML sitemap (served at /sitemap.xml via rewrite). Lists the
 * public marketing pages plus one branded estimator URL per company that
 * is live (trial/active/past_due). Dashboard, super-admin, and API URLs
 * never appear here.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    SITE_URL . '/',
    SITE_URL . '/signup.php',
    SITE_URL . '/login.php',
];

$stmt = db()->prepare(
    "SELECT slug FROM companies
     WHERE subscription_status IN ('trial', 'active', 'past_due')
     ORDER BY slug"
);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
    $urls[] = SITE_URL . '/estimate/' . $slug;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo '  <url><loc>' . e($url) . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";

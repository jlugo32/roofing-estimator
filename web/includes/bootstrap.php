<?php
/**
 * Single entry include for every page and endpoint.
 * Loads config (defaults first, then secrets), DB, auth, CSRF helpers.
 *
 *   require __DIR__ . '/../includes/bootstrap.php';
 */

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

require_once PROJECT_ROOT . '/config/config.defaults.php';
require_once PROJECT_ROOT . '/config/config.php';

date_default_timezone_set('America/New_York');

// Never leak stack traces to visitors; log instead.
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', PROJECT_ROOT . '/storage/logs/php-error.log');

require_once PROJECT_ROOT . '/includes/db.php';
require_once PROJECT_ROOT . '/includes/csrf.php';
require_once PROJECT_ROOT . '/includes/auth.php';

/** HTML-escape shortcut. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Best-effort client IP (OpenLiteSpeed sits directly on the socket — REMOTE_ADDR is real). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Integer cents → "$1,234" (whole dollars — estimates are ranges, cents are noise). */
function money(int $cents): string
{
    return '$' . number_format((int) round($cents / 100));
}

/** Append a line to a log file in storage/logs/. */
function app_log(string $file, string $line): void
{
    $dir = PROJECT_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    file_put_contents(
        $dir . '/' . $file,
        '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

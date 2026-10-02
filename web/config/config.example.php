<?php
/**
 * Copy to config/config.php and fill in. config.php holds secrets: it is
 * git-ignored and must never be committed.
 */

declare(strict_types=1);

// ── Database ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'estimator_platform');
define('DB_USER', 'estimator_app');
define('DB_PASS', '');

// ── SMTP ──
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'no-reply@example.com');

// ── API token signing/pepper secret: generate with `php -r 'echo bin2hex(random_bytes(32));'` ──
define('JWT_SECRET', '');

// ── Stripe (leave empty to keep billing inactive) ──
define('STRIPE_SECRET_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');

// ── Platform owner notifications ──
define('SUPER_ADMIN_NOTIFY_EMAIL', 'admin@example.com');

// ── FCM push (Android new-lead alerts) ──
// Path to a Firebase service-account JSON key; '' disables push (silent no-op).
// Keep the key in storage/, never in the web root.
define('FCM_SERVICE_ACCOUNT_JSON', '');

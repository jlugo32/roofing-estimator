<?php
/**
 * Idempotent schema migration. CLI ONLY:
 *
 *   php migrate.php
 *
 * Creates all tables (CREATE TABLE IF NOT EXISTS) and bootstraps a super
 * admin on first run (username "admin", random password printed ONCE).
 * Override with env: SA_USER=me SA_PASS=secret ... migrate.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/includes/bootstrap.php';

$pdo = db();

$tables = [

'companies' => "
CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    logo_path VARCHAR(255) NULL,
    contact_email VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL DEFAULT '',
    service_area VARCHAR(255) NOT NULL DEFAULT '',
    license_number VARCHAR(100) NULL,
    subscription_status ENUM('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial',
    trial_ends_at DATE NULL,
    stripe_customer_id VARCHAR(100) NULL,
    stripe_subscription_id VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'company_users' => "
CREATE TABLE IF NOT EXISTS company_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
    api_token VARCHAR(64) NULL,
    api_token_expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_users_email (email),
    UNIQUE KEY uq_company_users_api_token (api_token),
    KEY idx_company_users_company (company_id),
    CONSTRAINT fk_company_users_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'super_admins' => "
CREATE TABLE IF NOT EXISTS super_admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_super_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'clients' => "
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL DEFAULT '',
    address VARCHAR(255) NULL,
    zip_code VARCHAR(10) NOT NULL DEFAULT '',
    state VARCHAR(2) NOT NULL DEFAULT '',
    status ENUM('new','contacted','quoted','won','lost') NOT NULL DEFAULT 'new',
    notes TEXT NULL,
    consent_tcpa TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clients_company (company_id),
    KEY idx_clients_company_email (company_id, email),
    KEY idx_clients_company_status (company_id, status),
    CONSTRAINT fk_clients_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'estimates' => "
CREATE TABLE IF NOT EXISTS estimates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    results_token CHAR(32) NOT NULL,
    home_sqft INT UNSIGNED NOT NULL,
    roof_sqft_estimate INT UNSIGNED NOT NULL,
    stories TINYINT UNSIGNED NOT NULL DEFAULT 1,
    roof_material_current VARCHAR(50) NOT NULL DEFAULT 'unknown',
    roof_material_desired VARCHAR(50) NOT NULL,
    roof_age_years TINYINT UNSIGNED NULL,
    roof_condition ENUM('good','fair','poor','leaking','unknown') NOT NULL DEFAULT 'unknown',
    pitch ENUM('low','medium','steep','unsure') NOT NULL DEFAULT 'unsure',
    tear_off_needed ENUM('yes','no','unsure') NOT NULL DEFAULT 'unsure',
    estimate_low_cents INT UNSIGNED NOT NULL,
    estimate_high_cents INT UNSIGNED NOT NULL,
    financing_monthly_cents INT UNSIGNED NULL,
    created_via ENUM('public_web','dashboard','mobile_app') NOT NULL DEFAULT 'public_web',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estimates_results_token (results_token),
    KEY idx_estimates_company (company_id),
    KEY idx_estimates_company_created (company_id, created_at),
    KEY idx_estimates_client (client_id),
    CONSTRAINT fk_estimates_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_estimates_client FOREIGN KEY (client_id)
        REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'company_pricing_settings' => "
CREATE TABLE IF NOT EXISTS company_pricing_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value VARCHAR(500) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pricing_company_key (company_id, setting_key),
    KEY idx_pricing_company (company_id),
    CONSTRAINT fk_pricing_company FOREIGN KEY (company_id)
        REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'platform_settings' => "
CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value VARCHAR(500) NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'rate_limits' => "
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_key VARCHAR(120) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_limits_bucket (bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// Phase 2 additive (Android app): FCM push-token registry. Does not alter any
// existing table or endpoint — the v1 contract stays frozen.
'device_tokens' => "
CREATE TABLE IF NOT EXISTS device_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_user_id INT UNSIGNED NOT NULL,
    fcm_token VARCHAR(255) NOT NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'android',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_tokens_token (fcm_token),
    KEY idx_device_tokens_user (company_user_id),
    CONSTRAINT fk_device_tokens_user FOREIGN KEY (company_user_id)
        REFERENCES company_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'password_resets' => "
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_password_resets_email (email),
    KEY idx_password_resets_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

foreach ($tables as $name => $sql) {
    $pdo->exec($sql);
    echo "ok  {$name}\n";
}

// ── Bootstrap super admin on first run ──
$count = (int) $pdo->query('SELECT COUNT(*) FROM super_admins')->fetchColumn();
if ($count === 0) {
    $username = getenv('SA_USER') ?: 'admin';
    $password = getenv('SA_PASS') ?: bin2hex(random_bytes(9));
    $stmt = $pdo->prepare('INSERT INTO super_admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    echo "\nSuper admin created — SAVE THIS, it is shown once:\n";
    echo "  username: {$username}\n  password: {$password}\n";
} else {
    echo "super_admins: {$count} existing, skipped bootstrap\n";
}

echo "\nMigration complete.\n";

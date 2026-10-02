<?php
/**
 * Authentication + TENANT ISOLATION boundary.
 *
 * THE RULE: every query touching company data filters by the company_id
 * returned from require_company_auth() (web) or api_require_auth() (API).
 * Never trust a company_id arriving via GET/POST/JSON — it is always
 * derived server-side from the session or the Bearer token.
 *
 * Web dashboard and super-admin use SEPARATE session cookies
 * (APPSESSID vs SA_SESSID) — they never share a session.
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────
// Sessions
// ─────────────────────────────────────────────────────────────

function _start_session(string $name): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function start_company_session(): void
{
    _start_session('APPSESSID');
}

function start_super_admin_session(): void
{
    _start_session('SA_SESSID');
}

// ─────────────────────────────────────────────────────────────
// Company dashboard auth (company_users)
// ─────────────────────────────────────────────────────────────

/**
 * Gatekeeper for every dashboard/ page. Call FIRST, before any output.
 * Returns ['company_id' => int, 'user' => row, 'company' => row].
 * Redirects to login when unauthenticated; blocks suspended/cancelled companies.
 */
function require_company_auth(): array
{
    start_company_session();

    $userId = $_SESSION['company_user_id'] ?? null;
    if (!$userId) {
        redirect('/login.php');
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.company_id, u.name, u.email, u.role, u.api_token_expires_at,
                (u.api_token IS NOT NULL) AS has_api_token
         FROM company_users u WHERE u.id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        redirect('/login.php');
    }

    $company = get_company((int) $user['company_id']);
    if (!$company) {
        session_destroy();
        redirect('/login.php');
    }

    if (in_array($company['subscription_status'], ['suspended', 'cancelled'], true)) {
        http_response_code(403);
        $reason = $company['subscription_status'] === 'suspended'
            ? 'This account has been suspended. Contact support to restore access.'
            : 'This account has been cancelled.';
        exit('<!doctype html><html><head><meta charset="utf-8"><title>Account unavailable</title></head>'
            . '<body style="font-family:sans-serif;max-width:34rem;margin:4rem auto;text-align:center">'
            . '<h1>Account unavailable</h1><p>' . htmlspecialchars($reason, ENT_QUOTES) . '</p></body></html>');
    }

    return [
        'company_id' => (int) $user['company_id'],
        'user'       => $user,
        'company'    => $company,
    ];
}

/** Verify credentials, harden the session, store identity. True on success. */
function login_company_user(string $email, string $password): bool
{
    start_company_session();
    $stmt = db()->prepare('SELECT id, password_hash FROM company_users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['company_user_id'] = (int) $row['id'];
    unset($_SESSION['csrf_token']); // fresh token per authenticated session
    return true;
}

function get_company(int $companyId): ?array
{
    $stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    return $stmt->fetch() ?: null;
}

function get_company_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM companies WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

// ─────────────────────────────────────────────────────────────
// Company registration (signup.php calls this)
// ─────────────────────────────────────────────────────────────

/** Derive a unique URL slug from the company name. */
function unique_company_slug(string $companyName): string
{
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $companyName), '-'));
    $base = substr($base, 0, 80) ?: 'company';
    $slug = $base;
    $n = 1;
    $stmt = db()->prepare('SELECT 1 FROM companies WHERE slug = ?');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . (++$n);
    }
}

/**
 * Create company + owner user + seeded pricing settings in one transaction.
 * Returns ['company_id' => int, 'user_id' => int, 'slug' => string].
 * Throws on duplicate email (caller shows friendly error).
 */
function register_company(
    string $companyName,
    string $contactEmail,
    string $contactPhone,
    string $ownerName,
    string $ownerEmail,
    string $password
): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slug = unique_company_slug($companyName);

        $stmt = $pdo->prepare(
            'INSERT INTO companies (slug, name, contact_email, contact_phone, subscription_status, trial_ends_at)
             VALUES (?, ?, ?, ?, "trial", DATE_ADD(CURDATE(), INTERVAL ? DAY))'
        );
        $stmt->execute([$slug, $companyName, $contactEmail, $contactPhone, TRIAL_LENGTH_DAYS]);
        $companyId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO company_users (company_id, name, email, password_hash, role)
             VALUES (?, ?, ?, ?, "owner")'
        );
        $stmt->execute([$companyId, $ownerName, $ownerEmail, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $pdo->lastInsertId();

        seed_company_pricing($companyId);

        $pdo->commit();
        return ['company_id' => $companyId, 'user_id' => $userId, 'slug' => $slug];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Insert default pricing rows for a new company (idempotent per key). */
function seed_company_pricing(int $companyId): void
{
    $stmt = db()->prepare(
        'INSERT INTO company_pricing_settings (company_id, setting_key, setting_value)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = setting_value'
    );
    foreach (default_pricing_seed() as $key => $value) {
        $stmt->execute([$companyId, $key, $value]);
    }
}

// ─────────────────────────────────────────────────────────────
// Super admin auth (separate table, separate session cookie)
// ─────────────────────────────────────────────────────────────

/** Gatekeeper for every super-admin/ page (except its login). Returns admin row. */
function require_super_admin(): array
{
    start_super_admin_session();
    $adminId = $_SESSION['super_admin_id'] ?? null;
    if (!$adminId) {
        redirect('/super-admin/login.php');
    }
    $stmt = db()->prepare('SELECT id, username FROM super_admins WHERE id = ?');
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    if (!$admin) {
        session_destroy();
        redirect('/super-admin/login.php');
    }
    return $admin;
}

function login_super_admin(string $username, string $password): bool
{
    start_super_admin_session();
    $stmt = db()->prepare('SELECT id, password_hash FROM super_admins WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['super_admin_id'] = (int) $row['id'];
    unset($_SESSION['csrf_token']);
    return true;
}

// ─────────────────────────────────────────────────────────────
// REST API auth (Bearer token → company_id, for Phase 2 mobile app)
// ─────────────────────────────────────────────────────────────

/** Emit a JSON response and stop. Success shape: {success, data}; error: {success, error}. */
function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status): never
{
    json_out(['success' => false, 'error' => $message], $status);
}

/** Read the Bearer token from the Authorization header. */
function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
        $header = $headers['authorization'] ?? '';
    }
    if (preg_match('/^Bearer\s+([A-Za-z0-9]{64})$/', trim($header), $m)) {
        return $m[1];
    }
    return null;
}

/** Tokens are stored as keyed SHA-256 hashes — a DB leak alone can't replay them. */
function api_token_hash(string $plaintext): string
{
    return hash_hmac('sha256', $plaintext, JWT_SECRET);
}

/**
 * Gatekeeper for every /api/v1/ endpoint. Validates the Bearer token,
 * checks expiry + company status, and returns
 * ['company_id' => int, 'user' => row, 'company' => row].
 * The client NEVER sends company_id — it is derived from the token here,
 * so a stolen token cannot reach another tenant by tampering with params.
 */
function api_require_auth(): array
{
    $token = bearer_token();
    if (!$token) {
        json_error('Missing or malformed Authorization: Bearer token.', 401);
    }

    $stmt = db()->prepare(
        'SELECT id, company_id, name, email, role, api_token_expires_at
         FROM company_users WHERE api_token = ?'
    );
    $stmt->execute([api_token_hash($token)]);
    $user = $stmt->fetch();
    if (!$user) {
        json_error('Invalid API token.', 401);
    }
    if (!$user['api_token_expires_at'] || strtotime($user['api_token_expires_at']) < time()) {
        json_error('API token expired. Call /api/v1/auth/refresh.php or log in again.', 401);
    }

    $company = get_company((int) $user['company_id']);
    if (!$company || in_array($company['subscription_status'], ['suspended', 'cancelled'], true)) {
        json_error('Account is suspended or cancelled.', 403);
    }

    return ['company_id' => (int) $user['company_id'], 'user' => $user, 'company' => $company];
}

/**
 * Mint a fresh API token for a user (login, refresh, or dashboard "generate key").
 * Returns the PLAINTEXT token — shown/returned exactly once, only the hash is stored.
 */
function issue_api_token(int $userId): array
{
    $plaintext = bin2hex(random_bytes(32)); // 64 hex chars
    $expiresAt = date('Y-m-d H:i:s', time() + API_TOKEN_TTL_DAYS * 86400);
    $stmt = db()->prepare('UPDATE company_users SET api_token = ?, api_token_expires_at = ? WHERE id = ?');
    $stmt->execute([api_token_hash($plaintext), $expiresAt, $userId]);
    return ['token' => $plaintext, 'expires_at' => $expiresAt];
}

function revoke_api_token(int $userId): void
{
    $stmt = db()->prepare('UPDATE company_users SET api_token = NULL, api_token_expires_at = NULL WHERE id = ?');
    $stmt->execute([$userId]);
}

// ─────────────────────────────────────────────────────────────
// Rate limiting (DB-backed fixed window, keyed bucket + IP)
// ─────────────────────────────────────────────────────────────

/**
 * Returns true if the request is ALLOWED, false when over the limit.
 * Used on /api/public/estimate.php, both login endpoints.
 */
function rate_limit(string $bucket, int $max, int $windowSeconds): bool
{
    $key = $bucket . ':' . client_ip();
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, hits, window_start FROM rate_limits WHERE bucket_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    $now = time();
    if (!$row || $now - strtotime($row['window_start']) >= $windowSeconds) {
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (bucket_key, hits, window_start) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()'
        );
        $stmt->execute([$key]);
        return true;
    }

    if ((int) $row['hits'] >= $max) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?');
    $stmt->execute([$row['id']]);
    return true;
}

<?php
/**
 * POST /api/v1/auth/login.php — exchange credentials for an API token. NO auth.
 *
 * POST JSON: { email, password }
 *
 * Success 200: { success: true, data: {
 *   token, expires_at,
 *   user:    { id, name, email, role },
 *   company: { id, name, slug }
 * } }
 *
 * The token is shown exactly ONCE — only its hash is stored. Send it on every
 * subsequent request as "Authorization: Bearer <token>". It is valid for
 * API_TOKEN_TTL_DAYS days; refresh via /api/v1/auth/refresh.php before expiry.
 *
 * Errors: 405 non-POST, 429 rate limited (RATE_LIMIT_API_LOGIN_* per IP,
 * checked BEFORE credential verification), 422 missing fields,
 * 401 invalid credentials (identical for unknown email — no user enumeration),
 * 403 company suspended/cancelled.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('POST only.', 405);
}

if (!rate_limit('api_login', RATE_LIMIT_API_LOGIN_MAX, RATE_LIMIT_API_LOGIN_WINDOW)) {
    json_error('Too many login attempts. Please try again later.', 429);
}

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '', true);
if (!is_array($in)) {
    $in = $_POST; // form-encoded fallback
}

$email    = trim((string) ($in['email'] ?? ''));
$password = (string) ($in['password'] ?? '');

if ($email === '' || $password === '') {
    json_error('Email and password are required.', 422);
}

$stmt = db()->prepare(
    'SELECT id, company_id, name, email, role, password_hash FROM company_users WHERE email = ?'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid credentials.', 401);
}

$company = get_company((int) $user['company_id']);
if (!$company || in_array($company['subscription_status'], ['suspended', 'cancelled'], true)) {
    json_error('Account is suspended or cancelled.', 403);
}

$issued = issue_api_token((int) $user['id']);

json_out(['success' => true, 'data' => [
    'token'      => $issued['token'],
    'expires_at' => $issued['expires_at'],
    'user'       => [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ],
    'company'    => [
        'id'   => (int) $company['id'],
        'name' => $company['name'],
        'slug' => $company['slug'],
    ],
]]);

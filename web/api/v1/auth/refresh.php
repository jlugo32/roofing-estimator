<?php
/**
 * POST /api/v1/auth/refresh.php — rotate a STILL-VALID API token.
 *
 * Auth: Authorization: Bearer <token> (the current, unexpired token).
 * Body: none.
 *
 * Success 200: { success: true, data: { token, expires_at } }
 *
 * Rotation invalidates the old token immediately (one live token per user).
 * Refresh BEFORE expiry — an expired token cannot refresh and requires a
 * full re-login via /api/v1/auth/login.php.
 *
 * Errors: 405 non-POST, 401 missing/invalid/expired token,
 * 403 company suspended/cancelled.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('POST only.', 405);
}

$auth = api_require_auth();

$issued = issue_api_token((int) $auth['user']['id']);

json_out(['success' => true, 'data' => [
    'token'      => $issued['token'],
    'expires_at' => $issued['expires_at'],
]]);

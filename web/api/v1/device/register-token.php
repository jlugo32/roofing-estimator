<?php
/**
 * POST /api/v1/device/register-token.php — Phase 2 ADDITIVE endpoint (Android).
 * Registers/refreshes an FCM device token for the authenticated company user so
 * the company receives new-lead push notifications.
 *
 * Auth: Bearer. Body JSON: { fcm_token: string, platform?: "android" }
 * Response: {"success":true,"data":{"registered":true}}
 *
 * The rest of the v1 contract remains FROZEN — this endpoint only adds.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('POST only.', 405);
}

$auth = api_require_auth();

$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) {
    $in = $_POST;
}

$token = trim((string) ($in['fcm_token'] ?? ''));
if (strlen($token) < 20 || strlen($token) > 255 || !preg_match('/^[A-Za-z0-9_:.\-]+$/', $token)) {
    json_error('A valid fcm_token is required.', 422);
}
$platform = in_array($in['platform'] ?? '', ['android', 'ios'], true) ? $in['platform'] : 'android';

// A token identifies one device; re-registering moves it to the current user.
$stmt = db()->prepare(
    'INSERT INTO device_tokens (company_user_id, fcm_token, platform)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE company_user_id = VALUES(company_user_id), platform = VALUES(platform)'
);
$stmt->execute([(int) $auth['user']['id'], $token, $platform]);

json_out(['success' => true, 'data' => ['registered' => true]]);

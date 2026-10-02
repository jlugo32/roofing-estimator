<?php
/**
 * FCM push notifications (Phase 2 addition) — new-lead alerts to the Android app.
 *
 * Uses the FCM HTTP v1 API with a Google service-account JSON key:
 *   1. Create a Firebase project + service account key (see docs/SETUP.md "FCM setup")
 *   2. Save the key to storage/fcm-service-account.json (deploy-excluded, vhost-denied)
 *   3. define('FCM_SERVICE_ACCOUNT_JSON', PROJECT_ROOT.'/storage/fcm-service-account.json')
 *      in config/config.php
 *
 * Unconfigured → silent no-op (logged once per call to push.log). Sending is
 * always best-effort: a push failure must never affect lead capture.
 */

declare(strict_types=1);

/** OAuth2 access token via service-account JWT (RS256), cached in storage/. */
function _fcm_access_token(array $sa): ?string
{
    $cacheFile = PROJECT_ROOT . '/storage/fcm-access-token.json';
    if (is_file($cacheFile)) {
        $cache = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cache) && ($cache['expires_at'] ?? 0) > time() + 60) {
            return $cache['token'];
        }
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $b64 = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $signingInput = $b64(json_encode($header)) . '.' . $b64(json_encode($claims));
    if (!openssl_sign($signingInput, $signature, $sa['private_key'], 'sha256WithRSAEncryption')) {
        app_log('push.log', 'FCM: JWT signing failed');
        return null;
    }
    $jwt = $signingInput . '.' . $b64($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $resp, true);
    if (empty($data['access_token'])) {
        app_log('push.log', 'FCM: token exchange failed: ' . substr((string) $resp, 0, 300));
        return null;
    }

    file_put_contents($cacheFile, json_encode([
        'token'      => $data['access_token'],
        'expires_at' => $now + (int) ($data['expires_in'] ?? 3600),
    ]), LOCK_EX);
    return $data['access_token'];
}

/**
 * Push a new-lead alert to every registered device of the company's users.
 * Called from the public estimator flow after a lead is stored. Best-effort.
 */
function send_new_lead_push(int $companyId, array $client, array $estimate): void
{
    if (!defined('FCM_SERVICE_ACCOUNT_JSON') || FCM_SERVICE_ACCOUNT_JSON === '' || !is_file(FCM_SERVICE_ACCOUNT_JSON)) {
        app_log('push.log', 'skipped (FCM not configured) company_id=' . $companyId);
        return;
    }
    $sa = json_decode((string) file_get_contents(FCM_SERVICE_ACCOUNT_JSON), true);
    if (!is_array($sa) || empty($sa['project_id']) || empty($sa['private_key']) || empty($sa['client_email'])) {
        app_log('push.log', 'FCM: service account JSON invalid');
        return;
    }

    $stmt = db()->prepare(
        'SELECT dt.id, dt.fcm_token FROM device_tokens dt
         JOIN company_users u ON u.id = dt.company_user_id
         WHERE u.company_id = ?'
    );
    $stmt->execute([$companyId]);
    $devices = $stmt->fetchAll();
    if (!$devices) {
        return;
    }

    $accessToken = _fcm_access_token($sa);
    if (!$accessToken) {
        return;
    }

    $title = 'New lead: ' . $client['name'];
    $body  = money((int) $estimate['estimate_low_cents']) . ' – ' . money((int) $estimate['estimate_high_cents'])
        . ' · ' . $client['zip_code'] . ', ' . $client['state'];
    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send';

    foreach ($devices as $device) {
        $payload = json_encode(['message' => [
            'token'        => $device['fcm_token'],
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => ['title' => $title, 'body' => $body, 'type' => 'new_lead'],
            'android'      => ['priority' => 'HIGH'],
        ]]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code === 404 || str_contains((string) $resp, 'UNREGISTERED')) {
            // Device uninstalled/expired — prune so we stop sending to it.
            $del = db()->prepare('DELETE FROM device_tokens WHERE id = ?');
            $del->execute([$device['id']]);
        } elseif ($code >= 300) {
            app_log('push.log', "FCM send failed http={$code}: " . substr((string) $resp, 0, 200));
        }
    }
}

<?php
/**
 * POST /api/v1/estimates/create.php — create an estimate from the field app.
 *
 * Auth: Authorization: Bearer <token>.
 * POST JSON — client, EITHER:
 *   client_id  int — an existing client of this tenant (else 404), OR
 *   client     { name, email, phone, zip_code, state, address?, consent_tcpa? }
 *              — validated like the public estimator; consent_tcpa 0/1
 *              defaults to 0; find-or-create by email (upsert).
 * Roof fields (top level):
 *   home_sqft              int 200–20000 (required)
 *   stories                int 1–3
 *   roof_material_current  string
 *   roof_material_desired  key of ROOF_MATERIALS
 *   roof_age_years         int|null
 *   roof_condition         one of ROOF_CONDITIONS
 *   pitch                  one of ROOF_PITCHES
 *   tear_off               one of TEAR_OFF_OPTIONS
 * The state used for the calculation is the client's state (from the stored
 * row when client_id is given, from client.state otherwise).
 * Optional:
 *   send_email  bool (default false) — when true, emails the homeowner their
 *               estimate and the company a lead notification (best-effort;
 *               a mail failure never fails the request).
 *
 * Success 200: { success: true, data: {
 *   estimate_id, client_id, results_token, results_url,
 *   roof_sqft_estimate, estimate_low_cents, estimate_high_cents,
 *   financing_monthly_cents
 * } }
 *
 * Errors: 405 non-POST, 401/403 auth, 422 validation,
 * 404 client_id not found for this tenant, 500 save failure.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/estimator-engine.php';
require_once PROJECT_ROOT . '/includes/mailer.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('POST only.', 405);
}

$auth = api_require_auth();
$cid = $auth['company_id'];

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '', true);
if (!is_array($in)) {
    $in = $_POST; // form-encoded fallback
}

// ── Validate roof inputs ──
$homeSqft = (int) ($in['home_sqft'] ?? 0);
if ($homeSqft < 200 || $homeSqft > 20000) {
    json_error('Please enter a home size between 200 and 20,000 sq ft.', 422);
}

// ── Resolve the client: existing client_id OR inline client payload ──
$clientRow = null; // name/email/phone/zip_code/state — feeds the calc + emails

if (isset($in['client_id']) && $in['client_id'] !== '') {
    $clientId = (int) $in['client_id'];
    if ($clientId < 1) {
        json_error('A valid client_id is required.', 422);
    }
    $stmt = db()->prepare(
        'SELECT id, name, email, phone, zip_code, state FROM clients WHERE id = ? AND company_id = ?'
    );
    $stmt->execute([$clientId, $cid]);
    $clientRow = $stmt->fetch();
    if (!$clientRow) {
        json_error('Not found.', 404);
    }
    $state = strtoupper(trim((string) $clientRow['state']));
} elseif (isset($in['client']) && is_array($in['client'])) {
    $c     = $in['client'];
    $name  = trim((string) ($c['name'] ?? ''));
    $email = trim((string) ($c['email'] ?? ''));
    $phone = preg_replace('/[^0-9+() .-]/', '', (string) ($c['phone'] ?? ''));
    $addr  = trim((string) ($c['address'] ?? ''));
    $zip   = trim((string) ($c['zip_code'] ?? ''));
    $state = strtoupper(trim((string) ($c['state'] ?? '')));

    if ($name === '' || mb_strlen($name) > 100) {
        json_error('Please enter the client\'s name.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        json_error('Please enter a valid email address.', 422);
    }
    if (strlen(preg_replace('/\D/', '', $phone)) < 10) {
        json_error('Please enter a valid phone number.', 422);
    }
    if (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
        json_error('Please enter a valid ZIP code.', 422);
    }
    if (!preg_match('/^[A-Z]{2}$/', $state)) {
        json_error('Please select a valid state.', 422);
    }

    try {
        $clientId = upsert_client($cid, [
            'name'         => $name,
            'email'        => $email,
            'phone'        => substr($phone, 0, 20),
            'address'      => $addr !== '' ? substr($addr, 0, 255) : null,
            'zip_code'     => $zip,
            'state'        => $state,
            'consent_tcpa' => (int) !empty($c['consent_tcpa']),
        ]);
    } catch (Throwable $e) {
        app_log('php-error.log', 'api estimate client upsert failed: ' . $e->getMessage());
        json_error('Something went wrong saving the client. Please try again.', 500);
    }
    $clientRow = ['name' => $name, 'email' => $email, 'phone' => $phone, 'zip_code' => $zip, 'state' => $state];
} else {
    json_error('Provide either client_id or a client object.', 422);
}

$roofInput = [
    'home_sqft'             => $homeSqft,
    'stories'               => (int) ($in['stories'] ?? 1),
    'roof_material_current' => (string) ($in['roof_material_current'] ?? 'unknown'),
    'roof_material_desired' => (string) ($in['roof_material_desired'] ?? ''),
    'roof_age_years'        => $in['roof_age_years'] ?? null,
    'roof_condition'        => (string) ($in['roof_condition'] ?? 'unknown'),
    'pitch'                 => (string) ($in['pitch'] ?? 'unsure'),
    'tear_off'              => (string) ($in['tear_off'] ?? 'unsure'),
    'state'                 => $state,
];

try {
    $estimate = create_estimate($cid, $clientId, $roofInput, 'mobile_app');
} catch (Throwable $e) {
    app_log('php-error.log', 'api estimate create failed: ' . $e->getMessage());
    json_error('Something went wrong saving the estimate. Please try again.', 500);
}

// Emails are opt-in and best-effort — a mail failure must never lose the estimate.
if (filter_var($in['send_email'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    send_homeowner_estimate($auth['company'], $clientRow, $estimate);
    send_lead_notification($auth['company'], $clientRow, $estimate);
}

json_out(['success' => true, 'data' => [
    'estimate_id'             => $estimate['estimate_id'],
    'client_id'               => $clientId,
    'results_token'           => $estimate['results_token'],
    'results_url'             => SITE_URL . '/estimate/results.php?token=' . $estimate['results_token'],
    'roof_sqft_estimate'      => $estimate['roof_sqft_estimate'],
    'estimate_low_cents'      => $estimate['estimate_low_cents'],
    'estimate_high_cents'     => $estimate['estimate_high_cents'],
    'financing_monthly_cents' => $estimate['financing_monthly_cents'],
]]);

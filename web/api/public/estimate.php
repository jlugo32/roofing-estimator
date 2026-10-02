<?php
/**
 * PUBLIC estimator endpoint — the lead-gen entry point. NO auth.
 * Tenant is resolved from the company slug, never from a raw company_id.
 *
 * POST JSON:
 *   action=preview  → calculate a range from roof inputs (teaser step, no lead created)
 *   action=submit   → full payload incl. lead fields → creates client + estimate,
 *                     emails homeowner + company, returns results_token
 *
 * Defenses: IP rate limit, honeypot (HONEYPOT_FIELD, logged on trip), strict validation.
 */

declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/estimator-engine.php';
require_once PROJECT_ROOT . '/includes/mailer.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('POST only.', 405);
}

if (!rate_limit('public_estimate', RATE_LIMIT_PUBLIC_ESTIMATE_MAX, RATE_LIMIT_PUBLIC_ESTIMATE_WINDOW)) {
    json_error('Too many requests. Please try again later.', 429);
}

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '', true);
if (!is_array($in)) {
    $in = $_POST; // form-encoded fallback
}

// ── Honeypot: any value in the inert field = bot. Log and play dead. ──
if (!empty($in[HONEYPOT_FIELD])) {
    app_log('honeypot.log', 'TRIP ip=' . client_ip()
        . ' ua="' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200) . '"'
        . ' slug=' . substr((string) ($in['slug'] ?? ''), 0, 100));
    // Fake success so bots don't adapt.
    json_out(['success' => true, 'data' => ['status' => 'ok']]);
}

// ── Resolve tenant from slug ──
$slug = strtolower(trim((string) ($in['slug'] ?? '')));
if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug)) {
    json_error('Unknown estimator.', 404);
}
$company = get_company_by_slug($slug);
if (!$company || in_array($company['subscription_status'], ['suspended', 'cancelled'], true)) {
    json_error('This estimator is not available.', 404);
}
$companyId = (int) $company['id'];

// ── Validate roof inputs (both actions need these) ──
$homeSqft = (int) ($in['home_sqft'] ?? 0);
if ($homeSqft < 200 || $homeSqft > 20000) {
    json_error('Please enter a home size between 200 and 20,000 sq ft.', 422);
}
$state = strtoupper(trim((string) ($in['state'] ?? '')));
if (!preg_match('/^[A-Z]{2}$/', $state)) {
    json_error('Please select a valid state.', 422);
}
$zip = trim((string) ($in['zip_code'] ?? ''));
if (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
    json_error('Please enter a valid ZIP code.', 422);
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

$action = (string) ($in['action'] ?? 'submit');

if ($action === 'preview') {
    $calc = calculate_estimate($companyId, $roofInput);
    json_out(['success' => true, 'data' => [
        'roof_sqft_estimate'      => $calc['roof_sqft_estimate'],
        'estimate_low_cents'      => $calc['estimate_low_cents'],
        'estimate_high_cents'     => $calc['estimate_high_cents'],
        'financing_monthly_cents' => $calc['financing_monthly_cents'],
    ]]);
}

// ── action=submit: validate lead fields ──
$name  = trim((string) ($in['name'] ?? ''));
$email = trim((string) ($in['email'] ?? ''));
$phone = preg_replace('/[^0-9+() .-]/', '', (string) ($in['phone'] ?? ''));
$addr  = trim((string) ($in['address'] ?? ''));

if ($name === '' || mb_strlen($name) > 100) {
    json_error('Please enter your name.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    json_error('Please enter a valid email address.', 422);
}
if (strlen(preg_replace('/\D/', '', $phone)) < 10) {
    json_error('Please enter a valid phone number.', 422);
}
if (empty($in['consent_tcpa'])) {
    json_error('Please agree to be contacted so we can send your estimate.', 422);
}

try {
    $clientId = upsert_client($companyId, [
        'name'         => $name,
        'email'        => $email,
        'phone'        => substr($phone, 0, 20),
        'address'      => $addr !== '' ? substr($addr, 0, 255) : null,
        'zip_code'     => $zip,
        'state'        => $state,
        'consent_tcpa' => 1,
    ]);
    $estimate = create_estimate($companyId, $clientId, $roofInput, 'public_web');
} catch (Throwable $e) {
    app_log('php-error.log', 'public estimate submit failed: ' . $e->getMessage());
    json_error('Something went wrong saving your estimate. Please try again.', 500);
}

// Emails + push are best-effort — a notification failure must never lose the lead.
$clientRow = ['name' => $name, 'email' => $email, 'phone' => $phone, 'zip_code' => $zip, 'state' => $state];
send_homeowner_estimate($company, $clientRow, $estimate);
send_lead_notification($company, $clientRow, $estimate);
require_once PROJECT_ROOT . '/includes/push.php';
send_new_lead_push($companyId, $clientRow, $estimate); // Phase 2: Android new-lead alert

json_out(['success' => true, 'data' => [
    'results_token'           => $estimate['results_token'],
    'results_url'             => SITE_URL . '/estimate/results.php?token=' . $estimate['results_token'],
    'roof_sqft_estimate'      => $estimate['roof_sqft_estimate'],
    'estimate_low_cents'      => $estimate['estimate_low_cents'],
    'estimate_high_cents'     => $estimate['estimate_high_cents'],
    'financing_monthly_cents' => $estimate['financing_monthly_cents'],
]]);

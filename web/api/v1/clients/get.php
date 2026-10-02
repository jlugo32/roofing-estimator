<?php
/**
 * GET /api/v1/clients/get.php — one client with their estimate history.
 *
 * Auth: Authorization: Bearer <token>.
 * Query params:
 *   id  int ≥ 1 (required)
 *
 * Success 200: { success: true, data: {
 *   client: { id, name, email, phone, address, zip_code, state, status,
 *             notes, consent_tcpa, created_at },
 *   estimates: [ { id, created_at, created_via, home_sqft, roof_sqft_estimate,
 *                  stories, roof_material_desired, roof_condition, pitch,
 *                  tear_off_needed, estimate_low_cents, estimate_high_cents,
 *                  financing_monthly_cents, results_token } ]   // newest first
 * } }
 *
 * Errors: 405 non-GET, 401/403 auth, 422 missing/invalid id,
 * 404 client not found for this tenant (identical to nonexistent).
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_error('GET only.', 405);
}

$auth = api_require_auth();
$cid = $auth['company_id'];

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    json_error('A valid id is required.', 422);
}

$stmt = db()->prepare(
    'SELECT id, name, email, phone, address, zip_code, state, status, notes, consent_tcpa, created_at
     FROM clients WHERE id = ? AND company_id = ?'
);
$stmt->execute([$id, $cid]);
$client = $stmt->fetch();
if (!$client) {
    json_error('Not found.', 404);
}

$stmt = db()->prepare(
    'SELECT id, created_at, created_via, home_sqft, roof_sqft_estimate, stories,
            roof_material_desired, roof_condition, pitch, tear_off_needed,
            estimate_low_cents, estimate_high_cents, financing_monthly_cents, results_token
     FROM estimates
     WHERE client_id = ? AND company_id = ?
     ORDER BY created_at DESC'
);
$stmt->execute([$id, $cid]);

$estimates = [];
foreach ($stmt->fetchAll() as $row) {
    $estimates[] = [
        'id'                      => (int) $row['id'],
        'created_at'              => $row['created_at'],
        'created_via'             => $row['created_via'],
        'home_sqft'               => (int) $row['home_sqft'],
        'roof_sqft_estimate'      => (int) $row['roof_sqft_estimate'],
        'stories'                 => (int) $row['stories'],
        'roof_material_desired'   => $row['roof_material_desired'],
        'roof_condition'          => $row['roof_condition'],
        'pitch'                   => $row['pitch'],
        'tear_off_needed'         => $row['tear_off_needed'],
        'estimate_low_cents'      => (int) $row['estimate_low_cents'],
        'estimate_high_cents'     => (int) $row['estimate_high_cents'],
        'financing_monthly_cents' => (int) $row['financing_monthly_cents'],
        'results_token'           => $row['results_token'],
    ];
}

json_out(['success' => true, 'data' => [
    'client'    => [
        'id'           => (int) $client['id'],
        'name'         => $client['name'],
        'email'        => $client['email'],
        'phone'        => $client['phone'],
        'address'      => $client['address'],
        'zip_code'     => $client['zip_code'],
        'state'        => $client['state'],
        'status'       => $client['status'],
        'notes'        => $client['notes'],
        'consent_tcpa' => (int) $client['consent_tcpa'],
        'created_at'   => $client['created_at'],
    ],
    'estimates' => $estimates,
]]);

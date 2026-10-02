<?php
/**
 * GET /api/v1/estimates/list.php — paginated estimate list for the tenant.
 *
 * Auth: Authorization: Bearer <token>.
 * Query params:
 *   page                  int ≥ 1   (default 1)
 *   per_page              int 1–100 (default 20)
 *   client_id             optional int — only that client's estimates
 *                         (always tenant-scoped; another tenant's id → empty)
 *   created_via           optional, one of: public_web|dashboard|mobile_app
 *   roof_material_desired optional, key of ROOF_MATERIALS
 *   date_from / date_to   optional YYYY-MM-DD, inclusive on created_at's date
 *
 * Success 200: { success: true, data: {
 *   items: [ { id, client_id, client_name, created_at, created_via, home_sqft,
 *              roof_sqft_estimate, stories, roof_material_desired,
 *              roof_condition, pitch, tear_off_needed, estimate_low_cents,
 *              estimate_high_cents, financing_monthly_cents, results_token } ],
 *   pagination: { page, per_page, total, total_pages }
 * } }
 *
 * Errors: 405 non-GET, 401/403 auth, 422 invalid filter value.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/estimator-engine.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_error('GET only.', 405);
}

$auth = api_require_auth();
$cid = $auth['company_id'];

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 20);
$perPage = max(1, min(100, $perPage));

$where  = 'e.company_id = ?';
$params = [$cid];

if (isset($_GET['client_id']) && $_GET['client_id'] !== '') {
    $clientId = (int) $_GET['client_id'];
    if ($clientId < 1) {
        json_error('A valid client_id is required.', 422);
    }
    $where .= ' AND e.client_id = ?';
    $params[] = $clientId;
}

$createdVia = trim((string) ($_GET['created_via'] ?? ''));
if ($createdVia !== '') {
    if (!in_array($createdVia, ['public_web', 'dashboard', 'mobile_app'], true)) {
        json_error('Invalid created_via filter.', 422);
    }
    $where .= ' AND e.created_via = ?';
    $params[] = $createdVia;
}

$material = trim((string) ($_GET['roof_material_desired'] ?? ''));
if ($material !== '') {
    if (!array_key_exists($material, ROOF_MATERIALS)) {
        json_error('Invalid roof_material_desired filter.', 422);
    }
    $where .= ' AND e.roof_material_desired = ?';
    $params[] = $material;
}

foreach (['date_from' => '>=', 'date_to' => '<='] as $param => $op) {
    $value = trim((string) ($_GET[$param] ?? ''));
    if ($value === '') {
        continue;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        json_error('Invalid ' . $param . ' — use YYYY-MM-DD.', 422);
    }
    $where .= ' AND DATE(e.created_at) ' . $op . ' ?';
    $params[] = $value;
}

$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM estimates e WHERE ' . $where);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

$stmt = $pdo->prepare(
    'SELECT e.id, e.client_id, c.name AS client_name, e.created_at, e.created_via,
            e.home_sqft, e.roof_sqft_estimate, e.stories, e.roof_material_desired,
            e.roof_condition, e.pitch, e.tear_off_needed, e.estimate_low_cents,
            e.estimate_high_cents, e.financing_monthly_cents, e.results_token
     FROM estimates e
     JOIN clients c ON c.id = e.client_id
     WHERE ' . $where . '
     ORDER BY e.created_at DESC
     LIMIT ? OFFSET ?'
);
$i = 1;
foreach ($params as $value) {
    $stmt->bindValue($i++, $value);
}
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();

$items = [];
foreach ($stmt->fetchAll() as $row) {
    $items[] = [
        'id'                      => (int) $row['id'],
        'client_id'               => (int) $row['client_id'],
        'client_name'             => $row['client_name'],
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
    'items'      => $items,
    'pagination' => [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
    ],
]]);

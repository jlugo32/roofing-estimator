<?php
/**
 * GET /api/v1/clients/list.php — paginated client (lead) list for the tenant.
 *
 * Auth: Authorization: Bearer <token>.
 * Query params:
 *   page     int ≥ 1              (default 1)
 *   per_page int 1–100            (default 20)
 *   status   optional, one of: new|contacted|quoted|won|lost
 *   q        optional search — LIKE match on name/email/phone
 *
 * Success 200: { success: true, data: {
 *   items: [ { id, name, email, phone, address, zip_code, state, status,
 *              consent_tcpa, estimates_count, created_at } ],
 *   pagination: { page, per_page, total, total_pages }
 * } }
 *
 * Errors: 405 non-GET, 401/403 auth, 422 invalid status filter.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_error('GET only.', 405);
}

$auth = api_require_auth();
$cid = $auth['company_id'];

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 20);
$perPage = max(1, min(100, $perPage));

$where  = 'company_id = ?';
$params = [$cid];

$status = trim((string) ($_GET['status'] ?? ''));
if ($status !== '') {
    if (!in_array($status, ['new', 'contacted', 'quoted', 'won', 'lost'], true)) {
        json_error('Invalid status filter.', 422);
    }
    $where .= ' AND status = ?';
    $params[] = $status;
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE ' . $where);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

$stmt = $pdo->prepare(
    'SELECT id, name, email, phone, address, zip_code, state, status, consent_tcpa, created_at,
            (SELECT COUNT(*) FROM estimates e WHERE e.client_id = clients.id AND e.company_id = clients.company_id)
                AS estimates_count
     FROM clients
     WHERE ' . $where . '
     ORDER BY created_at DESC
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
        'id'              => (int) $row['id'],
        'name'            => $row['name'],
        'email'           => $row['email'],
        'phone'           => $row['phone'],
        'address'         => $row['address'],
        'zip_code'        => $row['zip_code'],
        'state'           => $row['state'],
        'status'          => $row['status'],
        'consent_tcpa'    => (int) $row['consent_tcpa'],
        'estimates_count' => (int) $row['estimates_count'],
        'created_at'      => $row['created_at'],
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

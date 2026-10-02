<?php
/**
 * POST /api/v1/clients/update.php — update a client's status and/or notes.
 *
 * Auth: Authorization: Bearer <token>.
 * POST JSON: { id, status?, notes? } — at least one of status/notes required.
 *   status  one of: new|contacted|quoted|won|lost
 *   notes   string ≤ 65000 chars, trimmed; '' allowed (clears the notes)
 *
 * Success 200: { success: true, data: {
 *   client: { id, name, email, phone, address, zip_code, state, status,
 *             notes, consent_tcpa, created_at }   // updated row
 * } }
 *
 * Errors: 405 non-POST, 401/403 auth, 422 missing id / no updatable field /
 * invalid status / notes too long, 404 client not found for this tenant.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';

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

$id = (int) ($in['id'] ?? 0);
if ($id < 1) {
    json_error('A valid id is required.', 422);
}

$sets   = [];
$params = [];

if (array_key_exists('status', $in)) {
    $status = (string) $in['status'];
    if (!in_array($status, ['new', 'contacted', 'quoted', 'won', 'lost'], true)) {
        json_error('Invalid status.', 422);
    }
    $sets[]   = 'status = ?';
    $params[] = $status;
}

if (array_key_exists('notes', $in)) {
    $notes = trim((string) $in['notes']);
    if (mb_strlen($notes) > 65000) {
        json_error('Notes must be 65,000 characters or fewer.', 422);
    }
    $sets[]   = 'notes = ?';
    $params[] = $notes;
}

if (!$sets) {
    json_error('Provide at least one of: status, notes.', 422);
}

$pdo = db();

// Existence check first — an update to another tenant's row must 404, not no-op.
$stmt = $pdo->prepare('SELECT id FROM clients WHERE id = ? AND company_id = ?');
$stmt->execute([$id, $cid]);
if (!$stmt->fetch()) {
    json_error('Not found.', 404);
}

$params[] = $id;
$params[] = $cid;
$stmt = $pdo->prepare(
    'UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = ? AND company_id = ?'
);
$stmt->execute($params);

$stmt = $pdo->prepare(
    'SELECT id, name, email, phone, address, zip_code, state, status, notes, consent_tcpa, created_at
     FROM clients WHERE id = ? AND company_id = ?'
);
$stmt->execute([$id, $cid]);
$client = $stmt->fetch();

json_out(['success' => true, 'data' => [
    'client' => [
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
]]);

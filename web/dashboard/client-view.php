<?php
/**
 * Single client detail — contact card, status + notes editing, estimate
 * history, delete. Record is fetched WHERE id AND company_id: an id that
 * belongs to another tenant renders a 404, never their data.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

require_once PROJECT_ROOT . '/includes/estimator-engine.php';

$pdo = db();

const CLIENT_STATUSES = ['new', 'contacted', 'quoted', 'won', 'lost'];

// ── Fetch the client, tenant-scoped. Wrong tenant / missing → 404. ──
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND company_id = ?');
$stmt->execute([$id, $cid]);
$client = $stmt->fetch();

if (!$client) {
    http_response_code(404);
    $active     = 'clients';
    $page_title = 'Client not found';
    require PROJECT_ROOT . '/includes/dashboard-header.php';
    echo '<div class="card text-center py-10">'
        . '<h1 class="text-xl font-bold mb-2">Client not found</h1>'
        . '<p class="text-sm text-slate-500 mb-4">This client does not exist or belongs to another account.</p>'
        . '<a href="/dashboard/clients.php" class="btn-secondary">Back to clients</a>'
        . '</div>';
    require PROJECT_ROOT . '/includes/dashboard-footer.php';
    exit;
}

// ── POST actions (client already verified as ours above) ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'status') {
        $newStatus = (string) ($_POST['status'] ?? '');
        if (in_array($newStatus, CLIENT_STATUSES, true)) {
            $stmt = $pdo->prepare('UPDATE clients SET status = ? WHERE id = ? AND company_id = ?');
            $stmt->execute([$newStatus, $id, $cid]);
        }
        redirect('/dashboard/client-view.php?id=' . $id . '&saved=1');
    }

    if ($action === 'notes') {
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $stmt = $pdo->prepare('UPDATE clients SET notes = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([$notes !== '' ? $notes : null, $id, $cid]);
        redirect('/dashboard/client-view.php?id=' . $id . '&saved=1');
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM clients WHERE id = ? AND company_id = ?');
        $stmt->execute([$id, $cid]); // estimates cascade via FK
        redirect('/dashboard/clients.php?deleted=1');
    }

    redirect('/dashboard/client-view.php?id=' . $id);
}

// ── Estimate history for this client (tenant-scoped) ──
$stmt = $pdo->prepare(
    'SELECT id, results_token, roof_material_desired, estimate_low_cents, estimate_high_cents,
            created_via, created_at
     FROM estimates
     WHERE client_id = ? AND company_id = ?
     ORDER BY created_at DESC'
);
$stmt->execute([$id, $cid]);
$estimates = $stmt->fetchAll();

$viaLabels = ['public_web' => 'Public web', 'dashboard' => 'Dashboard', 'mobile_app' => 'Mobile app'];

$active     = 'clients';
$page_title = $client['name'];
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<div class="mb-6">
  <a href="/dashboard/clients.php" class="text-sm text-slate-500 hover:underline">&larr; All clients</a>
  <div class="flex flex-wrap items-center gap-3 mt-1">
    <h1 class="text-2xl font-bold"><?= e($client['name']) ?></h1>
    <span class="badge badge-<?= e($client['status']) ?>"><?= e($client['status']) ?></span>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">Changes saved.</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
  <!-- Left column: contact, status, notes, delete -->
  <div class="space-y-4">
    <div class="card">
      <h2 class="font-semibold mb-3">Contact details</h2>
      <dl class="text-sm space-y-2">
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Email</dt>
          <dd class="text-right break-all"><a href="mailto:<?= e($client['email']) ?>" class="text-[var(--color-primary)] hover:underline"><?= e($client['email']) ?></a></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Phone</dt>
          <dd class="text-right"><a href="tel:<?= e($client['phone']) ?>" class="text-[var(--color-primary)] hover:underline"><?= e($client['phone']) ?></a></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Address</dt>
          <dd class="text-right"><?= $client['address'] !== null && $client['address'] !== '' ? e($client['address']) : '<span class="text-slate-400">—</span>' ?></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">ZIP / State</dt>
          <dd class="text-right"><?= e($client['zip_code']) ?>, <?= e($client['state']) ?></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">TCPA consent</dt>
          <dd class="text-right"><?= ((int) $client['consent_tcpa']) === 1 ? 'Yes' : 'No' ?></dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Added</dt>
          <dd class="text-right"><?= e(date('M j, Y g:ia', strtotime($client['created_at']))) ?></dd></div>
      </dl>
    </div>

    <div class="card">
      <h2 class="font-semibold mb-3">Pipeline status</h2>
      <form method="post" class="flex flex-col sm:flex-row gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <select name="status" class="field-input flex-1" aria-label="Client status">
          <?php foreach (CLIENT_STATUSES as $s): ?>
          <option value="<?= e($s) ?>" <?= $s === $client['status'] ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary whitespace-nowrap">Save status</button>
      </form>
    </div>

    <div class="card">
      <h2 class="font-semibold mb-3">Notes</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notes">
        <textarea name="notes" rows="5" class="field-input"
                  placeholder="Call outcomes, scheduling, roof details…"><?= e($client['notes'] ?? '') ?></textarea>
        <button type="submit" class="btn-primary mt-3">Save notes</button>
      </form>
    </div>

    <div class="card border border-red-100">
      <h2 class="font-semibold mb-1 text-[var(--color-danger)]">Danger zone</h2>
      <p class="text-sm text-slate-500 mb-3">Deletes this client and all of their estimates. This cannot be undone.</p>
      <form method="post" data-confirm="Delete this client and all their estimates? This cannot be undone.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn-outline !border-[var(--color-danger)] !text-[var(--color-danger)] hover:!bg-red-50">
          Delete client
        </button>
      </form>
    </div>
  </div>

  <!-- Right column: estimate history -->
  <div class="card">
    <h2 class="font-semibold mb-3">Estimate history</h2>
    <?php if (!$estimates): ?>
      <p class="text-sm text-slate-500 py-6 text-center">No estimates yet for this client.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Range</th><th>Material</th><th>Via</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($estimates as $est): ?>
            <tr>
              <td class="whitespace-nowrap text-slate-500"><?= e(date('M j, Y', strtotime($est['created_at']))) ?></td>
              <td class="whitespace-nowrap font-medium"><?= money((int) $est['estimate_low_cents']) ?> – <?= money((int) $est['estimate_high_cents']) ?></td>
              <td><?= e(ROOF_MATERIALS[$est['roof_material_desired']] ?? $est['roof_material_desired']) ?></td>
              <td><span class="badge bg-slate-200 text-slate-700"><?= e($viaLabels[$est['created_via']] ?? $est['created_via']) ?></span></td>
              <td>
                <a href="/estimate/results.php?token=<?= e($est['results_token']) ?>" target="_blank" rel="noopener"
                   class="text-[var(--color-primary)] font-medium hover:underline whitespace-nowrap">Results page ↗</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>

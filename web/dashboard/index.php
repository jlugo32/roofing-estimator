<?php
/**
 * Dashboard home — at-a-glance stats, estimator share link, recent activity.
 * Every query is tenant-scoped to the authenticated company.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

require_once PROJECT_ROOT . '/includes/estimator-engine.php';

$pdo = db();

// ── Stat queries (all filtered by company_id) ──
$stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE company_id = ? AND created_at >= NOW() - INTERVAL 7 DAY');
$stmt->execute([$cid]);
$newLeadsWeek = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE company_id = ?');
$stmt->execute([$cid]);
$totalClients = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM estimates WHERE company_id = ? AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
$stmt->execute([$cid]);
$estimatesMonth = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE company_id = ? AND status = 'won'");
$stmt->execute([$cid]);
$wonClients = (int) $stmt->fetchColumn();

$conversionRate = $totalClients > 0 ? round($wonClients / $totalClients * 100, 1) : 0.0;

$stmt = $pdo->prepare(
    'SELECT AVG((estimate_low_cents + estimate_high_cents) / 2) FROM estimates WHERE company_id = ?'
);
$stmt->execute([$cid]);
$avgRaw = $stmt->fetchColumn();
$avgEstimateCents = $avgRaw !== null ? (int) round((float) $avgRaw) : 0;

// ── Recent activity ──
$stmt = $pdo->prepare(
    'SELECT e.id, e.client_id, e.estimate_low_cents, e.estimate_high_cents, e.created_via, e.created_at,
            c.name AS client_name
     FROM estimates e
     JOIN clients c ON c.id = e.client_id AND c.company_id = e.company_id
     WHERE e.company_id = ?
     ORDER BY e.created_at DESC
     LIMIT 8'
);
$stmt->execute([$cid]);
$recentEstimates = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT id, name, email, status, created_at FROM clients
     WHERE company_id = ? ORDER BY created_at DESC LIMIT 5'
);
$stmt->execute([$cid]);
$recentClients = $stmt->fetchAll();

$viaLabels = ['public_web' => 'Public web', 'dashboard' => 'Dashboard', 'mobile_app' => 'Mobile app'];

$estimatorUrl = SITE_URL . '/estimate/' . $auth['company']['slug'];

$active     = 'home';
$page_title = 'Dashboard';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Welcome back, <?= e($auth['user']['name']) ?></h1>

<!-- Stat cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="card">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New leads this week</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($newLeadsWeek) ?></p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total clients</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($totalClients) ?></p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimates this month</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($estimatesMonth) ?></p>
  </div>
  <div class="card">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Conversion rate</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($conversionRate, 1) ?>%</p>
    <p class="text-xs text-slate-500 mt-1"><?= number_format($wonClients) ?> won of <?= number_format($totalClients) ?></p>
  </div>
</div>

<!-- Second row: avg estimate + estimator link -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
  <div class="card">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg estimate value</p>
    <p class="text-3xl font-bold mt-1"><?= $avgEstimateCents > 0 ? money($avgEstimateCents) : '—' ?></p>
    <p class="text-xs text-slate-500 mt-1">Midpoint average across all estimates</p>
  </div>
  <div class="card lg:col-span-2">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Your estimator link</p>
    <div class="flex flex-col sm:flex-row gap-2">
      <input id="estimator-link" type="text" readonly value="<?= e($estimatorUrl) ?>"
             class="field-input flex-1 bg-slate-50 font-mono text-sm" onclick="this.select()">
      <button type="button" class="btn-secondary whitespace-nowrap" data-copy="#estimator-link">Copy link</button>
    </div>
    <p class="field-help mt-2">
      Share this link anywhere — every homeowner who completes it becomes a lead here.
      <a href="<?= e($estimatorUrl) ?>" target="_blank" rel="noopener" class="underline font-medium">Open in new tab ↗</a>
    </p>
  </div>
</div>

<!-- Recent activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
  <div class="card">
    <h2 class="font-semibold mb-3">Latest estimates</h2>
    <?php if (!$recentEstimates): ?>
      <p class="text-sm text-slate-500 py-6 text-center">
        No estimates yet — share your estimator link to get your first lead.
      </p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Client</th><th>Range</th><th>Via</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentEstimates as $est): ?>
            <tr>
              <td>
                <a href="/dashboard/client-view.php?id=<?= (int) $est['client_id'] ?>"
                   class="font-medium text-[var(--color-primary)] hover:underline"><?= e($est['client_name']) ?></a>
              </td>
              <td class="whitespace-nowrap"><?= money((int) $est['estimate_low_cents']) ?> – <?= money((int) $est['estimate_high_cents']) ?></td>
              <td><span class="badge bg-slate-200 text-slate-700"><?= e($viaLabels[$est['created_via']] ?? $est['created_via']) ?></span></td>
              <td class="whitespace-nowrap text-slate-500"><?= e(date('M j, Y', strtotime($est['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-3">Newest clients</h2>
    <?php if (!$recentClients): ?>
      <p class="text-sm text-slate-500 py-6 text-center">
        No clients yet — your leads will appear here automatically.
      </p>
    <?php else: ?>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($recentClients as $cl): ?>
        <li class="py-2.5 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <a href="/dashboard/client-view.php?id=<?= (int) $cl['id'] ?>"
               class="font-medium text-[var(--color-primary)] hover:underline block truncate"><?= e($cl['name']) ?></a>
            <p class="text-xs text-slate-500 truncate"><?= e($cl['email']) ?></p>
          </div>
          <div class="text-right shrink-0">
            <span class="badge badge-<?= e($cl['status']) ?>"><?= e($cl['status']) ?></span>
            <p class="text-xs text-slate-400 mt-1"><?= e(date('M j', strtotime($cl['created_at']))) ?></p>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>

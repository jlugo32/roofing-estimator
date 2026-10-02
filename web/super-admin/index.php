<?php
/**
 * Super-admin dashboard — platform-wide stats across ALL companies
 * (deliberate: this is the platform owner view, not a tenant view).
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$admin = require_super_admin();

$pdo = db();

// ── Company counts by subscription status ──
$all_statuses = ['trial', 'active', 'past_due', 'suspended', 'cancelled'];
$status_counts = array_fill_keys($all_statuses, 0);
$stmt = $pdo->prepare('SELECT subscription_status, COUNT(*) AS n FROM companies GROUP BY subscription_status');
$stmt->execute();
foreach ($stmt->fetchAll() as $row) {
    $status_counts[$row['subscription_status']] = (int) $row['n'];
}
$total_companies = array_sum($status_counts);

// ── Platform-wide lead / estimate volume ──
$stmt = $pdo->prepare('SELECT COUNT(*) FROM clients');
$stmt->execute();
$total_leads = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM estimates');
$stmt->execute();
$total_estimates = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM estimates WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
$stmt->execute();
$estimates_30d = (int) $stmt->fetchColumn();

// ── MRR = active companies × plan price (platform_settings, default $99/mo) ──
$stmt = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
$stmt->execute(['monthly_price_cents']);
$price_cents = (int) (($stmt->fetchColumn()) ?: '9900');
$mrr_cents   = $status_counts['active'] * $price_cents;

// ── Recent signups ──
$stmt = $pdo->prepare(
    'SELECT id, name, slug, subscription_status, created_at
     FROM companies ORDER BY created_at DESC, id DESC LIMIT 10'
);
$stmt->execute();
$recent_companies = $stmt->fetchAll();

$active     = 'home';
$page_title = 'Dashboard';
require PROJECT_ROOT . '/includes/superadmin-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Platform Dashboard</h1>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <div class="card">
    <p class="text-sm text-slate-500">Total companies</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($total_companies) ?></p>
    <div class="mt-3 flex flex-wrap gap-1.5">
      <?php foreach ($all_statuses as $status): ?>
        <?php if ($status_counts[$status] > 0): ?>
        <span class="badge badge-<?= e($status) ?>"><?= e(str_replace('_', ' ', $status)) ?>: <?= number_format($status_counts[$status]) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <p class="text-sm text-slate-500">Total leads</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($total_leads) ?></p>
    <p class="text-xs text-slate-400 mt-3">Platform-wide, all companies</p>
  </div>
  <div class="card">
    <p class="text-sm text-slate-500">Total estimates</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($total_estimates) ?></p>
    <p class="text-xs text-slate-400 mt-3"><?= number_format($estimates_30d) ?> in the last 30 days</p>
  </div>
  <div class="card">
    <p class="text-sm text-slate-500">MRR (estimated)</p>
    <p class="text-3xl font-bold mt-1"><?= e(money($mrr_cents)) ?></p>
    <p class="text-xs text-slate-400 mt-3">active companies &times; plan price (set in Platform Settings)</p>
  </div>
</div>

<div class="card">
  <div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold text-lg">Recent signups</h2>
    <a href="/super-admin/companies.php" class="text-sm text-violet-700 hover:underline">All companies &rarr;</a>
  </div>
  <?php if (!$recent_companies): ?>
  <p class="text-sm text-slate-500">No companies yet.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Estimator</th>
          <th>Status</th>
          <th>Signed up</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_companies as $c): ?>
        <tr>
          <td class="font-medium"><?= e($c['name']) ?></td>
          <td>
            <a href="<?= e(SITE_URL . '/estimate/' . $c['slug']) ?>" target="_blank" rel="noopener"
               class="text-violet-700 hover:underline"><?= e($c['slug']) ?> &#8599;</a>
          </td>
          <td><span class="badge badge-<?= e($c['subscription_status']) ?>"><?= e(str_replace('_', ' ', $c['subscription_status'])) ?></span></td>
          <td class="text-slate-500"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
          <td><a href="/super-admin/company-view.php?id=<?= (int) $c['id'] ?>" class="text-violet-700 hover:underline">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require PROJECT_ROOT . '/includes/superadmin-footer.php'; ?>

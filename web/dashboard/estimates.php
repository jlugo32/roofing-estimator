<?php
/**
 * Estimates list — filterable by desired material / source / date range,
 * paginated. Every query is tenant-scoped to the authenticated company.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

require_once PROJECT_ROOT . '/includes/estimator-engine.php';

$pdo = db();

const ESTIMATES_PER_PAGE = 20;

$viaOptions = ['public_web' => 'Public web', 'dashboard' => 'Dashboard', 'mobile_app' => 'Mobile app'];

// ── Filters (GET), all whitelisted / validated ──
$material = (string) ($_GET['material'] ?? '');
if (!array_key_exists($material, ROOF_MATERIALS)) {
    $material = '';
}
$via = (string) ($_GET['via'] ?? '');
if (!array_key_exists($via, $viaOptions)) {
    $via = '';
}

/** Accept only a real Y-m-d date, otherwise ''. */
$valid_date = static function (string $d): string {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
};
$dateFrom = $valid_date(trim((string) ($_GET['from'] ?? '')));
$dateTo   = $valid_date(trim((string) ($_GET['to'] ?? '')));
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom]; // swap a backwards range
}

$page = max(1, (int) ($_GET['page'] ?? 1));

// ── Build WHERE (always e.company_id first) ──
$where  = 'e.company_id = ?';
$params = [$cid];
if ($material !== '') {
    $where .= ' AND e.roof_material_desired = ?';
    $params[] = $material;
}
if ($via !== '') {
    $where .= ' AND e.created_via = ?';
    $params[] = $via;
}
if ($dateFrom !== '') {
    $where .= ' AND e.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where .= ' AND e.created_at < ?'; // exclusive upper bound = inclusive "to" day
    $params[] = date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00';
}

// ── Count + clamp page ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM estimates e WHERE $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / ESTIMATES_PER_PAGE));
$page   = min($page, $totalPages);
$offset = ($page - 1) * ESTIMATES_PER_PAGE;

// ── Page of rows (join stays inside the tenant on both sides) ──
$sql = "SELECT e.id, e.client_id, e.results_token, e.roof_material_desired, e.roof_sqft_estimate,
               e.estimate_low_cents, e.estimate_high_cents, e.created_via, e.created_at,
               c.name AS client_name
        FROM estimates e
        JOIN clients c ON c.id = e.client_id AND c.company_id = e.company_id
        WHERE $where
        ORDER BY e.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) {
    $stmt->bindValue($i++, $p);
}
$stmt->bindValue($i++, ESTIMATES_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue($i, $offset, PDO::PARAM_INT);
$stmt->execute();
$estimates = $stmt->fetchAll();

/** Pagination URL preserving active filters. */
$page_url = static function (int $p) use ($material, $via, $dateFrom, $dateTo): string {
    $qs = array_filter(['material' => $material, 'via' => $via, 'from' => $dateFrom, 'to' => $dateTo]);
    $qs['page'] = $p;
    return '/dashboard/estimates.php?' . http_build_query($qs);
};

$active     = 'estimates';
$page_title = 'Estimates';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold">Estimates</h1>
  <p class="text-sm text-slate-500"><?= number_format($total) ?> total</p>
</div>

<!-- Filter bar -->
<form method="get" class="card mb-4">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
    <div>
      <label for="f-material" class="field-label">Desired material</label>
      <select id="f-material" name="material" class="field-input">
        <option value="">All materials</option>
        <?php foreach (ROOF_MATERIALS as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $key === $material ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="f-via" class="field-label">Source</label>
      <select id="f-via" name="via" class="field-input">
        <option value="">All sources</option>
        <?php foreach ($viaOptions as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $key === $via ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="f-from" class="field-label">From</label>
      <input id="f-from" type="date" name="from" value="<?= e($dateFrom) ?>" class="field-input">
    </div>
    <div>
      <label for="f-to" class="field-label">To</label>
      <input id="f-to" type="date" name="to" value="<?= e($dateTo) ?>" class="field-input">
    </div>
    <div class="flex gap-2">
      <button type="submit" class="btn-primary flex-1">Filter</button>
      <a href="/dashboard/estimates.php" class="btn-outline">Reset</a>
    </div>
  </div>
</form>

<div class="card p-0 overflow-hidden">
  <?php if (!$estimates): ?>
    <div class="p-8 text-center text-sm text-slate-500">
      <?php if ($material !== '' || $via !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
        No estimates match these filters. <a href="/dashboard/estimates.php" class="underline font-medium">Clear filters</a>
      <?php else: ?>
        No estimates yet — share your estimator link to get your first lead.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Client</th>
            <th>Range</th>
            <th>Material</th>
            <th class="text-right">Roof sq ft</th>
            <th>Via</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($estimates as $est): ?>
          <tr>
            <td class="whitespace-nowrap text-slate-500"><?= e(date('M j, Y', strtotime($est['created_at']))) ?></td>
            <td>
              <a href="/dashboard/client-view.php?id=<?= (int) $est['client_id'] ?>"
                 class="font-medium text-[var(--color-primary)] hover:underline"><?= e($est['client_name']) ?></a>
            </td>
            <td class="whitespace-nowrap font-medium"><?= money((int) $est['estimate_low_cents']) ?> – <?= money((int) $est['estimate_high_cents']) ?></td>
            <td class="whitespace-nowrap"><?= e(ROOF_MATERIALS[$est['roof_material_desired']] ?? $est['roof_material_desired']) ?></td>
            <td class="text-right"><?= number_format((int) $est['roof_sqft_estimate']) ?></td>
            <td><span class="badge bg-slate-200 text-slate-700"><?= e($viaOptions[$est['created_via']] ?? $est['created_via']) ?></span></td>
            <td>
              <a href="/estimate/results.php?token=<?= e($est['results_token']) ?>" target="_blank" rel="noopener"
                 class="text-[var(--color-primary)] font-medium hover:underline whitespace-nowrap">Results ↗</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-4 text-sm">
  <?php if ($page > 1): ?>
    <a href="<?= e($page_url($page - 1)) ?>" class="btn-outline">&larr; Previous</a>
  <?php else: ?>
    <span></span>
  <?php endif; ?>
  <span class="text-slate-500">Page <?= $page ?> of <?= $totalPages ?></span>
  <?php if ($page < $totalPages): ?>
    <a href="<?= e($page_url($page + 1)) ?>" class="btn-outline">Next &rarr;</a>
  <?php else: ?>
    <span></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>

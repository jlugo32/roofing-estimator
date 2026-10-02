<?php
/**
 * Clients & leads list — filterable by status / free-text search, paginated.
 * Every query is tenant-scoped to the authenticated company.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

$pdo = db();

const CLIENT_STATUSES = ['new', 'contacted', 'quoted', 'won', 'lost'];
const CLIENTS_PER_PAGE = 20;

// ── Filters (GET) ──
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, CLIENT_STATUSES, true)) {
    $status = '';
}
$q = trim((string) ($_GET['q'] ?? ''));

$page = max(1, (int) ($_GET['page'] ?? 1));

// ── Build WHERE (always company_id first) ──
$where  = 'company_id = ?';
$params = [$cid];
if ($status !== '') {
    $where .= ' AND status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

// ── Count + clamp page ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / CLIENTS_PER_PAGE));
$page   = min($page, $totalPages);
$offset = ($page - 1) * CLIENTS_PER_PAGE;

// ── Page of rows (+ per-client estimate count, tenant-scoped subquery) ──
$sql = "SELECT c.id, c.name, c.email, c.phone, c.zip_code, c.state, c.status, c.created_at,
               (SELECT COUNT(*) FROM estimates e
                 WHERE e.client_id = c.id AND e.company_id = c.company_id) AS estimate_count
        FROM clients c
        WHERE $where
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) {
    $stmt->bindValue($i++, $p);
}
$stmt->bindValue($i++, CLIENTS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue($i, $offset, PDO::PARAM_INT);
$stmt->execute();
$clients = $stmt->fetchAll();

/** Pagination URL preserving active filters. */
$page_url = static function (int $p) use ($status, $q): string {
    $qs = array_filter(['status' => $status, 'q' => $q]);
    $qs['page'] = $p;
    return '/dashboard/clients.php?' . http_build_query($qs);
};

$active     = 'clients';
$page_title = 'Clients & Leads';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold">Clients &amp; Leads</h1>
  <p class="text-sm text-slate-500"><?= number_format($total) ?> total</p>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">Client deleted.</div>
<?php endif; ?>

<!-- Filter bar -->
<form method="get" class="card mb-4">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
    <div>
      <label for="f-status" class="field-label">Status</label>
      <select id="f-status" name="status" class="field-input">
        <option value="">All statuses</option>
        <?php foreach (CLIENT_STATUSES as $s): ?>
        <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="sm:col-span-1 lg:col-span-2">
      <label for="f-q" class="field-label">Search</label>
      <input id="f-q" type="text" name="q" value="<?= e($q) ?>" class="field-input"
             placeholder="Name, email, or phone">
    </div>
    <div class="flex gap-2">
      <button type="submit" class="btn-primary flex-1">Filter</button>
      <a href="/dashboard/clients.php" class="btn-outline">Reset</a>
    </div>
  </div>
</form>

<div class="card p-0 overflow-hidden">
  <?php if (!$clients): ?>
    <div class="p-8 text-center text-sm text-slate-500">
      <?php if ($status !== '' || $q !== ''): ?>
        No clients match these filters. <a href="/dashboard/clients.php" class="underline font-medium">Clear filters</a>
      <?php else: ?>
        No clients yet — share your estimator link to get your first lead.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Location</th>
            <th>Status</th>
            <th class="text-right">Estimates</th>
            <th>Added</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
          <tr data-href="/dashboard/client-view.php?id=<?= (int) $c['id'] ?>">
            <td class="font-medium whitespace-nowrap"><?= e($c['name']) ?></td>
            <td>
              <span class="block truncate max-w-[14rem]"><?= e($c['email']) ?></span>
              <span class="block text-xs text-slate-500"><?= e($c['phone']) ?></span>
            </td>
            <td class="whitespace-nowrap"><?= e($c['zip_code']) ?>, <?= e($c['state']) ?></td>
            <td><span class="badge badge-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td class="text-right"><?= (int) $c['estimate_count'] ?></td>
            <td class="whitespace-nowrap text-slate-500"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
            <td>
              <a href="/dashboard/client-view.php?id=<?= (int) $c['id'] ?>"
                 class="text-[var(--color-primary)] font-medium hover:underline whitespace-nowrap">View</a>
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

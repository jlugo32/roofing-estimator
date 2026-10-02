<?php
/**
 * Super-admin company list — search, status filter, pagination, and inline
 * suspend/activate actions. Spans ALL companies on purpose (platform owner
 * view); per-company detail lives in company-view.php (explicit id).
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$admin = require_super_admin();

$valid_statuses = ['trial', 'active', 'past_due', 'suspended', 'cancelled'];

// ── Suspend / Activate (inline POST forms below post back to this page) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $company_id = (int) ($_POST['company_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    if ($company_id > 0 && in_array($action, ['suspend', 'activate'], true)) {
        $new_status = $action === 'suspend' ? 'suspended' : 'active';
        $stmt = db()->prepare('UPDATE companies SET subscription_status = ? WHERE id = ?');
        $stmt->execute([$new_status, $company_id]);
    }
    $qs = $_GET ? '?' . http_build_query($_GET) : '';
    redirect('/super-admin/companies.php' . $qs);
}

// ── Filters + pagination ──
$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, $valid_statuses, true)) {
    $status = '';
}
$per_page = 25;
$page     = max(1, (int) ($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(c.name LIKE ? OR c.slug LIKE ? OR c.contact_email LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $where[]  = 'c.subscription_status = ?';
    $params[] = $status;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare('SELECT COUNT(*) FROM companies c' . $where_sql);
$stmt->execute($params);
$total_rows  = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$stmt = db()->prepare(
    'SELECT c.id, c.name, c.slug, c.subscription_status, c.trial_ends_at, c.created_at,
            (SELECT COUNT(*) FROM clients cl WHERE cl.company_id = c.id)   AS leads_count,
            (SELECT COUNT(*) FROM estimates es WHERE es.company_id = c.id) AS estimates_count
     FROM companies c' . $where_sql . '
     ORDER BY c.created_at DESC, c.id DESC
     LIMIT ? OFFSET ?'
);
$i = 1;
foreach ($params as $p) {
    $stmt->bindValue($i++, $p);
}
$stmt->bindValue($i++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($i, $offset, PDO::PARAM_INT);
$stmt->execute();
$companies = $stmt->fetchAll();

// Query string that preserves current filters (for form actions + pagination).
$filter_qs = http_build_query(array_filter(['q' => $q, 'status' => $status, 'page' => $page > 1 ? $page : null]));
$self_url  = '/super-admin/companies.php' . ($filter_qs !== '' ? '?' . $filter_qs : '');

$active     = 'companies';
$page_title = 'Companies';
require PROJECT_ROOT . '/includes/superadmin-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Companies</h1>

<div class="card mb-4">
  <form method="get" action="/super-admin/companies.php" class="flex flex-col sm:flex-row gap-3 sm:items-end">
    <div class="flex-1">
      <label class="field-label" for="q">Search</label>
      <input class="field-input" type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, slug, or contact email">
    </div>
    <div>
      <label class="field-label" for="status">Status</label>
      <select class="field-input" id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach ($valid_statuses as $s): ?>
        <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-secondary">Filter</button>
  </form>
</div>

<div class="card">
  <p class="text-sm text-slate-500 mb-3"><?= number_format($total_rows) ?> compan<?= $total_rows === 1 ? 'y' : 'ies' ?> found</p>
  <?php if (!$companies): ?>
  <p class="text-sm text-slate-500">No companies match your filters.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Slug</th>
          <th>Status</th>
          <th>Leads</th>
          <th>Estimates</th>
          <th>Trial ends</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
        <tr>
          <td class="text-slate-500"><?= (int) $c['id'] ?></td>
          <td class="font-medium"><?= e($c['name']) ?></td>
          <td class="text-slate-500"><?= e($c['slug']) ?></td>
          <td><span class="badge badge-<?= e($c['subscription_status']) ?>"><?= e(str_replace('_', ' ', $c['subscription_status'])) ?></span></td>
          <td><?= number_format((int) $c['leads_count']) ?></td>
          <td><?= number_format((int) $c['estimates_count']) ?></td>
          <td class="text-slate-500"><?= $c['trial_ends_at'] ? e(date('M j, Y', strtotime($c['trial_ends_at']))) : '—' ?></td>
          <td class="text-slate-500"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
          <td class="whitespace-nowrap">
            <a href="/super-admin/company-view.php?id=<?= (int) $c['id'] ?>" class="text-violet-700 hover:underline">View</a>
            <?php if ($c['subscription_status'] !== 'suspended'): ?>
            <form method="post" action="<?= e($self_url) ?>" class="inline ml-2"
                  onsubmit="return confirm('Suspend <?= e(addslashes($c['name'])) ?>? Their dashboard and public estimator will be blocked immediately.');">
              <?= csrf_field() ?>
              <input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>">
              <input type="hidden" name="action" value="suspend">
              <button type="submit" data-confirm class="text-red-700 hover:underline">Suspend</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= e($self_url) ?>" class="inline ml-2">
              <?= csrf_field() ?>
              <input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>">
              <input type="hidden" name="action" value="activate">
              <button type="submit" class="text-green-700 hover:underline">Activate</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="flex items-center justify-between mt-4 text-sm">
    <?php
    $prev_qs = http_build_query(array_filter(['q' => $q, 'status' => $status, 'page' => $page - 1 > 1 ? $page - 1 : null]));
    $next_qs = http_build_query(array_filter(['q' => $q, 'status' => $status, 'page' => $page + 1]));
    ?>
    <?php if ($page > 1): ?>
    <a href="/super-admin/companies.php<?= $prev_qs !== '' ? '?' . e($prev_qs) : '' ?>" class="text-violet-700 hover:underline">&larr; Previous</a>
    <?php else: ?><span></span><?php endif; ?>
    <span class="text-slate-500">Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
    <a href="/super-admin/companies.php?<?= e($next_qs) ?>" class="text-violet-700 hover:underline">Next &rarr;</a>
    <?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<p class="text-xs text-slate-500 mt-4">
  Suspending a company immediately blocks its dashboard logins and its public estimator —
  enforcement already lives in core auth and the public estimate endpoint. Activate restores access.
</p>

<?php require PROJECT_ROOT . '/includes/superadmin-footer.php'; ?>

<?php
/**
 * Super-admin company detail — profile, users, stats, read-only pricing
 * settings, and actions (suspend/activate + impersonate for support access).
 * Fetches a single company by explicit id.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$admin = require_super_admin();

$id      = (int) ($_GET['id'] ?? 0);
$company = $id > 0 ? get_company($id) : null;
if (!$company) {
    header('X-Robots-Tag: noindex, nofollow');
    http_response_code(404);
    exit('Company not found.');
}

// ── Actions: suspend / activate / impersonate ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'suspend' || $action === 'activate') {
        $new_status = $action === 'suspend' ? 'suspended' : 'active';
        $stmt = db()->prepare('UPDATE companies SET subscription_status = ? WHERE id = ?');
        $stmt->execute([$new_status, $id]);
        redirect('/super-admin/company-view.php?id=' . $id . '&msg=' . ($action === 'suspend' ? 'suspended' : 'activated'));
    }

    if ($action === 'impersonate') {
        // Prefer the owner; fall back to any user in the company.
        $stmt = db()->prepare("SELECT id FROM company_users WHERE company_id = ? AND role = 'owner' ORDER BY id LIMIT 1");
        $stmt->execute([$id]);
        $target_user_id = $stmt->fetchColumn();
        if (!$target_user_id) {
            $stmt = db()->prepare('SELECT id FROM company_users WHERE company_id = ? ORDER BY id LIMIT 1');
            $stmt->execute([$id]);
            $target_user_id = $stmt->fetchColumn();
        }
        if (!$target_user_id) {
            redirect('/super-admin/company-view.php?id=' . $id . '&msg=no_user');
        }

        // Close the SA session cleanly, then open a company session as the
        // target user. The dashboard shell shows a support-mode banner when
        // impersonated_by_sa is set.
        session_write_close();
        start_company_session();
        session_regenerate_id(true);
        $_SESSION['company_user_id']    = (int) $target_user_id;
        $_SESSION['impersonated_by_sa'] = (int) $admin['id'];
        unset($_SESSION['csrf_token']); // fresh token for the new session
        redirect('/dashboard/');
    }
}

$flash_messages = [
    'suspended' => 'Company suspended. Dashboard logins and the public estimator are now blocked.',
    'activated' => 'Company activated. Access has been restored.',
    'no_user'   => 'Cannot impersonate: this company has no users.',
];
$msg_key = (string) ($_GET['msg'] ?? '');
$flash   = $flash_messages[$msg_key] ?? '';

// ── Users ──
$stmt = db()->prepare(
    'SELECT id, name, email, role, created_at, api_token_expires_at
     FROM company_users WHERE company_id = ? ORDER BY id'
);
$stmt->execute([$id]);
$users = $stmt->fetchAll();

// ── Stats ──
$stmt = db()->prepare('SELECT COUNT(*) FROM clients WHERE company_id = ?');
$stmt->execute([$id]);
$clients_count = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) AS n, MAX(created_at) AS latest FROM estimates WHERE company_id = ?');
$stmt->execute([$id]);
$estimate_stats  = $stmt->fetch() ?: ['n' => 0, 'latest' => null];
$estimates_count = (int) $estimate_stats['n'];
$latest_estimate = $estimate_stats['latest'];

// ── Pricing settings (read-only) ──
$stmt = db()->prepare(
    'SELECT setting_key, setting_value FROM company_pricing_settings
     WHERE company_id = ? ORDER BY setting_key'
);
$stmt->execute([$id]);
$pricing = $stmt->fetchAll();

$is_suspended = $company['subscription_status'] === 'suspended';

$active     = 'companies';
$page_title = $company['name'];
require PROJECT_ROOT . '/includes/superadmin-header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold"><?= e($company['name']) ?></h1>
  <a href="/super-admin/companies.php" class="text-sm text-violet-700 hover:underline">&larr; All companies</a>
</div>

<?php if ($flash !== ''): ?>
<div class="mb-4 rounded-md <?= $msg_key === 'no_user' ? 'bg-red-50 border border-red-200 text-red-800' : 'bg-green-50 border border-green-200 text-green-800' ?> text-sm px-3 py-2">
  <?= e($flash) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="card">
    <p class="text-sm text-slate-500">Leads (clients)</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($clients_count) ?></p>
  </div>
  <div class="card">
    <p class="text-sm text-slate-500">Estimates</p>
    <p class="text-3xl font-bold mt-1"><?= number_format($estimates_count) ?></p>
  </div>
  <div class="card">
    <p class="text-sm text-slate-500">Most recent estimate</p>
    <p class="text-xl font-bold mt-2"><?= $latest_estimate ? e(date('M j, Y g:ia', strtotime($latest_estimate))) : '—' ?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
  <!-- Profile -->
  <div class="card">
    <h2 class="font-semibold text-lg mb-3">Company profile</h2>
    <dl class="text-sm space-y-2">
      <div class="flex justify-between gap-4"><dt class="text-slate-500">ID</dt><dd><?= (int) $company['id'] ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Slug</dt><dd><?= e($company['slug']) ?></dd></div>
      <div class="flex justify-between gap-4">
        <dt class="text-slate-500">Status</dt>
        <dd><span class="badge badge-<?= e($company['subscription_status']) ?>"><?= e(str_replace('_', ' ', $company['subscription_status'])) ?></span></dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="text-slate-500">Public estimator</dt>
        <dd><a href="<?= e(SITE_URL . '/estimate/' . $company['slug']) ?>" target="_blank" rel="noopener" class="text-violet-700 hover:underline"><?= e('/estimate/' . $company['slug']) ?> &#8599;</a></dd>
      </div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Contact email</dt><dd><?= e($company['contact_email']) ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Contact phone</dt><dd><?= $company['contact_phone'] !== null && $company['contact_phone'] !== '' ? e($company['contact_phone']) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Service area</dt><dd><?= $company['service_area'] !== null && $company['service_area'] !== '' ? e($company['service_area']) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">License number</dt><dd><?= $company['license_number'] !== null && $company['license_number'] !== '' ? e($company['license_number']) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Logo</dt><dd><?= $company['logo_path'] !== null && $company['logo_path'] !== '' ? e($company['logo_path']) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Trial ends</dt><dd><?= $company['trial_ends_at'] ? e(date('M j, Y', strtotime($company['trial_ends_at']))) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Stripe customer</dt><dd class="font-mono text-xs"><?= $company['stripe_customer_id'] !== null && $company['stripe_customer_id'] !== '' ? e($company['stripe_customer_id']) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Stripe subscription</dt><dd class="font-mono text-xs"><?= $company['stripe_subscription_id'] !== null && $company['stripe_subscription_id'] !== '' ? e($company['stripe_subscription_id']) : '—' ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Created</dt><dd><?= e(date('M j, Y g:ia', strtotime($company['created_at']))) ?></dd></div>
    </dl>
  </div>

  <!-- Actions -->
  <div class="card">
    <h2 class="font-semibold text-lg mb-3">Actions</h2>

    <?php if (!$is_suspended): ?>
    <form method="post" action="/super-admin/company-view.php?id=<?= $id ?>" class="mb-4"
          onsubmit="return confirm('Suspend <?= e(addslashes($company['name'])) ?>? Their dashboard and public estimator will be blocked immediately.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="suspend">
      <button type="submit" data-confirm class="btn-outline w-full !border-red-700 !text-red-700 hover:!bg-red-700 hover:!text-white">Suspend company</button>
      <p class="field-help mt-2">Blocks dashboard logins and the public estimator (enforced in core auth and the public endpoint).</p>
    </form>
    <?php else: ?>
    <form method="post" action="/super-admin/company-view.php?id=<?= $id ?>" class="mb-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="activate">
      <button type="submit" class="btn-secondary w-full">Activate company</button>
      <p class="field-help mt-2">Sets status to active and restores dashboard + estimator access.</p>
    </form>
    <?php endif; ?>

    <hr class="my-4 border-slate-200">

    <form method="post" action="/super-admin/company-view.php?id=<?= $id ?>"
          onsubmit="return confirm('Impersonate <?= e(addslashes($company['name'])) ?>? Your super-admin session will end and you will be logged into their dashboard.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="impersonate">
      <button type="submit" class="btn-primary w-full">Impersonate (support access)</button>
      <p class="field-help mt-2">
        You will be logged in AS this company's owner and see exactly what they see —
        the dashboard shows a support-mode banner. Use <strong>/logout.php</strong> to end
        the impersonated session, then log back into the super-admin console.
      </p>
    </form>
  </div>
</div>

<!-- Users -->
<div class="card mb-6">
  <h2 class="font-semibold text-lg mb-3">Users</h2>
  <?php if (!$users): ?>
  <p class="text-sm text-slate-500">This company has no users.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Created</th>
          <th>API token</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="font-medium"><?= e($u['name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td class="capitalize"><?= e($u['role']) ?></td>
          <td class="text-slate-500"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td>
            <?php if ($u['api_token_expires_at'] && strtotime($u['api_token_expires_at']) > time()): ?>
            <span class="badge badge-active">active</span>
            <span class="text-xs text-slate-400">expires <?= e(date('M j, Y', strtotime($u['api_token_expires_at']))) ?></span>
            <?php elseif ($u['api_token_expires_at']): ?>
            <span class="badge badge-lost">expired</span>
            <?php else: ?>
            <span class="text-slate-400">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pricing settings (read-only) -->
<div class="card">
  <h2 class="font-semibold text-lg mb-3">Pricing settings <span class="text-sm font-normal text-slate-400">(read-only)</span></h2>
  <p class="text-sm text-slate-500 mb-3">
    Managed by the company in their own dashboard. Keys ending in <code>_cents</code> are integer cents.
  </p>
  <?php if (!$pricing): ?>
  <p class="text-sm text-slate-500">No pricing settings found.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Key</th>
          <th>Value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pricing as $row): ?>
        <tr>
          <td class="font-mono text-xs"><?= e($row['setting_key']) ?></td>
          <td>
            <?= e($row['setting_value']) ?>
            <?php if (str_ends_with($row['setting_key'], '_cents') && ctype_digit($row['setting_value'])): ?>
            <span class="text-xs text-slate-400">(<?= e(money((int) $row['setting_value'])) ?>)</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require PROJECT_ROOT . '/includes/superadmin-footer.php'; ?>

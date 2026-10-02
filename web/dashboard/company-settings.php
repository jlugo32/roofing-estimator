<?php
/**
 * Company settings — profile fields + logo upload. Slug is read-only
 * (changing it would break every shared estimator link). All updates
 * are WHERE id = the authenticated company only.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

$pdo     = db();
$company = $auth['company'];
$errors  = [];

const LOGO_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const LOGO_TYPES = [ // getimagesize mime → stored extension
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
];
const LOGO_EXT_WHITELIST = ['png', 'jpg', 'jpeg', 'webp'];

// ── POST ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'remove_logo') {
        $old = (string) ($company['logo_path'] ?? '');
        if ($old !== '' && str_starts_with($old, '/uploads/logos/') && is_file(PROJECT_ROOT . $old)) {
            @unlink(PROJECT_ROOT . $old);
        }
        $stmt = $pdo->prepare('UPDATE companies SET logo_path = NULL WHERE id = ?');
        $stmt->execute([$cid]);
        redirect('/dashboard/company-settings.php?saved=1');
    }

    if ($action === 'save') {
        $name    = trim((string) ($_POST['name'] ?? ''));
        $email   = trim((string) ($_POST['contact_email'] ?? ''));
        $phone   = trim((string) ($_POST['contact_phone'] ?? ''));
        $area    = trim((string) ($_POST['service_area'] ?? ''));
        $license = trim((string) ($_POST['license_number'] ?? ''));

        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Please enter a company name (150 characters max).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid contact email.';
        }

        // ── Optional logo upload ──
        $newLogoPath = null;
        $file = $_FILES['logo'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Logo upload failed — please try again.';
            } elseif ((int) $file['size'] > LOGO_MAX_BYTES) {
                $errors[] = 'Logo must be 2 MB or smaller.';
            } else {
                $origExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                $info    = @getimagesize((string) $file['tmp_name']);
                $mime    = $info['mime'] ?? '';
                if (!in_array($origExt, LOGO_EXT_WHITELIST, true) || !isset(LOGO_TYPES[$mime])) {
                    $errors[] = 'Logo must be a PNG, JPEG, or WebP image.';
                } else {
                    $ext = LOGO_TYPES[$mime];
                    $dir = PROJECT_ROOT . '/uploads/logos';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0750, true);
                    }
                    $dest = $dir . '/company-' . $cid . '.' . $ext;
                    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
                        $errors[] = 'Could not save the logo file — please try again.';
                    } else {
                        $newLogoPath = '/uploads/logos/company-' . $cid . '.' . $ext;
                        // Old logo with a different extension would be orphaned — remove it.
                        $old = (string) ($company['logo_path'] ?? '');
                        if ($old !== '' && $old !== $newLogoPath
                            && str_starts_with($old, '/uploads/logos/') && is_file(PROJECT_ROOT . $old)) {
                            @unlink(PROJECT_ROOT . $old);
                        }
                    }
                }
            }
        }

        if (!$errors) {
            if ($newLogoPath !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE companies
                     SET name = ?, contact_email = ?, contact_phone = ?, service_area = ?,
                         license_number = ?, logo_path = ?
                     WHERE id = ?'
                );
                $stmt->execute([$name, $email, $phone, $area, $license, $newLogoPath, $cid]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE companies
                     SET name = ?, contact_email = ?, contact_phone = ?, service_area = ?, license_number = ?
                     WHERE id = ?'
                );
                $stmt->execute([$name, $email, $phone, $area, $license, $cid]);
            }
            redirect('/dashboard/company-settings.php?saved=1');
        }

        // Validation failed — re-render with the submitted values.
        $company = array_merge($company, [
            'name'           => $name,
            'contact_email'  => $email,
            'contact_phone'  => $phone,
            'service_area'   => $area,
            'license_number' => $license,
        ]);
    }
}

$estimatorUrl = SITE_URL . '/estimate/' . $company['slug'];

$active     = 'company-settings';
$page_title = 'Company Settings';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Company Settings</h1>

<?php if (isset($_GET['saved'])): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">Company settings saved.</div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="card mb-4 border-l-4 border-[var(--color-danger)]">
  <?php foreach ($errors as $err): ?>
  <p class="field-error"><?= e($err) ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
  <form method="post" enctype="multipart/form-data" class="card lg:col-span-2 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div>
      <label for="c-name" class="field-label">Company name</label>
      <input id="c-name" type="text" name="name" required maxlength="150"
             value="<?= e($company['name']) ?>" class="field-input">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="c-email" class="field-label">Contact email</label>
        <input id="c-email" type="email" name="contact_email" required
               value="<?= e($company['contact_email']) ?>" class="field-input">
        <p class="field-help">New-lead notifications go here.</p>
      </div>
      <div>
        <label for="c-phone" class="field-label">Contact phone</label>
        <input id="c-phone" type="tel" name="contact_phone"
               value="<?= e($company['contact_phone'] ?? '') ?>" class="field-input">
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="c-area" class="field-label">Service area</label>
        <input id="c-area" type="text" name="service_area"
               value="<?= e($company['service_area'] ?? '') ?>" class="field-input"
               placeholder="e.g. Greater Tampa Bay">
      </div>
      <div>
        <label for="c-license" class="field-label">License number</label>
        <input id="c-license" type="text" name="license_number"
               value="<?= e($company['license_number'] ?? '') ?>" class="field-input">
        <p class="field-help">Shown on your public estimator for credibility.</p>
      </div>
    </div>

    <div>
      <label class="field-label">Logo</label>
      <?php if (!empty($company['logo_path'])): ?>
      <div class="flex items-center gap-4 mb-2">
        <img src="<?= e($company['logo_path']) ?>" alt="Current logo"
             class="h-16 w-auto max-w-[10rem] object-contain rounded border border-slate-200 bg-white p-1">
        <span class="text-xs text-slate-500">Current logo</span>
      </div>
      <?php endif; ?>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="field-input !py-2">
      <p class="field-help">PNG, JPEG, or WebP — 2 MB max. Shown on your public estimator and emails.</p>
    </div>

    <div class="pt-2">
      <button type="submit" class="btn-primary">Save settings</button>
    </div>
  </form>

  <div class="space-y-4">
    <div class="card">
      <h2 class="font-semibold mb-2">Your estimator URL</h2>
      <label for="c-slug" class="field-label">Slug (read-only)</label>
      <input id="c-slug" type="text" readonly value="<?= e($company['slug']) ?>"
             class="field-input bg-slate-50 font-mono text-sm mb-1">
      <p class="field-help mb-3">
        The slug can't be changed — links you've already shared would stop working.
      </p>
      <input id="c-est-url" type="text" readonly value="<?= e($estimatorUrl) ?>"
             class="field-input bg-slate-50 font-mono text-sm" onclick="this.select()">
      <div class="flex gap-2 mt-2">
        <button type="button" class="btn-outline text-sm" data-copy="#c-est-url">Copy</button>
        <a href="<?= e($estimatorUrl) ?>" target="_blank" rel="noopener" class="btn-outline text-sm">Open ↗</a>
      </div>
    </div>

    <?php if (!empty($company['logo_path'])): ?>
    <div class="card">
      <h2 class="font-semibold mb-2">Remove logo</h2>
      <p class="text-sm text-slate-500 mb-3">Your estimator will fall back to your company name.</p>
      <form method="post" data-confirm="Remove your logo?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove_logo">
        <button type="submit" class="btn-outline !border-[var(--color-danger)] !text-[var(--color-danger)] hover:!bg-red-50 text-sm">
          Remove logo
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>

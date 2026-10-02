<?php
/**
 * Account — profile, password, and API key for the LOGGED-IN user only.
 * All updates target $auth['user']['id']; nothing here accepts a user id
 * from the request.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth   = require_company_auth(); // FIRST — tenant gate
$cid    = $auth['company_id'];
$userId = (int) $auth['user']['id'];

$pdo = db();

$errors      = ['profile' => [], 'password' => []];
$issuedToken = null; // set when a key is generated this request — shown exactly once

// ── POST ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['profile'][] = 'Please enter your name (100 characters max).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors['profile'][] = 'Please enter a valid email address.';
        }
        if (!$errors['profile']) {
            try {
                $stmt = $pdo->prepare('UPDATE company_users SET name = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $email, $userId]);
                redirect('/dashboard/account.php?saved=profile');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { // email UNIQUE across the platform
                    $errors['profile'][] = 'That email address is already in use.';
                } else {
                    throw $e;
                }
            }
        }
        // fall through and re-render with errors; keep submitted values
        $auth['user']['name']  = $name;
        $auth['user']['email'] = $email;
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        // Fresh fetch — never trust a stale/absent hash on the auth payload.
        $stmt = $pdo->prepare('SELECT password_hash FROM company_users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors['password'][] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 10) {
            $errors['password'][] = 'New password must be at least 10 characters.';
        }
        if ($new !== $confirm) {
            $errors['password'][] = 'New passwords do not match.';
        }
        if (!$errors['password']) {
            $stmt = $pdo->prepare('UPDATE company_users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            redirect('/dashboard/account.php?saved=password');
        }
    }

    if ($action === 'generate') {
        // Plaintext is shown ONCE on this response — only the hash is stored,
        // so a redirect here would lose it. Intentionally no PRG.
        $issuedToken = issue_api_token($userId);
    }

    if ($action === 'revoke') {
        revoke_api_token($userId);
        redirect('/dashboard/account.php?saved=revoked');
    }
}

$hasToken     = !empty($auth['user']['has_api_token']) || $issuedToken !== null;
$tokenExpires = $issuedToken['expires_at'] ?? $auth['user']['api_token_expires_at'] ?? null;

$active     = 'account';
$page_title = 'Account & API';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Account &amp; API</h1>

<?php if (($_GET['saved'] ?? '') === 'profile'): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">Profile updated.</div>
<?php elseif (($_GET['saved'] ?? '') === 'password'): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">Password changed.</div>
<?php elseif (($_GET['saved'] ?? '') === 'revoked'): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">API key revoked.</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
  <!-- (a) Profile -->
  <div class="card">
    <h2 class="font-semibold mb-3">Profile</h2>
    <?php foreach ($errors['profile'] as $err): ?>
    <p class="field-error mb-2"><?= e($err) ?></p>
    <?php endforeach; ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <div>
        <label for="p-name" class="field-label">Name</label>
        <input id="p-name" type="text" name="name" required maxlength="100"
               value="<?= e($auth['user']['name']) ?>" class="field-input">
      </div>
      <div>
        <label for="p-email" class="field-label">Email</label>
        <input id="p-email" type="email" name="email" required maxlength="150"
               value="<?= e($auth['user']['email']) ?>" class="field-input">
        <p class="field-help">You'll use this to log in.</p>
      </div>
      <button type="submit" class="btn-primary">Save profile</button>
    </form>
  </div>

  <!-- (b) Change password -->
  <div class="card">
    <h2 class="font-semibold mb-3">Change password</h2>
    <?php foreach ($errors['password'] as $err): ?>
    <p class="field-error mb-2"><?= e($err) ?></p>
    <?php endforeach; ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div>
        <label for="pw-current" class="field-label">Current password</label>
        <input id="pw-current" type="password" name="current_password" required
               autocomplete="current-password" class="field-input">
      </div>
      <div>
        <label for="pw-new" class="field-label">New password</label>
        <input id="pw-new" type="password" name="new_password" required minlength="10"
               autocomplete="new-password" class="field-input">
        <p class="field-help">At least 10 characters.</p>
      </div>
      <div>
        <label for="pw-confirm" class="field-label">Confirm new password</label>
        <input id="pw-confirm" type="password" name="new_password_confirm" required minlength="10"
               autocomplete="new-password" class="field-input">
      </div>
      <button type="submit" class="btn-primary">Change password</button>
    </form>
  </div>

  <!-- (c) API key -->
  <div class="card lg:col-span-2">
    <h2 class="font-semibold mb-1">API key <span class="text-xs font-normal text-slate-500">(mobile app — Phase 2)</span></h2>
    <p class="text-sm text-slate-500 mb-4">
      This key lets the upcoming <?= e(APP_NAME) ?> mobile app access your account.
      Keep it secret — it grants the same access as your login.
      Keys are valid for <?= (int) API_TOKEN_TTL_DAYS ?> days.
    </p>

    <?php if ($issuedToken !== null): ?>
    <div class="border-l-4 border-[var(--color-success)] bg-green-50 rounded p-4 mb-4">
      <p class="text-sm font-semibold text-green-900 mb-2">Your new API key — copy it now:</p>
      <div class="flex flex-col sm:flex-row gap-2">
        <input id="api-token" type="text" readonly value="<?= e($issuedToken['token']) ?>"
               class="field-input flex-1 font-mono text-xs bg-white" onclick="this.select()">
        <button type="button" class="btn-secondary whitespace-nowrap" data-copy="#api-token">Copy key</button>
      </div>
      <p class="text-xs text-green-900 mt-2 font-medium">
        This key will NOT be shown again. It expires <?= e(date('M j, Y', strtotime($issuedToken['expires_at']))) ?>.
      </p>
    </div>
    <?php endif; ?>

    <?php if ($hasToken): ?>
      <?php if ($issuedToken === null): ?>
      <div class="flex flex-wrap items-center gap-3 mb-4 text-sm">
        <span class="font-mono bg-slate-100 rounded px-3 py-1.5 text-slate-500">••••••••••••••••••••••••</span>
        <span class="text-slate-500">
          <?php if ($tokenExpires): ?>
            Expires <?= e(date('M j, Y', strtotime((string) $tokenExpires))) ?>
            <?php if (strtotime((string) $tokenExpires) < time()): ?>
              <span class="badge badge-past_due ml-1">expired</span>
            <?php endif; ?>
          <?php endif; ?>
        </span>
      </div>
      <?php endif; ?>
      <div class="flex flex-wrap gap-2">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="generate">
          <button type="submit" class="btn-secondary"
                  data-confirm="Regenerate your API key? The current key stops working immediately.">Regenerate key</button>
        </form>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="revoke">
          <button type="submit" class="btn-outline !border-[var(--color-danger)] !text-[var(--color-danger)] hover:!bg-red-50"
                  data-confirm="Revoke your API key? Any connected app will lose access immediately.">Revoke key</button>
        </form>
      </div>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <button type="submit" class="btn-primary">Generate API key</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>

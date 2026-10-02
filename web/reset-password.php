<?php
/**
 * Password reset completion. Validates the emailed token (64 hex chars,
 * sha256-hashed at rest, single-use, time-limited) on BOTH the GET render
 * and the POST submit, then updates the user's password and marks the
 * token used. Success bounces to /login.php?reset=1.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

start_company_session();

$isPost = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
if ($isPost) {
    csrf_verify();
}

// ── Look up a live (unused, unexpired) reset row for the raw token ──
$token = strtolower(trim((string) ($isPost ? ($_POST['token'] ?? '') : ($_GET['token'] ?? ''))));
$reset = null;
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $stmt = db()->prepare(
        'SELECT id, email FROM password_resets
         WHERE token_hash = ? AND used = 0 AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch() ?: null;
}

$errors = [];

if ($isPost && $reset) {
    $password        = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($password) < 10) {
        $errors['password'] = 'Password must be at least 10 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE company_users SET password_hash = ? WHERE email = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $reset['email']]);

        $stmt = db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
        $stmt->execute([(int) $reset['id']]);

        redirect('/login.php?reset=1');
    }
}

$page_title       = 'Reset password';
$page_description = 'Choose a new password for your ' . APP_NAME . ' account.';
$canonical_path   = '/reset-password.php';

include PROJECT_ROOT . '/includes/header.php';
?>

<section class="max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
  <?php if (!$reset): ?>
  <h1 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">Reset link invalid</h1>
  <div class="card mt-8 text-center">
    <p class="text-sm text-slate-700">
      This password reset link is invalid, has expired, or was already used.
      Reset links are valid for <?= PASSWORD_RESET_TTL_MINUTES ?> minutes.
    </p>
    <a href="/forgot-password.php" class="btn-primary mt-5 inline-block">Request a new link</a>
  </div>
  <?php else: ?>
  <h1 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">Choose a new password</h1>

  <form method="post" action="/reset-password.php" class="card mt-8 space-y-4" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div>
      <label class="field-label" for="password">New password</label>
      <input class="field-input" type="password" id="password" name="password" required
             minlength="10" autocomplete="new-password">
      <p class="field-help">At least 10 characters.</p>
      <?php if (!empty($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="field-label" for="password_confirm">Confirm new password</label>
      <input class="field-input" type="password" id="password_confirm" name="password_confirm" required
             minlength="10" autocomplete="new-password">
      <?php if (!empty($errors['password_confirm'])): ?><p class="field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn-primary w-full text-base py-3">Reset password</button>
  </form>
  <?php endif; ?>
</section>

<?php include PROJECT_ROOT . '/includes/footer.php'; ?>

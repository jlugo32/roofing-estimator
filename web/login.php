<?php
/**
 * Company user login. Rate-limited per IP before any password check;
 * deliberately generic error on bad credentials (no user enumeration).
 * Shows a small success note after a password reset (?reset=1).
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

start_company_session();

$error     = '';
$oldEmail  = '';
$resetDone = (($_GET['reset'] ?? '') === '1');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $oldEmail = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!rate_limit('web_login', RATE_LIMIT_WEB_LOGIN_MAX, RATE_LIMIT_WEB_LOGIN_WINDOW)) {
        $error = 'Too many login attempts. Please try again later.';
    } elseif ($oldEmail !== '' && $password !== '' && login_company_user($oldEmail, $password)) {
        redirect('/dashboard/');
    } else {
        $error = 'Invalid email or password.'; // deliberately generic
    }
}

$page_title       = 'Log in';
$page_description = 'Log in to your ' . APP_NAME . ' dashboard to manage your roofing estimator, pricing, and leads.';
$canonical_path   = '/login.php';

include PROJECT_ROOT . '/includes/header.php';
?>

<section class="max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
  <h1 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">Log in</h1>
  <p class="mt-3 text-center text-slate-600">Welcome back — your leads are waiting.</p>

  <?php if ($resetDone): ?>
  <p class="card mt-8 text-sm text-center" style="color:var(--color-success)">
    Your password has been reset. Log in with your new password below.
  </p>
  <?php endif; ?>

  <form method="post" action="/login.php" class="card mt-6 space-y-4" novalidate>
    <?= csrf_field() ?>

    <?php if ($error !== ''): ?>
    <p class="field-error"><?= e($error) ?></p>
    <?php endif; ?>

    <div>
      <label class="field-label" for="email">Email</label>
      <input class="field-input" type="email" id="email" name="email" required
             maxlength="150" value="<?= e($oldEmail) ?>" autocomplete="email">
    </div>

    <div>
      <label class="field-label" for="password">Password</label>
      <input class="field-input" type="password" id="password" name="password" required
             autocomplete="current-password">
    </div>

    <button type="submit" class="btn-primary w-full text-base py-3">Log in</button>

    <div class="text-center text-sm text-slate-600 space-y-1">
      <p><a href="/forgot-password.php" class="underline" style="color:var(--color-primary)">Forgot your password?</a></p>
      <p>New here? <a href="/signup.php" class="underline" style="color:var(--color-primary)">Start your free trial</a></p>
    </div>
  </form>
</section>

<?php include PROJECT_ROOT . '/includes/footer.php'; ?>

<?php
/**
 * Password reset request. ALWAYS answers with the same neutral message
 * whether or not the email exists (no user enumeration). Matching accounts
 * get a single-use token — stored as a sha256 hash, emailed in plaintext —
 * that expires after PASSWORD_RESET_TTL_MINUTES.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/mailer.php';

start_company_session();

$sent     = false;
$error    = '';
$oldEmail = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $oldEmail = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Rate limit silently: over-limit requests skip the work but still
        // show the neutral success, so the response never leaks anything.
        if (rate_limit('pw_reset', 5, 900)) {
            $stmt = db()->prepare('SELECT name, email FROM company_users WHERE email = ?');
            $stmt->execute([$oldEmail]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32)); // 64 hex chars, emailed once, only the hash is stored
                $stmt = db()->prepare(
                    'INSERT INTO password_resets (email, token_hash, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
                );
                $stmt->execute([$user['email'], hash('sha256', $token), PASSWORD_RESET_TTL_MINUTES]);

                // Best-effort: a mail failure still shows the neutral message.
                send_password_reset(
                    $user['email'],
                    $user['name'],
                    SITE_URL . '/reset-password.php?token=' . $token
                );
            }
        }
        $sent = true;
    }
}

$page_title       = 'Forgot password';
$page_description = 'Request a password reset link for your ' . APP_NAME . ' account.';
$canonical_path   = '/forgot-password.php';

include PROJECT_ROOT . '/includes/header.php';
?>

<section class="max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
  <h1 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">Forgot your password?</h1>

  <?php if ($sent): ?>
  <div class="card mt-8 text-center">
    <p class="text-sm text-slate-700">
      If that email matches an account, we&rsquo;ve sent a password reset link.
      Check your inbox — the link expires in <?= PASSWORD_RESET_TTL_MINUTES ?> minutes.
    </p>
    <p class="mt-4 text-sm">
      <a href="/login.php" class="underline" style="color:var(--color-primary)">Back to log in</a>
    </p>
  </div>
  <?php else: ?>
  <p class="mt-3 text-center text-slate-600">
    Enter your account email and we&rsquo;ll send you a link to reset it.
  </p>

  <form method="post" action="/forgot-password.php" class="card mt-6 space-y-4" novalidate>
    <?= csrf_field() ?>

    <?php if ($error !== ''): ?>
    <p class="field-error"><?= e($error) ?></p>
    <?php endif; ?>

    <div>
      <label class="field-label" for="email">Email</label>
      <input class="field-input" type="email" id="email" name="email" required
             maxlength="150" value="<?= e($oldEmail) ?>" autocomplete="email">
    </div>

    <button type="submit" class="btn-primary w-full text-base py-3">Send reset link</button>

    <p class="text-center text-sm text-slate-600">
      Remembered it? <a href="/login.php" class="underline" style="color:var(--color-primary)">Log in</a>
    </p>
  </form>
  <?php endif; ?>
</section>

<?php include PROJECT_ROOT . '/includes/footer.php'; ?>

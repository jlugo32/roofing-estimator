<?php
/**
 * Company registration. Creates the company + owner user via
 * register_company(), sends the welcome email (best-effort), auto-logs
 * the owner in, and lands them on the dashboard. Honors the platform
 * 'allow_signups' kill switch in platform_settings.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/mailer.php';

start_company_session();

// ── Platform kill switch: a platform_settings row 'allow_signups' = '0' closes signups ──
$stmt = db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
$stmt->execute(['allow_signups']);
$row = $stmt->fetch();
$signupsClosed = ($row && $row['setting_value'] === '0');

$errors = [];
$old = ['company_name' => '', 'owner_name' => '', 'email' => '', 'phone' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    if (!$signupsClosed) {
        $old['company_name'] = trim((string) ($_POST['company_name'] ?? ''));
        $old['owner_name']   = trim((string) ($_POST['owner_name'] ?? ''));
        $old['email']        = trim((string) ($_POST['email'] ?? ''));
        $old['phone']        = trim(preg_replace('/[^0-9+() .-]/', '', (string) ($_POST['phone'] ?? '')));
        $password        = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($old['company_name'] === '' || mb_strlen($old['company_name']) > 150) {
            $errors['company_name'] = 'Please enter your company name (150 characters max).';
        }
        if ($old['owner_name'] === '' || mb_strlen($old['owner_name']) > 100) {
            $errors['owner_name'] = 'Please enter your name (100 characters max).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (strlen(preg_replace('/\D/', '', $old['phone'])) < 10 || strlen($old['phone']) > 20) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }
        if (strlen($password) < 10) {
            $errors['password'] = 'Password must be at least 10 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
        if (empty($_POST['terms'])) {
            $errors['terms'] = 'Please agree to the terms to continue.';
        }

        if (!$errors) {
            try {
                // Company contact email = owner email at signup (editable later).
                $new = register_company(
                    $old['company_name'],
                    $old['email'],
                    $old['phone'],
                    $old['owner_name'],
                    $old['email'],
                    $password
                );

                // Best-effort: a mail failure must never block the signup.
                send_welcome_email(
                    ['name' => $old['company_name'], 'slug' => $new['slug']],
                    ['name' => $old['owner_name'], 'email' => $old['email']]
                );

                if (login_company_user($old['email'], $password)) {
                    redirect('/dashboard/');
                }
                redirect('/login.php'); // account created; fall back to manual login
            } catch (PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    $errors['email'] = 'An account with that email already exists — try logging in instead.';
                } else {
                    app_log('php-error.log', 'signup failed: ' . $e->getMessage());
                    $errors['general'] = 'Something went wrong creating your account. Please try again.';
                }
            } catch (Throwable $e) {
                app_log('php-error.log', 'signup failed: ' . $e->getMessage());
                $errors['general'] = 'Something went wrong creating your account. Please try again.';
            }
        }
    }
}

$page_title       = 'Start your free trial';
$page_description = 'Create your ' . APP_NAME . ' account and get your branded roofing estimator link. '
    . 'Free ' . TRIAL_LENGTH_DAYS . '-day trial, no credit card required.';
$canonical_path   = '/signup.php';
$hide_nav_cta     = true;

include PROJECT_ROOT . '/includes/header.php';
?>

<section class="max-w-lg mx-auto px-4 sm:px-6 py-12 sm:py-16">
  <h1 class="text-2xl sm:text-3xl font-bold text-center" style="color:var(--color-primary)">
    Start your <?= TRIAL_LENGTH_DAYS ?>-day free trial
  </h1>
  <p class="mt-3 text-center text-slate-600">No credit card required. Your branded estimator link in minutes.</p>

  <?php if ($signupsClosed): ?>
  <div class="card mt-8 text-center">
    <h2 class="text-lg font-semibold">Signups are temporarily closed</h2>
    <p class="mt-2 text-sm text-slate-600">
      We&rsquo;re not accepting new accounts right now. Please check back soon —
      existing customers can still <a href="/login.php" class="underline" style="color:var(--color-primary)">log in</a>.
    </p>
  </div>
  <?php else: ?>
  <form method="post" action="/signup.php" class="card mt-8 space-y-4" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($errors['general'])): ?>
    <p class="field-error"><?= e($errors['general']) ?></p>
    <?php endif; ?>

    <div>
      <label class="field-label" for="company_name">Company name</label>
      <input class="field-input" type="text" id="company_name" name="company_name" required
             maxlength="150" value="<?= e($old['company_name']) ?>" autocomplete="organization">
      <?php if (!empty($errors['company_name'])): ?><p class="field-error"><?= e($errors['company_name']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="field-label" for="owner_name">Your name</label>
      <input class="field-input" type="text" id="owner_name" name="owner_name" required
             maxlength="100" value="<?= e($old['owner_name']) ?>" autocomplete="name">
      <?php if (!empty($errors['owner_name'])): ?><p class="field-error"><?= e($errors['owner_name']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="field-label" for="email">Email</label>
      <input class="field-input" type="email" id="email" name="email" required
             maxlength="150" value="<?= e($old['email']) ?>" autocomplete="email">
      <?php if (!empty($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="field-label" for="phone">Phone</label>
      <input class="field-input" type="tel" id="phone" name="phone" required
             maxlength="20" value="<?= e($old['phone']) ?>" autocomplete="tel">
      <?php if (!empty($errors['phone'])): ?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="field-label" for="password">Password</label>
      <input class="field-input" type="password" id="password" name="password" required
             minlength="10" autocomplete="new-password">
      <p class="field-help">At least 10 characters.</p>
      <?php if (!empty($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="field-label" for="password_confirm">Confirm password</label>
      <input class="field-input" type="password" id="password_confirm" name="password_confirm" required
             minlength="10" autocomplete="new-password">
      <?php if (!empty($errors['password_confirm'])): ?><p class="field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="flex items-start gap-2 text-sm text-slate-600">
        <input type="checkbox" name="terms" value="1" class="mt-0.5">
        <span>I agree to the <?= e(APP_NAME) ?> terms of service and privacy policy.</span>
      </label>
      <?php if (!empty($errors['terms'])): ?><p class="field-error"><?= e($errors['terms']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn-primary w-full text-base py-3">Create my account</button>

    <p class="text-center text-sm text-slate-600">
      Already have an account?
      <a href="/login.php" class="underline" style="color:var(--color-primary)">Log in</a>
    </p>
  </form>
  <?php endif; ?>
</section>

<?php include PROJECT_ROOT . '/includes/footer.php'; ?>

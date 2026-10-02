<?php
/**
 * Super-admin login (platform owner console). Standalone page — does NOT use
 * the superadmin shell. Separate session cookie (SA_SESSID) from the company
 * dashboard, separate table (super_admins). Also handles ?logout=1.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow'); // never in search results

start_super_admin_session();

$logged_out = false;
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    start_super_admin_session(); // fresh session so the CSRF token below works
    session_regenerate_id(true);
    $logged_out = true;
}

if (!empty($_SESSION['super_admin_id'])) {
    redirect('/super-admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!rate_limit('sa_login', 10, 900)) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username !== '' && $password !== '' && login_super_admin($username, $password)) {
            redirect('/super-admin/index.php');
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Super Admin Login | <?= e(APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bg-violet-950 antialiased min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-sm">
  <div class="text-center mb-6">
    <p class="font-bold text-white text-lg"><?= e(APP_NAME) ?> &middot; Super Admin</p>
    <p class="text-sm text-violet-300 mt-1">Platform owner console</p>
  </div>

  <div class="card">
    <?php if ($logged_out): ?>
    <div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-3 py-2">
      You have been logged out.
    </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-3 py-2">
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/super-admin/login.php" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="field-label" for="username">Username</label>
        <input class="field-input" type="text" id="username" name="username" required autofocus autocomplete="username">
      </div>
      <div>
        <label class="field-label" for="password">Password</label>
        <input class="field-input" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary w-full">Log in</button>
    </form>
  </div>

  <p class="text-center text-xs text-violet-400 mt-6">Authorized platform staff only.</p>
</div>

</body>
</html>

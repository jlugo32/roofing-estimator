<?php
/**
 * Super-admin platform settings — whitelisted keys in platform_settings.
 * Platform-wide only: plan price (drives the MRR card + Stripe plan),
 * signup toggle, support email. Per-company PRICING lives in each
 * company's own dashboard (company_pricing_settings), not here.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$admin = require_super_admin();

$defaults = [
    'monthly_price_cents' => '9900',
    'allow_signups'       => '1',
    'support_email'       => '',
];

$errors = [];
$posted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $price_dollars = trim((string) ($_POST['monthly_price'] ?? ''));
    $allow_signups = (string) ($_POST['allow_signups'] ?? '');
    $support_email = trim((string) ($_POST['support_email'] ?? ''));

    if (!is_numeric($price_dollars) || (float) $price_dollars < 0) {
        $errors['monthly_price'] = 'Enter the monthly price in dollars, e.g. 99 or 99.00.';
    }
    if (!in_array($allow_signups, ['0', '1'], true)) {
        $errors['allow_signups'] = 'Invalid value.';
    }
    if ($support_email !== '' && !filter_var($support_email, FILTER_VALIDATE_EMAIL)) {
        $errors['support_email'] = 'Enter a valid email address, or leave blank.';
    }

    if (!$errors) {
        $values = [
            'monthly_price_cents' => (string) (int) round((float) $price_dollars * 100),
            'allow_signups'       => $allow_signups,
            'support_email'       => $support_email,
        ];
        $stmt = db()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($values as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        redirect('/super-admin/platform-settings.php?saved=1');
    }

    // Keep what was typed so the form repopulates alongside the errors.
    $posted = [
        'monthly_price' => $price_dollars,
        'allow_signups' => $allow_signups,
        'support_email' => $support_email,
    ];
}

// ── Current values (defaults where missing) ──
$settings = $defaults;
$stmt = db()->prepare('SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN (?, ?, ?)');
$stmt->execute(array_keys($defaults));
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$price_display = $posted['monthly_price']
    ?? number_format(((int) $settings['monthly_price_cents']) / 100, 2, '.', '');
$allow_display = $posted['allow_signups'] ?? $settings['allow_signups'];
$email_display = $posted['support_email'] ?? $settings['support_email'];
$saved         = isset($_GET['saved']);

$active     = 'settings';
$page_title = 'Platform Settings';
require PROJECT_ROOT . '/includes/superadmin-header.php';
?>

<h1 class="text-2xl font-bold mb-6">Platform Settings</h1>

<?php if ($saved): ?>
<div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-3 py-2">
  Settings saved.
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
  <div class="card lg:col-span-2">
    <form method="post" action="/super-admin/platform-settings.php" class="space-y-5">
      <?= csrf_field() ?>

      <div>
        <label class="field-label" for="monthly_price">Monthly plan price (USD)</label>
        <input class="field-input" type="text" inputmode="decimal" id="monthly_price" name="monthly_price"
               value="<?= e($price_display) ?>">
        <?php if (isset($errors['monthly_price'])): ?>
        <p class="field-error"><?= e($errors['monthly_price']) ?></p>
        <?php endif; ?>
        <p class="field-help">Stored as integer cents (<code>monthly_price_cents</code>). Drives the MRR card on the dashboard.</p>
      </div>

      <div>
        <label class="field-label" for="allow_signups">Allow new signups</label>
        <select class="field-input" id="allow_signups" name="allow_signups">
          <option value="1" <?= $allow_display === '1' ? 'selected' : '' ?>>Yes — signups open</option>
          <option value="0" <?= $allow_display === '0' ? 'selected' : '' ?>>No — signups closed</option>
        </select>
        <?php if (isset($errors['allow_signups'])): ?>
        <p class="field-error"><?= e($errors['allow_signups']) ?></p>
        <?php endif; ?>
        <p class="field-help">When set to No, signup.php refuses new company registrations.</p>
      </div>

      <div>
        <label class="field-label" for="support_email">Support email</label>
        <input class="field-input" type="email" id="support_email" name="support_email"
               value="<?= e($email_display) ?>" placeholder="support@example.com">
        <?php if (isset($errors['support_email'])): ?>
        <p class="field-error"><?= e($errors['support_email']) ?></p>
        <?php endif; ?>
        <p class="field-help">Informational — where platform support requests should go. Optional.</p>
      </div>

      <button type="submit" class="btn-primary">Save settings</button>
    </form>
  </div>

  <div class="card">
    <h2 class="font-semibold mb-2">About these settings</h2>
    <p class="text-sm text-slate-600">
      These are <strong>platform-wide</strong> settings that apply to every company on
      <?= e(APP_NAME) ?>. They control the subscription plan price, whether new companies
      can sign up, and the platform support contact.
    </p>
    <p class="text-sm text-slate-600 mt-3">
      Per-company <strong>roofing pricing</strong> (cost per square, surcharges, regional
      multipliers) lives in each company's own dashboard, not here — view it read-only on a
      company's detail page.
    </p>
  </div>
</div>

<?php require PROJECT_ROOT . '/includes/superadmin-footer.php'; ?>

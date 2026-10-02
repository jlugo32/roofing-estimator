<?php
/**
 * Pricing settings — the numbers behind this company's estimator.
 * ALL MONEY AS INTEGER CENTS in storage; the form shows dollars and
 * converts on save. Only keys present in default_pricing_seed() are
 * accepted (whitelist), each upserted scoped to the tenant.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$auth = require_company_auth(); // FIRST — tenant gate
$cid  = $auth['company_id'];

require_once PROJECT_ROOT . '/includes/estimator-engine.php';

$pdo = db();

$whitelist = array_keys(default_pricing_seed());

// ── POST: save every whitelisted key with type-appropriate conversion ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $stmt = $pdo->prepare(
        'INSERT INTO company_pricing_settings (company_id, setting_key, setting_value)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($whitelist as $key) {
        if (!isset($_POST[$key])) {
            continue; // missing input — keep the stored value
        }
        $raw = trim((string) $_POST[$key]);
        if ($raw === '') {
            continue;
        }

        if (str_ends_with($key, '_cents')) {
            // Dollars in the form → integer cents in storage.
            $value = (string) max(0, (int) round(floatval($raw) * 100));
        } elseif ($key === 'financing_default_term_months') {
            $value = (string) max(6, min(360, (int) $raw));
        } elseif ($key === 'financing_default_apr') {
            $value = (string) max(0.0, min(30.0, (float) $raw));
        } elseif (str_starts_with($key, 'regional_multiplier_')) {
            $value = (string) max(0.5, min(2.5, (float) $raw));
        } else { // *_pct surcharges & waste
            $value = (string) max(0.0, min(100.0, (float) $raw));
        }

        $stmt->execute([$cid, $key, $value]);
    }

    redirect('/dashboard/pricing-settings.php?saved=1');
}

$pricing = get_company_pricing($cid);

/** Cents value → "123.45" for a dollars number input. */
$dollars = static fn (string $key): string => number_format(((int) $pricing[$key]) / 100, 2, '.', '');

// Regional multiplier keys, sorted by state code.
$multiplierKeys = array_values(array_filter(
    $whitelist,
    static fn (string $k): bool => str_starts_with($k, 'regional_multiplier_')
));
sort($multiplierKeys);

$active     = 'pricing';
$page_title = 'Pricing Settings';
require PROJECT_ROOT . '/includes/dashboard-header.php';
?>

<h1 class="text-2xl font-bold mb-1">Pricing Settings</h1>
<p class="text-sm text-slate-500 mb-6">These numbers drive every estimate your estimator produces.</p>

<?php if (isset($_GET['saved'])): ?>
<div class="flash card mb-4 border-l-4 border-[var(--color-success)] text-sm">
  Pricing updated — changes are live on your estimator now.
</div>
<?php endif; ?>

<form method="post" class="space-y-4">
  <?= csrf_field() ?>

  <!-- Cost per square by material -->
  <div class="card">
    <h2 class="font-semibold mb-1">Cost per square (100 sq ft) by material</h2>
    <p class="field-help mb-4">Installed price per roofing square, in dollars — materials and labor combined.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach (ROOF_MATERIALS as $mkey => $label): $skey = 'cost_per_square_' . $mkey . '_cents'; ?>
      <div>
        <label for="<?= e($skey) ?>" class="field-label"><?= e($label) ?></label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
          <input id="<?= e($skey) ?>" name="<?= e($skey) ?>" type="number" step="0.01" min="0"
                 value="<?= e($dollars($skey)) ?>" class="field-input !pl-7">
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Flat fees -->
  <div class="card">
    <h2 class="font-semibold mb-4">Flat fees</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="tear_off_flat_fee_cents" class="field-label">Tear-off fee</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
          <input id="tear_off_flat_fee_cents" name="tear_off_flat_fee_cents" type="number" step="0.01" min="0"
                 value="<?= e($dollars('tear_off_flat_fee_cents')) ?>" class="field-input !pl-7">
        </div>
        <p class="field-help">Added when the old roof needs removal (or the homeowner isn't sure).</p>
      </div>
      <div>
        <label for="permit_fee_flat_cents" class="field-label">Permit fee</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
          <input id="permit_fee_flat_cents" name="permit_fee_flat_cents" type="number" step="0.01" min="0"
                 value="<?= e($dollars('permit_fee_flat_cents')) ?>" class="field-input !pl-7">
        </div>
        <p class="field-help">Added to every estimate.</p>
      </div>
    </div>
  </div>

  <!-- Surcharges & waste -->
  <div class="card">
    <h2 class="font-semibold mb-4">Surcharges &amp; waste</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <label for="steep_pitch_surcharge_pct" class="field-label">Steep pitch surcharge (%)</label>
        <input id="steep_pitch_surcharge_pct" name="steep_pitch_surcharge_pct" type="number" step="0.1" min="0" max="100"
               value="<?= e((string) $pricing['steep_pitch_surcharge_pct']) ?>" class="field-input">
      </div>
      <div>
        <label for="multi_story_surcharge_pct" class="field-label">Multi-story surcharge (%)</label>
        <input id="multi_story_surcharge_pct" name="multi_story_surcharge_pct" type="number" step="0.1" min="0" max="100"
               value="<?= e((string) $pricing['multi_story_surcharge_pct']) ?>" class="field-input">
      </div>
      <div>
        <label for="waste_factor_pct" class="field-label">Waste factor (%)</label>
        <input id="waste_factor_pct" name="waste_factor_pct" type="number" step="0.1" min="0" max="100"
               value="<?= e((string) $pricing['waste_factor_pct']) ?>" class="field-input">
        <p class="field-help">Extra material to cover cuts and overlap.</p>
      </div>
    </div>
  </div>

  <!-- Financing -->
  <div class="card">
    <h2 class="font-semibold mb-4">Financing</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="financing_default_apr" class="field-label">Default APR (%)</label>
        <input id="financing_default_apr" name="financing_default_apr" type="number" step="0.01" min="0" max="30"
               value="<?= e((string) $pricing['financing_default_apr']) ?>" class="field-input">
      </div>
      <div>
        <label for="financing_default_term_months" class="field-label">Default term (months)</label>
        <input id="financing_default_term_months" name="financing_default_term_months" type="number" step="1" min="6" max="360"
               value="<?= e((string) $pricing['financing_default_term_months']) ?>" class="field-input">
      </div>
    </div>
    <p class="field-help mt-2">Used for the "as low as $X/mo" teaser on estimate results.</p>
  </div>

  <!-- Regional multipliers (collapsible) -->
  <div class="card" x-data="{ open: false }">
    <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
      <span>
        <span class="font-semibold block">Regional multipliers by state</span>
        <span class="field-help">Cost-of-labor adjustment applied by the homeowner's state (1.00 = no change).</span>
      </span>
      <span class="text-sm font-medium text-[var(--color-primary)] whitespace-nowrap ml-4"
            x-text="open ? 'Hide' : 'Show'">Show</span>
    </button>
    <div x-show="open" x-cloak class="grid grid-cols-3 lg:grid-cols-6 gap-3 mt-4">
      <?php foreach ($multiplierKeys as $mkey): $state = substr($mkey, strlen('regional_multiplier_')); ?>
      <div>
        <label for="<?= e($mkey) ?>" class="field-label text-center"><?= e($state) ?></label>
        <input id="<?= e($mkey) ?>" name="<?= e($mkey) ?>" type="number" step="0.01" min="0.5" max="2.5"
               value="<?= e((string) $pricing[$mkey]) ?>" class="field-input text-center !px-2">
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="flex justify-end">
    <button type="submit" class="btn-primary">Save pricing</button>
  </div>
</form>

<?php require PROJECT_ROOT . '/includes/dashboard-footer.php'; ?>

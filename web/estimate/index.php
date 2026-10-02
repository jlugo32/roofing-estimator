<?php
/**
 * PUBLIC estimator wizard — the company-branded, 5-step lead-gen funnel.
 * Reached via vhost rewrite /estimate/{slug} → estimate/index.php?slug=...
 *
 * Standalone shell (NOT the platform header) branded with the company row.
 * All option lists render from the estimator-engine constants — single
 * source of truth, never duplicated in JS. Answers are collected client-side
 * by /assets/js/estimator.js and POSTed to /api/public/estimate.php.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/estimator-engine.php';

// ── Resolve tenant from slug ──
$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$company = null;
if (preg_match('/^[a-z0-9-]{1,100}$/', $slug)) {
    $company = get_company_by_slug($slug);
}
if (!$company || in_array($company['subscription_status'], ['suspended', 'cancelled'], true)) {
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Estimator not available | <?= e(APP_NAME) ?></title>
</head>
<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem;text-align:center;color:#1f2937">
<h1 style="font-size:1.4rem">This estimator is not available</h1>
<p style="color:#6b7280">The estimate page you're looking for doesn't exist or is no longer active.
Please double-check the link, or contact the roofing company directly.</p>
<p style="margin-top:2rem;font-size:.85rem;color:#9ca3af">Powered by <a href="<?= e(SITE_URL) ?>" style="color:#1e3a5f"><?= e(APP_NAME) ?></a></p>
</body>
</html><?php
    exit;
}

// ── Page metadata ──
$company_name = (string) $company['name'];
$service_area = trim((string) ($company['service_area'] ?? ''));
$page_title   = $company_name . ' — Instant Roof Estimate | ' . APP_NAME;
$meta_desc    = 'Get an instant roof replacement estimate from ' . $company_name
    . ($service_area !== '' ? ', serving ' . $service_area : '')
    . '. Free, takes about a minute, no obligation.';
$canonical    = SITE_URL . '/estimate/' . $slug;

$phone      = trim((string) ($company['contact_phone'] ?? ''));
$phone_href = 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
$logo_path  = trim((string) ($company['logo_path'] ?? ''));
$license    = trim((string) ($company['license_number'] ?? ''));

// ── Display labels for engine constants (keys stay the single source of truth) ──
$state_names = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
    'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
    'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
    'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
    'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
    'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
    'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
    'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
    'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
];

$pitch_meta = [
    'low'    => ['Low', 'Walkable, gentle slope'],
    'medium' => ['Medium', 'Moderate slope'],
    'steep'  => ['Steep', 'Hard to walk on'],
    'unsure' => ['Not sure', "We'll estimate it"],
];
$condition_labels = [
    'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor',
    'leaking' => 'Leaking', 'unknown' => 'Not sure',
];
$tear_off_labels = ['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Not sure'];

$wizard_steps = [1 => 'Location', 2 => 'Home', 3 => 'Roof', 4 => 'Estimate', 5 => 'Results'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($company_name) ?> — Instant Roof Estimate">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col text-gray-800">

<!-- ── Branded company header ── -->
<header class="bg-white border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <?php if ($logo_path !== ''): ?>
            <img src="<?= e($logo_path) ?>" alt="<?= e($company_name) ?> logo" class="h-10 w-10 rounded object-contain shrink-0">
            <?php else: ?>
            <svg class="h-9 w-9 shrink-0" style="color: var(--color-primary)" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9h13v-9"/><path d="M10 19v-5h4v5"/>
            </svg>
            <?php endif; ?>
            <div class="min-w-0">
                <p class="font-bold leading-tight truncate" style="color: var(--color-primary)"><?= e($company_name) ?></p>
                <?php if ($license !== ''): ?>
                <p class="text-xs text-gray-500 truncate">Licensed: <?= e($license) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($phone !== ''): ?>
        <a href="<?= e($phone_href) ?>" class="shrink-0 text-sm font-semibold whitespace-nowrap" style="color: var(--color-accent)">
            <?= e($phone) ?>
        </a>
        <?php endif; ?>
    </div>
</header>

<main class="flex-1 w-full max-w-xl mx-auto px-4 py-6 sm:py-10">
    <h1 class="text-xl sm:text-2xl font-bold text-center mb-1" style="color: var(--color-primary)">
        Get your instant roof estimate
    </h1>
    <p class="text-sm text-gray-500 text-center mb-6">Answer a few quick questions — no obligation.</p>

    <!-- ── Progress dots ── -->
    <div class="flex items-start justify-between mb-6" id="wizard-progress" aria-hidden="true">
        <?php foreach ($wizard_steps as $n => $label): ?>
        <div class="flex flex-col items-center flex-1">
            <div class="wizard-step-dot<?= $n === 1 ? ' is-active' : '' ?>" data-step-dot="<?= $n ?>"><?= $n ?></div>
            <span class="text-[0.65rem] mt-1 text-gray-500"><?= e($label) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card sm:p-6" id="wizard-card">

        <!-- ── Step 1: Location ── -->
        <section id="step-1" class="wizard-panel" data-step="1">
            <h2 class="text-lg font-bold mb-4">Where is the home?</h2>
            <div class="mb-4">
                <label class="field-label" for="f-zip">ZIP code</label>
                <input class="field-input" id="f-zip" type="text" inputmode="numeric" autocomplete="postal-code"
                       maxlength="5" pattern="\d{5}" placeholder="e.g. 30301">
                <div class="field-error hidden" data-error-for="zip_code"></div>
            </div>
            <div class="mb-5">
                <label class="field-label" for="f-state">State</label>
                <select class="field-input" id="f-state" autocomplete="address-level1">
                    <option value="">Select your state…</option>
                    <?php foreach ($state_names as $code => $name): ?>
                    <option value="<?= e($code) ?>"><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-error hidden" data-error-for="state"></div>
            </div>
            <div class="field-error hidden mb-3" id="step-1-error" role="alert"></div>
            <button type="button" class="btn-primary w-full py-3 text-base" data-next="2">Continue</button>
        </section>

        <!-- ── Step 2: Home details ── -->
        <section id="step-2" class="wizard-panel hidden" data-step="2">
            <h2 class="text-lg font-bold mb-4">Tell us about the home</h2>
            <div class="mb-4">
                <label class="field-label" for="f-sqft">Home square footage</label>
                <input class="field-input" id="f-sqft" type="number" inputmode="numeric" min="200" max="20000"
                       placeholder="e.g. 1800">
                <p class="field-help">Total finished living area</p>
                <div class="field-error hidden" data-error-for="home_sqft"></div>
            </div>
            <div class="mb-5">
                <span class="field-label">How many stories?</span>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" class="option-tile py-4" data-group="stories" data-value="1">1</button>
                    <button type="button" class="option-tile py-4" data-group="stories" data-value="2">2</button>
                    <button type="button" class="option-tile py-4" data-group="stories" data-value="3">3+</button>
                </div>
                <div class="field-error hidden" data-error-for="stories"></div>
            </div>
            <div class="field-error hidden mb-3" id="step-2-error" role="alert"></div>
            <div class="flex gap-2">
                <button type="button" class="btn-outline flex-none px-4 py-3" data-back="1">Back</button>
                <button type="button" class="btn-primary flex-1 py-3 text-base" data-next="3">Continue</button>
            </div>
        </section>

        <!-- ── Step 3: Roof details ── -->
        <section id="step-3" class="wizard-panel hidden" data-step="3">
            <h2 class="text-lg font-bold mb-4">About your roof</h2>

            <div class="mb-4">
                <label class="field-label" for="f-material-current">Current roof material</label>
                <select class="field-input" id="f-material-current">
                    <?php foreach (ROOF_MATERIALS as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                    <option value="unknown" selected>Not sure</option>
                </select>
            </div>

            <div class="mb-4">
                <span class="field-label">What material do you want?</span>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach (ROOF_MATERIALS as $key => $label): ?>
                    <button type="button" class="option-tile" data-group="roof_material_desired"
                            data-value="<?= e($key) ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="field-error hidden" data-error-for="roof_material_desired"></div>
            </div>

            <div class="mb-4">
                <label class="field-label" for="f-roof-age">Roof age in years <span class="font-normal text-gray-400">(optional)</span></label>
                <input class="field-input" id="f-roof-age" type="number" inputmode="numeric" min="0" max="120" placeholder="e.g. 15">
                <div class="field-error hidden" data-error-for="roof_age_years"></div>
            </div>

            <div class="mb-4">
                <span class="field-label">Current condition</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <?php foreach (ROOF_CONDITIONS as $key): ?>
                    <button type="button" class="option-tile" data-group="roof_condition"
                            data-value="<?= e($key) ?>"><?= e($condition_labels[$key] ?? ucfirst($key)) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="field-error hidden" data-error-for="roof_condition"></div>
            </div>

            <div class="mb-4">
                <span class="field-label">Roof pitch (steepness)</span>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach (ROOF_PITCHES as $key): $meta = $pitch_meta[$key] ?? [ucfirst($key), '']; ?>
                    <button type="button" class="option-tile" data-group="pitch" data-value="<?= e($key) ?>">
                        <span class="block font-semibold"><?= e($meta[0]) ?></span>
                        <?php if ($meta[1] !== ''): ?>
                        <span class="block text-xs text-gray-500 font-normal mt-0.5"><?= e($meta[1]) ?></span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="field-error hidden" data-error-for="pitch"></div>
            </div>

            <div class="mb-5">
                <span class="field-label">Does the old roof need tearing off?</span>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach (TEAR_OFF_OPTIONS as $key): ?>
                    <button type="button" class="option-tile" data-group="tear_off"
                            data-value="<?= e($key) ?>"><?= e($tear_off_labels[$key] ?? ucfirst($key)) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="field-error hidden" data-error-for="tear_off"></div>
            </div>

            <div class="field-error hidden mb-3" id="step-3-error" role="alert"></div>
            <div class="flex gap-2">
                <button type="button" class="btn-outline flex-none px-4 py-3" data-back="2">Back</button>
                <button type="button" class="btn-primary flex-1 py-3 text-base" data-next="4">See my estimate</button>
            </div>
        </section>

        <!-- ── Step 4: Teaser (blurred price, unlock CTA) ── -->
        <section id="step-4" class="wizard-panel hidden" data-step="4">
            <div id="teaser-loading" class="py-10 text-center text-gray-500">Calculating your estimate…</div>

            <div id="teaser-error" class="hidden py-6 text-center">
                <p class="field-error mb-3" id="teaser-error-msg" role="alert"></p>
                <button type="button" class="btn-outline" id="teaser-retry">Try again</button>
            </div>

            <div id="teaser-content" class="hidden">
                <h2 class="text-lg font-bold text-center mb-2">Your estimate is ready</h2>
                <p class="text-center text-gray-600 mb-5">
                    Your roof is approximately <strong id="teaser-sqft">—</strong> sq ft
                </p>
                <div class="relative rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 mb-5 overflow-hidden">
                    <div class="teaser-blur text-center" aria-hidden="true">
                        <p class="text-3xl font-extrabold" id="teaser-range">$18,400 – $23,500</p>
                        <p class="text-sm text-gray-600 mt-2">from <span id="teaser-monthly">$212</span>/mo with financing</p>
                    </div>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <svg class="h-7 w-7 mb-1" style="color: var(--color-primary)" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                        </svg>
                        <p class="text-sm font-semibold" style="color: var(--color-primary)">Unlock your full price range</p>
                    </div>
                </div>
                <button type="button" class="btn-primary w-full py-3 text-base" data-next="5">Get my full estimate &rarr;</button>
                <p class="text-xs text-gray-500 text-center mt-2">Takes 10 seconds — instant results</p>
            </div>

            <div class="mt-4">
                <button type="button" class="btn-outline px-4 py-2 text-sm" data-back="3">Back</button>
            </div>
        </section>

        <!-- ── Step 5: Lead capture ── -->
        <section id="step-5" class="wizard-panel hidden" data-step="5">
            <h2 class="text-lg font-bold mb-1">Where should we send your estimate?</h2>
            <p class="text-sm text-gray-500 mb-4">Instant results — no waiting.</p>
            <form id="lead-form" novalidate>
                <div class="mb-4">
                    <label class="field-label" for="f-name">Full name</label>
                    <input class="field-input" id="f-name" type="text" autocomplete="name" maxlength="100" placeholder="Jane Homeowner">
                    <div class="field-error hidden" data-error-for="name"></div>
                </div>
                <div class="mb-4">
                    <label class="field-label" for="f-email">Email</label>
                    <input class="field-input" id="f-email" type="email" autocomplete="email" maxlength="150" placeholder="you@example.com">
                    <div class="field-error hidden" data-error-for="email"></div>
                </div>
                <div class="mb-4">
                    <label class="field-label" for="f-phone">Phone</label>
                    <input class="field-input" id="f-phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="(555) 123-4567">
                    <div class="field-error hidden" data-error-for="phone"></div>
                </div>
                <div class="mb-4">
                    <label class="field-label" for="f-address">Street address <span class="font-normal text-gray-400">(optional)</span></label>
                    <input class="field-input" id="f-address" type="text" autocomplete="street-address" maxlength="255" placeholder="123 Main St">
                </div>

                <div class="hp-wrap" aria-hidden="true">
                    <label for="f-hp">Reference code</label>
                    <input type="text" id="f-hp" name="<?= e(HONEYPOT_FIELD) ?>" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="mb-4">
                    <label class="flex items-start gap-2 text-sm text-gray-600">
                        <input type="checkbox" id="f-consent" class="mt-1 h-4 w-4 shrink-0">
                        <span>I agree that <?= e($company_name) ?> may contact me by phone, text, or email about
                        my roofing project. Consent is not a condition of purchase.</span>
                    </label>
                    <div class="field-error hidden" data-error-for="consent_tcpa"></div>
                </div>

                <div class="field-error hidden mb-3" id="step-5-error" role="alert"></div>
                <div class="flex gap-2">
                    <button type="button" class="btn-outline flex-none px-4 py-3" data-back="4">Back</button>
                    <button type="submit" class="btn-primary flex-1 py-3 text-base" id="submit-btn">Show my estimate</button>
                </div>
            </form>
        </section>

        <!-- ── Success panel (replaces the wizard after submit) ── -->
        <section id="panel-success" class="hidden text-center">
            <h2 class="text-lg font-bold mb-1" style="color: var(--color-success)">Your estimate is ready</h2>
            <p class="text-sm text-gray-500 mb-4">Based on a roof of about <span id="success-sqft">—</span> sq ft</p>
            <p class="text-3xl sm:text-4xl font-extrabold mb-2" style="color: var(--color-primary)" id="success-range">—</p>
            <p class="text-sm text-gray-600 mb-6 hidden" id="success-financing">
                or from <strong id="success-monthly">—</strong>/mo with financing
            </p>
            <div class="text-left bg-gray-50 rounded-lg p-4 mb-5">
                <p class="font-semibold mb-2">What happens next</p>
                <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                    <li><?= e($company_name) ?> will reach out to you shortly</li>
                    <li>They'll schedule a free roof inspection</li>
                    <li>You'll receive a final, exact quote</li>
                </ul>
            </div>
            <a href="#" id="success-results-link" class="block underline text-sm mb-4" style="color: var(--color-primary)">
                View / save your full estimate
            </a>
            <?php if ($phone !== ''): ?>
            <a href="<?= e($phone_href) ?>" class="btn-primary w-full py-3 text-base">Call <?= e($company_name) ?> now</a>
            <?php endif; ?>
        </section>
    </div>
</main>

<footer class="py-6 text-center text-xs text-gray-400">
    Powered by <a href="<?= e(SITE_URL) ?>" class="underline hover:text-gray-600"><?= e(APP_NAME) ?></a>
</footer>

<script>
window.ESTIMATOR = <?= json_encode([
    'slug'          => $slug,
    'honeypotField' => HONEYPOT_FIELD,
    'apiUrl'        => '/api/public/estimate.php',
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/assets/js/estimator.js" defer></script>
</body>
</html>

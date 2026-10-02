<?php
/**
 * PUBLIC gated results page — /estimate/results.php?token=<32 hex chars>.
 *
 * The unguessable results_token (from a wizard submission) is the only
 * credential. Shows the homeowner their saved estimate under the company's
 * branding. Contains personal data → noindex via BOTH response header and
 * meta tag; invalid or unknown tokens get a friendly 404.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/estimator-engine.php';

header('X-Robots-Tag: noindex, nofollow');

// ── Look up the estimate by token (format gate first, then prepared query) ──
$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$row = null;
if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt = db()->prepare(
        'SELECT e.home_sqft, e.roof_sqft_estimate, e.stories, e.roof_material_desired,
                e.roof_age_years, e.roof_condition, e.pitch, e.tear_off_needed,
                e.estimate_low_cents, e.estimate_high_cents, e.financing_monthly_cents,
                e.created_at,
                c.name AS client_name,
                co.name AS company_name, co.logo_path, co.contact_phone, co.license_number
         FROM estimates e
         JOIN clients c ON c.id = e.client_id
         JOIN companies co ON co.id = e.company_id
         WHERE e.results_token = ?'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch() ?: null;
}

if (!$row) {
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Estimate not found | <?= e(APP_NAME) ?></title>
</head>
<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem;text-align:center;color:#1f2937">
<h1 style="font-size:1.4rem">Estimate not found</h1>
<p style="color:#6b7280">We couldn't find an estimate for this link. It may have been mistyped —
please use the exact link from your email, or contact the roofing company for a new one.</p>
<p style="margin-top:2rem;font-size:.85rem;color:#9ca3af">Powered by <a href="<?= e(SITE_URL) ?>" style="color:#1e3a5f"><?= e(APP_NAME) ?></a></p>
</body>
</html><?php
    exit;
}

// ── Presentation values ──
$company_name = (string) $row['company_name'];
$phone        = trim((string) ($row['contact_phone'] ?? ''));
$phone_href   = 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
$logo_path    = trim((string) ($row['logo_path'] ?? ''));
$license      = trim((string) ($row['license_number'] ?? ''));

$first_name = explode(' ', trim((string) $row['client_name']))[0] ?: 'there';

$material_label = ROOF_MATERIALS[$row['roof_material_desired']] ?? ucfirst((string) $row['roof_material_desired']);
$pitch_labels    = ['low' => 'Low', 'medium' => 'Medium', 'steep' => 'Steep', 'unsure' => 'Not sure'];
$tear_off_labels = ['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Not sure'];
$pitch_label     = $pitch_labels[$row['pitch']] ?? ucfirst((string) $row['pitch']);
$tear_off_label  = $tear_off_labels[$row['tear_off_needed']] ?? ucfirst((string) $row['tear_off_needed']);

$financing_cents = (int) ($row['financing_monthly_cents'] ?? 0);

$details = [
    'Estimated roof size' => number_format((int) $row['roof_sqft_estimate']) . ' sq ft',
    'Desired material'    => $material_label,
    'Stories'             => (string) (int) $row['stories'],
    'Roof pitch'          => $pitch_label,
    'Tear-off needed'     => $tear_off_label,
    'Home size'           => number_format((int) $row['home_sqft']) . ' sq ft',
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($company_name) ?> — Your Roof Estimate | <?= e(APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col text-gray-800">

<!-- ── Branded company header (mirrors the wizard) ── -->
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
    <div class="card sm:p-6">
        <p class="text-sm text-gray-500 mb-1">Hi <?= e($first_name) ?>, here's your saved estimate</p>
        <h1 class="text-lg font-bold mb-4" style="color: var(--color-primary)">
            Your roof replacement estimate
        </h1>

        <p class="text-3xl sm:text-4xl font-extrabold mb-1" style="color: var(--color-primary)">
            <?= e(money((int) $row['estimate_low_cents'])) ?> &ndash; <?= e(money((int) $row['estimate_high_cents'])) ?>
        </p>
        <?php if ($financing_cents > 0): ?>
        <p class="text-sm text-gray-600 mb-5">
            or from <strong><?= e(money($financing_cents)) ?>/mo</strong> (estimate) with financing
        </p>
        <?php else: ?>
        <div class="mb-5"></div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-3 bg-gray-50 rounded-lg p-4 mb-5">
            <?php foreach ($details as $label => $value): ?>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400"><?= e($label) ?></p>
                <p class="text-sm font-semibold"><?= e($value) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="text-xs text-gray-500 mb-5">
            This is a preliminary estimate range based on the details you provided.
            Your final price will be confirmed by <?= e($company_name) ?> after a free
            on-site roof inspection.
        </p>

        <div class="text-left bg-gray-50 rounded-lg p-4 mb-5">
            <p class="font-semibold mb-2">What happens next</p>
            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                <li><?= e($company_name) ?> will reach out to you shortly</li>
                <li>They'll schedule a free roof inspection</li>
                <li>You'll receive a final, exact quote</li>
            </ul>
        </div>

        <?php if ($phone !== ''): ?>
        <a href="<?= e($phone_href) ?>" class="btn-primary block w-full py-3 text-base">
            Call <?= e($company_name) ?> now &middot; <?= e($phone) ?>
        </a>
        <?php endif; ?>
    </div>
</main>

<footer class="py-6 text-center text-xs text-gray-400">
    Powered by <a href="<?= e(SITE_URL) ?>" class="underline hover:text-gray-600"><?= e(APP_NAME) ?></a>
</footer>

</body>
</html>

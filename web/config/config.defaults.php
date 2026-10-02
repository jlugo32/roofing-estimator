<?php
/**
 * Non-secret platform constants. This file DEPLOYS normally.
 * Secrets (DB, SMTP, JWT, Stripe) live in config/config.php — DEPLOY-EXCLUDED.
 *
 * RENAME BEFORE LAUNCH: APP_NAME and SITE_URL below are placeholders.
 * See docs/SETUP.md → "RENAME BEFORE LAUNCH".
 */

declare(strict_types=1);

// ── Placeholder identity (single source of truth — never hardcode elsewhere) ──
if (!defined('APP_NAME')) {
    define('APP_NAME', 'TEMP_APP_NAME');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://PLACEHOLDER-DOMAIN.com');
}

// ── Platform behavior ──
define('TRIAL_LENGTH_DAYS', 14);
define('API_TOKEN_TTL_DAYS', 30);
define('PASSWORD_RESET_TTL_MINUTES', 60);

// ── Rate limits (per IP) ──
define('RATE_LIMIT_PUBLIC_ESTIMATE_MAX', 10);      // submissions
define('RATE_LIMIT_PUBLIC_ESTIMATE_WINDOW', 3600); // seconds
define('RATE_LIMIT_API_LOGIN_MAX', 8);             // attempts
define('RATE_LIMIT_API_LOGIN_WINDOW', 900);        // seconds
define('RATE_LIMIT_WEB_LOGIN_MAX', 10);
define('RATE_LIMIT_WEB_LOGIN_WINDOW', 900);

/**
 * Honeypot field name on the public estimator lead form.
 * MUST stay autofill-INERT: never rename to website/company/url or any
 * label Chrome autofill recognizes — autofill silently filling it kills
 * real leads. Hidden via off-screen positioning, NOT display:none.
 */
define('HONEYPOT_FIELD', 'hp_ref_code_x9');

/**
 * Default pricing seeded into company_pricing_settings on company signup.
 * ALL MONEY AS INTEGER CENTS. Values are strings because setting_value is
 * VARCHAR — cast where consumed (estimator-engine.php).
 */
function default_pricing_seed(): array
{
    $seed = [
        'cost_per_square_asphalt_3tab_cents'         => '42500',
        'cost_per_square_asphalt_architectural_cents' => '47500',
        'cost_per_square_metal_cents'                => '95000',
        'cost_per_square_tile_cents'                 => '120000',
        'cost_per_square_flat_tpo_cents'             => '65000',
        'cost_per_square_slate_cents'                => '150000',
        'tear_off_flat_fee_cents'                    => '125000',
        'permit_fee_flat_cents'                      => '50000',
        'steep_pitch_surcharge_pct'                  => '15',
        'multi_story_surcharge_pct'                  => '10',
        'waste_factor_pct'                           => '10',
        'financing_default_apr'                      => '7.99',
        'financing_default_term_months'              => '120',
    ];

    // Regional cost-of-labor multipliers, one row per state (editable per company).
    $multipliers = [
        'AL' => '0.88', 'AK' => '1.22', 'AZ' => '0.97', 'AR' => '0.87', 'CA' => '1.28',
        'CO' => '1.06', 'CT' => '1.15', 'DE' => '1.04', 'DC' => '1.20', 'FL' => '1.02',
        'GA' => '0.93', 'HI' => '1.35', 'ID' => '0.95', 'IL' => '1.06', 'IN' => '0.92',
        'IA' => '0.90', 'KS' => '0.89', 'KY' => '0.90', 'LA' => '0.92', 'ME' => '1.02',
        'MD' => '1.10', 'MA' => '1.20', 'MI' => '0.96', 'MN' => '1.02', 'MS' => '0.85',
        'MO' => '0.91', 'MT' => '0.98', 'NE' => '0.90', 'NV' => '1.03', 'NH' => '1.06',
        'NJ' => '1.16', 'NM' => '0.92', 'NY' => '1.22', 'NC' => '0.93', 'ND' => '0.94',
        'OH' => '0.93', 'OK' => '0.87', 'OR' => '1.08', 'PA' => '1.02', 'RI' => '1.12',
        'SC' => '0.92', 'SD' => '0.91', 'TN' => '0.91', 'TX' => '0.95', 'UT' => '0.99',
        'VT' => '1.04', 'VA' => '1.02', 'WA' => '1.12', 'WV' => '0.88', 'WI' => '0.97',
        'WY' => '0.96',
    ];
    foreach ($multipliers as $state => $mult) {
        $seed['regional_multiplier_' . $state] = $mult;
    }

    return $seed;
}

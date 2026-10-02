<?php
/**
 * Estimate calculation engine. ALL MONEY AS INTEGER CENTS.
 *
 * Pricing is loaded per-company from company_pricing_settings — the same
 * engine serves every tenant, but each tenant's numbers are their own.
 * Callers pass a company_id they obtained from an auth gatekeeper or a
 * public slug lookup — never from client-supplied input.
 */

declare(strict_types=1);

const ROOF_MATERIALS = [
    'asphalt_3tab'          => 'Asphalt Shingles (3-Tab)',
    'asphalt_architectural' => 'Asphalt Shingles (Architectural)',
    'metal'                 => 'Metal Roofing',
    'tile'                  => 'Tile Roofing',
    'flat_tpo'              => 'Flat / TPO Membrane',
    'slate'                 => 'Slate Roofing',
];

const ROOF_PITCHES     = ['low', 'medium', 'steep', 'unsure'];
const ROOF_CONDITIONS  = ['good', 'fair', 'poor', 'leaking', 'unknown'];
const TEAR_OFF_OPTIONS = ['yes', 'no', 'unsure'];

/**
 * Company's pricing settings merged over platform defaults, so a missing
 * row can never break a calculation. Returns key => string value.
 */
function get_company_pricing(int $companyId): array
{
    $stmt = db()->prepare(
        'SELECT setting_key, setting_value FROM company_pricing_settings WHERE company_id = ?'
    );
    $stmt->execute([$companyId]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_merge(default_pricing_seed(), $rows);
}

/**
 * Core calculation.
 *
 * $input keys (already validated by caller):
 *   home_sqft int, stories int(1-3), roof_material_current string,
 *   roof_material_desired string (key of ROOF_MATERIALS), roof_age_years ?int,
 *   roof_condition string, pitch string, tear_off string, state string(2)
 *
 * Returns: roof_sqft_estimate, squares, estimate_low_cents,
 *          estimate_high_cents, financing_monthly_cents, financing_apr,
 *          financing_term_months
 */
function calculate_estimate(int $companyId, array $input): array
{
    $p = get_company_pricing($companyId);

    $homeSqft = max(200, min(20000, (int) $input['home_sqft']));
    $stories  = max(1, min(3, (int) $input['stories']));
    $pitch    = in_array($input['pitch'] ?? '', ROOF_PITCHES, true) ? $input['pitch'] : 'unsure';
    $tearOff  = in_array($input['tear_off'] ?? '', TEAR_OFF_OPTIONS, true) ? $input['tear_off'] : 'unsure';
    $material = array_key_exists($input['roof_material_desired'] ?? '', ROOF_MATERIALS)
        ? $input['roof_material_desired'] : 'asphalt_architectural';
    $state    = strtoupper(substr((string) ($input['state'] ?? ''), 0, 2));

    // Footprint → roof surface. Pitch steepens surface area; 10% covers overhangs/eaves.
    $pitchFactor = ['low' => 1.05, 'medium' => 1.15, 'steep' => 1.35, 'unsure' => 1.15][$pitch];
    $footprint   = $homeSqft / $stories;
    $roofSqft    = (int) round($footprint * $pitchFactor * 1.10);

    // Roofing is priced per "square" (100 sq ft), padded by the waste factor.
    $wastePct = (float) ($p['waste_factor_pct'] ?? 10);
    $squares  = (int) ceil(($roofSqft / 100) * (1 + $wastePct / 100));

    $costPerSquare = (int) ($p['cost_per_square_' . $material . '_cents'] ?? 47500);
    $base = $squares * $costPerSquare;

    // Percentage surcharges apply to the material+labor base.
    $surchargePct = 0.0;
    if ($pitch === 'steep') {
        $surchargePct += (float) ($p['steep_pitch_surcharge_pct'] ?? 15);
    }
    if ($stories >= 2) {
        $surchargePct += (float) ($p['multi_story_surcharge_pct'] ?? 10);
    }
    $subtotal = (int) round($base * (1 + $surchargePct / 100));

    // Flat adders: tear-off (when needed or unknown) and permits.
    if ($tearOff !== 'no') {
        $subtotal += (int) ($p['tear_off_flat_fee_cents'] ?? 125000);
    }
    $subtotal += (int) ($p['permit_fee_flat_cents'] ?? 50000);

    // Regional labor/cost multiplier for the client's state.
    $multiplier = (float) ($p['regional_multiplier_' . $state] ?? 1.0);
    $total = (int) round($subtotal * $multiplier);

    $low  = (int) round($total * 0.90);
    $high = (int) round($total * 1.15);

    // Financing teaser: standard amortized payment on the LOW figure.
    $apr        = (float) ($p['financing_default_apr'] ?? 7.99);
    $termMonths = max(6, (int) ($p['financing_default_term_months'] ?? 120));
    $monthlyRate = $apr / 100 / 12;
    if ($monthlyRate > 0) {
        $payment = $low * ($monthlyRate * (1 + $monthlyRate) ** $termMonths)
            / ((1 + $monthlyRate) ** $termMonths - 1);
    } else {
        $payment = $low / $termMonths;
    }

    return [
        'roof_sqft_estimate'      => $roofSqft,
        'squares'                 => $squares,
        'estimate_low_cents'      => $low,
        'estimate_high_cents'     => $high,
        'financing_monthly_cents' => (int) round($payment),
        'financing_apr'           => $apr,
        'financing_term_months'   => $termMonths,
    ];
}

/**
 * Persist an estimate row (tenant-scoped) and return
 * ['estimate_id' => int, 'results_token' => string, ...calculation].
 * $via must be one of: public_web, dashboard, mobile_app.
 */
function create_estimate(int $companyId, int $clientId, array $input, string $via): array
{
    $calc  = calculate_estimate($companyId, $input);
    $token = bin2hex(random_bytes(16)); // 32 chars, gates the public results page

    $stmt = db()->prepare(
        'INSERT INTO estimates
            (company_id, client_id, results_token, home_sqft, roof_sqft_estimate, stories,
             roof_material_current, roof_material_desired, roof_age_years, roof_condition,
             pitch, tear_off_needed, estimate_low_cents, estimate_high_cents,
             financing_monthly_cents, created_via)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $companyId,
        $clientId,
        $token,
        max(200, min(20000, (int) $input['home_sqft'])),
        $calc['roof_sqft_estimate'],
        max(1, min(3, (int) $input['stories'])),
        substr(trim((string) ($input['roof_material_current'] ?? 'unknown')), 0, 50),
        array_key_exists($input['roof_material_desired'] ?? '', ROOF_MATERIALS)
            ? $input['roof_material_desired'] : 'asphalt_architectural',
        isset($input['roof_age_years']) && $input['roof_age_years'] !== ''
            ? max(0, min(120, (int) $input['roof_age_years'])) : null,
        in_array($input['roof_condition'] ?? '', ROOF_CONDITIONS, true)
            ? $input['roof_condition'] : 'unknown',
        in_array($input['pitch'] ?? '', ROOF_PITCHES, true) ? $input['pitch'] : 'unsure',
        in_array($input['tear_off'] ?? '', TEAR_OFF_OPTIONS, true) ? $input['tear_off'] : 'unsure',
        $calc['estimate_low_cents'],
        $calc['estimate_high_cents'],
        $calc['financing_monthly_cents'],
        in_array($via, ['public_web', 'dashboard', 'mobile_app'], true) ? $via : 'public_web',
    ]);

    return ['estimate_id' => (int) db()->lastInsertId(), 'results_token' => $token] + $calc;
}

/**
 * Find-or-create a client (lead) for a company by email. Tenant-scoped.
 * Returns the client id. Updates contact fields on repeat submissions.
 */
function upsert_client(int $companyId, array $c): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE company_id = ? AND email = ?');
    $stmt->execute([$companyId, $c['email']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare(
            'UPDATE clients SET name = ?, phone = ?, address = ?, zip_code = ?, state = ?
             WHERE id = ? AND company_id = ?'
        );
        $stmt->execute([
            $c['name'], $c['phone'], $c['address'] ?? null, $c['zip_code'], $c['state'],
            (int) $existing['id'], $companyId,
        ]);
        return (int) $existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO clients (company_id, name, email, phone, address, zip_code, state, status, consent_tcpa)
         VALUES (?, ?, ?, ?, ?, ?, ?, "new", ?)'
    );
    $stmt->execute([
        $companyId, $c['name'], $c['email'], $c['phone'], $c['address'] ?? null,
        $c['zip_code'], $c['state'], (int) ($c['consent_tcpa'] ?? 0),
    ]);
    return (int) $pdo->lastInsertId();
}

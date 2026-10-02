<?php
/**
 * GET /api/v1/company/profile.php — the authenticated tenant's profile,
 * pricing settings, and material catalog. The field app uses this to render
 * its own estimator with the company's branding and pricing labels.
 *
 * Auth: Authorization: Bearer <token>.
 * Query params: none.
 *
 * Success 200: { success: true, data: {
 *   company: { id, slug, name, logo_path, contact_email, contact_phone,
 *              service_area, license_number, subscription_status,
 *              trial_ends_at, estimator_url },
 *   pricing_settings: { <setting_key>: <string value>, ... },   // merged over defaults
 *   materials: { <material_key>: <display label>, ... }         // ROOF_MATERIALS
 * } }
 *
 * Errors: 405 non-GET, 401/403 auth.
 */

declare(strict_types=1);

require __DIR__ . '/../../../includes/bootstrap.php';
require_once PROJECT_ROOT . '/includes/estimator-engine.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_error('GET only.', 405);
}

$auth = api_require_auth();
$cid = $auth['company_id'];
$company = $auth['company'];

json_out(['success' => true, 'data' => [
    'company'          => [
        'id'                  => (int) $company['id'],
        'slug'                => $company['slug'],
        'name'                => $company['name'],
        'logo_path'           => $company['logo_path'],
        'contact_email'       => $company['contact_email'],
        'contact_phone'       => $company['contact_phone'],
        'service_area'        => $company['service_area'],
        'license_number'      => $company['license_number'],
        'subscription_status' => $company['subscription_status'],
        'trial_ends_at'       => $company['trial_ends_at'],
        'estimator_url'       => SITE_URL . '/estimate/' . $company['slug'],
    ],
    'pricing_settings' => get_company_pricing($cid),
    'materials'        => ROOF_MATERIALS,
]]);

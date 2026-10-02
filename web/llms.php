<?php
/**
 * Dynamic llms.txt (served at /llms.txt via rewrite) — a plain-text
 * markdown summary of the platform for AI crawlers and answer engines.
 * Built entirely from APP_NAME / SITE_URL so branding never drifts.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$app  = APP_NAME;
$site = SITE_URL;

echo <<<TXT
# {$app}

> {$app} is multi-tenant SaaS for roofing companies: each company gets a
> branded instant roof estimator that prices projects with the company's own
> numbers and turns website visitors into qualified, TCPA-consented leads.

## Who it's for

Roofing company owners who want their website traffic to produce qualified
leads. Homeowners use a company's branded estimator to answer five quick
questions about their roof and receive an instant ballpark price range; the
company receives the lead in its dashboard and inbox the moment it arrives.

## What it does

- Branded public estimator link (and website embed) per roofing company
- Estimates priced with each company's own numbers: per-square material
  costs, tear-off and permit fees, surcharges, and state multipliers
- Instant lead capture with TCPA consent, plus email notifications
- Lead pipeline dashboard (new → contacted → quoted → won/lost)
- REST API ready for the upcoming mobile app

## Key public URLs

- {$site}/ — platform overview, features, pricing, and FAQ
- {$site}/signup.php — start a free trial (no credit card required)
- {$site}/estimate/{company-slug} — each roofing company's branded public
  estimator (one per company)

## Private areas

/dashboard/, /super-admin/, and /api/ are private application areas — they
require authentication and are not intended for crawling or citation.
TXT;

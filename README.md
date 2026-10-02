# Roofing Estimator

A multi-tenant SaaS for roofing companies, with a native Android companion app.

Each company signs up and gets its own branded public estimator at `/estimate/{slug}`.
Homeowners get an instant price range, and the company manages its leads and estimates
from a private dashboard. The platform owner has a separate super-admin panel.

- **[`web/`](web/)**: PHP 8 / MariaDB web app and REST API. It uses no framework and has no
  build step. The UI is vanilla JS, Alpine.js and Tailwind.
- **[`android/`](android/)**: Kotlin + Jetpack Compose app for company staff. It uses Hilt, Retrofit,
  kotlinx-serialization and EncryptedSharedPreferences.

Status: built and tested. It has not launched yet, so the product name and domain are
still placeholders (`TEMP_APP_NAME`, `PLACEHOLDER-DOMAIN.com`). See [docs/SETUP.md](docs/SETUP.md).

## The tenant isolation rule

Every table that holds company data has a `company_id`. Every query on company data filters
by the `company_id` returned from a gatekeeper. It never uses one from the request:

- Dashboard pages call `require_company_auth()` first. It checks the session, blocks
  suspended or cancelled companies, and returns the company.
- API endpoints call `api_require_auth()`. There the `company_id` comes from the Bearer token.
- The public estimator finds the company from the URL slug only.

A row fetched by id must also match `AND company_id = ?`. On a miss it returns 404, the
same as for an id that does not exist. The super-admin pages are the only place where
queries across companies are allowed.

Isolation was checked with two test companies, in both directions, through both the
dashboard and the API. It was checked again through the Android app's own network stack.

## REST API v1

Every response is either `{"success":true,"data":{…}}` or `{"success":false,"error":"msg"}`
with a 4xx or 5xx status. Auth is `Authorization: Bearer <64-char token>`. Tokens last 30 days
and are stored server-side only as an HMAC-SHA256 hash. All money values are integer cents.

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `/api/v1/auth/login.php` | POST | none (8 per 15 min per IP) | `{email,password}` → token, user, company |
| `/api/v1/auth/refresh.php` | POST | Bearer | Rotates the token. The old token stops working at once. |
| `/api/v1/clients/list.php` | GET | Bearer | Paged, filtered lead list |
| `/api/v1/clients/get.php` | GET | Bearer | One lead and its estimates. 404 if it belongs to another tenant. |
| `/api/v1/clients/update.php` | POST | Bearer | Change status and/or notes |
| `/api/v1/estimates/create.php` | POST | Bearer | Create an estimate on site, for an existing or new client |
| `/api/v1/estimates/list.php` | GET | Bearer | Paged, filtered estimate list |
| `/api/v1/company/profile.php` | GET | Bearer | Company, pricing settings, material labels |
| `/api/v1/device/register-token.php` | POST | Bearer | Register an Android device for new-lead push alerts |
| `/api/public/estimate.php` | POST | none (slug-scoped, 10 per hour per IP) | `preview` gives a price range. `submit` creates a lead and emails both sides. |

Bad filter values return 422. They are never silently ignored.

## Security

- PDO prepared statements everywhere. Every web form POST carries a CSRF token.
- The public estimator has a honeypot field (`hp_ref_code_x9`). It is hidden off-screen,
  not with `display:none`, and its name is chosen so browser autofill never fills it.
- Rate limits are stored in the database: public estimate, API login and web login.
- The super-admin session uses a separate table and a separate cookie. Support
  impersonation is flagged and shows a banner.
- Dashboard and admin responses send `X-Robots-Tag: noindex`. Results pages are gated by
  a 32-character unguessable token.
- On Android, tokens are stored in Keystore-backed EncryptedSharedPreferences. The app
  refreshes a token before it expires, and any 401 signs the user out.

## Run it

```bash
# Web (PHP 8.1+, MariaDB)
cd web
composer install
cp config/config.example.php config/config.php    # DB + SMTP settings
php migrate.php                                   # creates tables + a super admin
php -S 127.0.0.1:8085 -t . dev-router.php

# Android (JDK 17, Android SDK)
cd android
./gradlew --no-daemon :app:assembleDebug
./gradlew --no-daemon :app:testDebugUnitTest

# Live isolation tests through the app's network stack (needs the dev server above
# and two test companies: alpha-owner@test.local and bravo-owner@test.local)
INTEGRATION_BASE_URL=http://127.0.0.1:8085/api/v1/ ./gradlew --no-daemon :app:testDebugUnitTest
```

## License

MIT. See [LICENSE](LICENSE).

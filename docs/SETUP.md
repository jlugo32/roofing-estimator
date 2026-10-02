# Setup and launch notes

## RENAME BEFORE LAUNCH

The product name and domain are placeholders. Each one lives in exactly one place.

**Web**

1. `APP_NAME` in `web/config/config.defaults.php` (now `TEMP_APP_NAME`). Every page,
   email, meta tag and JSON-LD block reads this constant.
2. `SITE_URL` in the same file (now `https://PLACEHOLDER-DOMAIN.com`). Canonicals, the
   sitemap, robots.txt, llms.txt and email links all come from it. `robots.php`,
   `sitemap.php` and `llms.php` build those files from PHP, so they update when the constant changes.
3. `SMTP_*` and `SUPER_ADMIN_NOTIFY_EMAIL` in `web/config/config.php`.

The database name (`estimator_platform`) is generic on purpose. Do not rename it.

**Android**

1. **applicationId**: `com.tempcompany.tempapp` in `android/app/build.gradle.kts`.
   It **cannot change after the first Google Play upload**.
2. **Display name**: `app_name` in `android/app/src/main/res/values/strings.xml`.
3. **API base URL**: `API_BASE_URL` `buildConfigField` in `android/app/build.gradle.kts`.
   It is the only place the backend host appears.
4. **Icon and branding**: the placeholder adaptive icon is in `res/mipmap-anydpi-v26/` and
   `res/drawable/ic_launcher_foreground.xml`.
5. **Play listing**: a privacy policy URL and the data-safety form (login credentials,
   client contact data, FCM token).
6. **Billing policy**: subscriptions are billed on the web through Stripe. The app sells
   nothing in-app. Confirm the current Play policy for free companion apps to an externally billed B2B service.

## FCM setup

New-lead push alerts are optional. The app and the backend work fully without them.

1. Create a Firebase project. Add the Android app with the **final** applicationId.
   Download `google-services.json` into `android/app/`. The google-services plugin is
   applied only if the file exists, so the build works with or without it.
2. Backend: generate a service-account key JSON, save it as
   `web/storage/fcm-service-account.json`, and set `FCM_SERVICE_ACCOUNT_JSON` in
   `web/config/config.php` to that path.
3. The app registers its device token after login (`DeviceRegistrar`) and again when FCM
   rotates the token (`LeadMessagingService.onNewToken`). Android 13+ also needs the
   `POST_NOTIFICATIONS` runtime permission.

## Known limits

- **Dashboard stats are computed in the app**, over at most 500 clients and 500 estimates.
  The v1 API is frozen and has no stats endpoint. Above the cap, the UI marks the numbers
  as approximate. A `/api/v1/stats.php` endpoint would remove the limit.
- **Pricing settings and the company profile can be edited on the web only.**
- **There is no offline cache yet.** Lists show a "no connection" state with a retry button.
- **Logout** uses `auth/refresh.php`: it rotates the token and throws the new one away. The
  old token dies on the server, so no separate logout endpoint is needed.
- The API rate-limits logins to 8 per 15 minutes per IP. The live integration tests log in
  about 5 times per run, so clear the `rate_limits` table between repeat runs.

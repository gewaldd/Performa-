---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# External Integrations

**Analysis Date:** 2026-09-07

**Scope:** `Employer/` and the shared Firebase/auth helpers it calls.

## APIs & External Services

**Payment Processing:**
- None

**Email/SMS:**
- None implemented in Employer. README describes deadline alerts; Employer pages do not send mail/SMS

**External APIs:**
- Google Firestore REST — all directory, ratings, reports, custom KPIs
  - Integration method: PHP cURL/file helpers in `firebase_init.php`
  - Auth: OAuth2 access token from service-account JWT (scopes: datastore, cloud-platform, identitytoolkit)
- Google Identity Toolkit REST — create user, update password, disable account
  - Used from `add_employee.php`, `settings.php`, `employee_view.php`
- ui-avatars.com — generated profile images (`https://ui-avatars.com/api/?name=...`)
- Google Fonts CDN — IBM Plex Sans, JetBrains Mono
- jsDelivr Bootstrap (only `layout.php`, unused by live views)

**AI (product README vs this module):**
- README mentions Random Forest and Gemini. Employer PHP does not call those APIs. Dashboard “insight” is a local worst-gap score comparison and a hardcoded course title `Performance Improvement Training`

## Data Storage

**Databases:**
- Cloud Firestore collections used by Employer:
  - `Users` — profiles, roles, hire/probation fields, assigned training, regularization decision
  - `Ratings` — weekly KPI scores (`employeeUid`, `scores`, `weekOf`, `ratedBy`)
  - `Reports` — generated snapshots (`reports.php` write, `report_view.php` read)
  - `CustomKpis` — employer-added KPIs per industry key (via `kpi_templates.php` / `add_custom_kpi`)
  - Connection: Firebase project from service account `project_id`
  - Client: custom REST helpers, not an ORM
  - Migrations: none (schemaless documents)

**File Storage:**
- None for Employer uploads. Avatars are remote URLs

**Caching:**
- PHP session: `dashboard_live_data` (60s) in `employer_dashboard.php`; `performa_employee_directory` (20s) in `employees.php`
- Disk: `sys_get_temp_dir()/performa_{md5(collection)}.json` (600s) in `kpis.php` and `reports.php`
- Firebase access-token cache: `sys_get_temp_dir()/firebase_sa_token_*.json` in `firebase_init.php`

## Authentication & Identity

**Auth Provider:**
- Firebase Identity Toolkit for account create/password/disable
- App session: PHP `$_SESSION` (`uid`, `role`, `name`, `email`, `department`)
  - Gate: `Employer/includes/auth.php` → root `auth.php` `require_login()` + `require_role('employer')`
  - Token storage: PHP session cookie (not JWT in the browser)
  - Logout: `../logout.php`

**OAuth Integrations:**
- None for end users (no Google sign-in in Employer pages)

## Monitoring & Observability

**Error Tracking:**
- None (no Sentry). Some pages `error_log()` failures (`settings.php`, `reports.php`)
- `Employer/php_errors.log` exists in the module folder (do not treat as a product logger)

**Analytics:**
- None

**Logs:**
- PHP `error_log` / `display_errors` depending on host php.ini

## CI/CD & Deployment

**Hosting:**
- Not defined in Employer. Traditional PHP deploy assumed

**CI Pipeline:**
- No Employer-specific test or deploy workflow observed

## Environment Configuration

**Development:**
- Required: `GOOGLE_APPLICATION_CREDENTIALS` or `firebase-service-account.json`
- Secrets location: `.env` and service-account JSON at project root (never commit values)
- Mock/stub services: none; pages swallow Firestore errors and render empty lists

**Staging / Production:**
- Same REST helpers; separate Firebase projects would be env-only (not coded as environments)

## Webhooks & Callbacks

**Incoming:**
- None

**Outgoing:**
- None (assign course writes Firestore only; no LMS webhook)

---

*Integration audit: 2026-09-07*
*Update when adding/removing external services*

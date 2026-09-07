---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# Technology Stack

**Analysis Date:** 2026-09-07

**Scope:** `Employer/` employer portal (plus shared PHP helpers it requires).

## Languages

**Primary:**
- PHP (typed helpers in places: `string` params, `?string`, `Throwable`) — all Employer pages and `includes/`
- JavaScript (vanilla, no bundler) — client filters, CSV export, custom dropdowns in `Employer/*.js`

**Secondary:**
- CSS custom properties in `Employer/styles.css` — UI chrome for the live shell
- SVG path strings in PHP — inline icons in `employer_layout.php`, page `$icons` arrays, and `includes/icons.php`

## Runtime

**Environment:**
- PHP with OpenSSL (`openssl_sign` required by `firebase_init.php` for service-account JWTs)
- Browser runtime for JS/CSS; no Node build step for this module
- No `composer.json` in the repo; Employer does not use Composer packages

**Package Manager:**
- None for Employer. CDN assets only (and only in unused `layout.php`)

## Frameworks

**Core:**
- Page-per-route PHP (not MVC, not a framework). Each `*.php` file is an entry point
- Session auth via root `auth.php` (`require_login()`, `require_role('employer')`)
- Firebase REST (not PHP Admin SDK) via root `firebase_init.php`

**Testing:**
- None in `Employer/`
- Root `test.php` is `phpinfo()`, not a test suite

**Build/Dev:**
- None (edit PHP/JS/CSS and serve from the web root)

## Key Dependencies

**Critical:**
- Google Firestore REST — `firestore_list_documents`, `firestore_get_document`, `firestore_write_document` from `firebase_init.php`
- Google Identity Toolkit REST — `identitytoolkit_create_user`, `identitytoolkit_update_password`, `identitytoolkit_disable_user`
- Root `kpi_templates.php` — industry KPI templates and `employee_kpi_summary()` / `add_custom_kpi()` used by dashboard, KPIs, reports, rating

**Infrastructure:**
- PHP sessions (`$_SESSION`) for login and short-lived dashboard/directory caches
- `sys_get_temp_dir()` JSON files for 600s collection caches in `kpis.php` and `reports.php`
- ui-avatars.com HTTPS URLs for avatar images (no local upload)

**Unused / parallel UI:**
- Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 via jsDelivr in `Employer/layout.php` — no live page `require`s this file

## Configuration

**Environment:**
- Root `.env` loaded by `firebase_init.php` (gitignored typical)
- `GOOGLE_APPLICATION_CREDENTIALS` — path to Firebase service-account JSON
- Fallback credential files: project `firebase-service-account.json` or `../firebase-service-account.json` relative to `firebase_init.php`

**Build:**
- No Vite/webpack/tsconfig. Fonts loaded from Google Fonts: IBM Plex Sans, JetBrains Mono

## Platform Requirements

**Development:**
- Windows/macOS/Linux with PHP + OpenSSL + a local web server pointing at the Performa- document root
- Firebase project with Firestore and Identity Toolkit enabled

**Production:**
- Traditional PHP hosting (Apache/nginx + PHP-FPM or similar)
- Service-account JSON on disk (not in the browser)
- Outbound HTTPS to `googleapis.com` (Firestore, Identity Toolkit, OAuth token)

---

*Stack analysis: 2026-09-07*
*Update after major dependency changes*

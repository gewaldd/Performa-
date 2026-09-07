---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# Architecture

**Analysis Date:** 2026-09-07

**Scope:** Employer portal under `Employer/`.

## Pattern Overview

**Overall:** Multi-page PHP portal with shared chrome, Firestore as the system of record

**Key Characteristics:**
- One PHP file ≈ one screen; POST handlers live at the top of the same file
- Role-gated (`employer`) before any page logic
- Server-rendered HTML + vanilla JS for search/filter/export
- No shared Employer service layer — Firestore calls are inlined
- Dual layout artifacts: live `employer_layout.php` vs unused Bootstrap `layout.php`

## Layers

**HTTP page (controller + view):**
- Purpose: Auth already done; load Firestore; handle POST; echo HTML
- Contains: `employer_dashboard.php`, `employees.php`, `add_employee.php`, `employee_view.php`, `kpis.php`, `rate_employee.php`, `reports.php`, `report_view.php`, `settings.php`
- Depends on: `includes/config.php` → `firebase_init.php`; `includes/auth.php`; often `../kpi_templates.php`
- Used by: Browser navigation between relative `*.php` URLs

**Shell / presentation:**
- Purpose: Sidebar + session name/role
- Contains: `employer_render_shell($active)` in `employer_layout.php`; page-local `$icons` arrays; `styles.css`
- Depends on: `$_SESSION` only (no Firestore)
- Used by: Every live Employer page

**Shared domain helpers (repo root, not Employer-local):**
- Purpose: KPI templates, summaries, custom KPI merge
- Contains: `kpi_templates.php` (`kpi_template_for`, `employee_kpi_summary`, `add_custom_kpi`, `ratings_for_employee`)
- Depends on: Firestore when `firestore_get_document` exists
- Used by: dashboard, KPIs, reports, rating, employee view

**Firebase adapter:**
- Purpose: REST to Firestore and Identity Toolkit
- Contains: `firebase_init.php`
- Depends on: service-account JSON, OpenSSL
- Used by: all Employer data mutations/reads

**Auth:**
- Purpose: Session login and exact-role check
- Contains: `auth.php` (`require_login`, `require_role`, `logout`)
- Used by: `Employer/includes/auth.php` on every page (except `rate_employee.php` which requires auth files directly)

## Data Flow

**Authenticated page load:**

1. Browser hits e.g. `Employer/employees.php`
2. `includes/config.php` loads Firebase helpers
3. `includes/auth.php` starts session via `auth.php`, requires `uid` and `role === 'employer'`
4. Page optionally reads session cache or `firestore_list_documents('Users'|'Ratings'|'Reports')`
5. PHP maps documents into arrays (role filters: probationary vs exclude admin/employer)
6. HTML rendered with `employer_render_shell('Employees')` + `styles.css`
7. JS (`employees.js`, `script.js`, `kpis.js`, `dropdowns.js`) filters rows client-side

**Assign training (dashboard POST):**

1. POST `action=assign_course` + `uid` + `course` on `employer_dashboard.php`
2. `firestore_get_document('Users', $uid)` then merge `assignedTraining` / `assignedTrainingAt`
3. Clear dashboard session cache; redirect `?assigned=1`

**Add employee:**

1. POST on `add_employee.php`
2. List all `Users` to check duplicate email
3. `identitytoolkit_create_user` then `firestore_write_document('Users', $uid, ...)`
4. Redirect to `employees.php?created=1&temp_password=...`

**Weekly rating:**

1. POST scores on `rate_employee.php`
2. Write `Ratings/{uid}_{Y-m-d}` with `scores` map and `ratedBy` session uid

**Report generation:**

1. POST `generate_report` on `reports.php`
2. Snapshot KPI summary into `Reports` document
3. List/view via `report_view.php?id=` (print CSS / `autoprint`)

**State Management:**
- Durable: Firestore documents
- Ephemeral: PHP session caches and temp-dir JSON collection dumps
- Client: filter/pagination state in JS only (not persisted)

## Key Abstractions

**Page script:**
- Purpose: Combine POST + query + HTML
- Examples: `kpis.php`, `reports.php`
- Pattern: Procedural file, not a class

**Role key normalizer:**
- Purpose: Map messy Firestore `role` strings to `probationary` / `supervisor` / `employer` / `admin`
- Examples: duplicated `normalize_role_key()` in `employer_dashboard.php`, `employees.php`, `employee_view.php`
- Pattern: Copy-pasted function (not a shared include)

**Collection cache helper:**
- Purpose: Avoid repeated full collection list
- Examples: `get_cached_collection()` in both `kpis.php` and `reports.php`
- Pattern: Duplicated disk cache keyed by `md5($collectionName)` (global, not per-employer)

**KPI template:**
- Purpose: Industry-specific KPI keys/targets plus CustomKpis overlay
- Examples: `kpi_template_for('retail')` in `kpi_templates.php`
- Pattern: PHP arrays + optional Firestore merge

## Entry Points

**Portal home:**
- Location: `Employer/employer_dashboard.php`
- Triggers: Login redirect / sidebar Dashboard
- Responsibilities: Probationary metrics, evaluations table, assign course, CSV export JS

**Other screens:**
- `employees.php` — directory of non-admin/non-employer users
- `add_employee.php` — Identity Toolkit + Users write
- `employee_view.php` — profile, disable, regularization fields
- `kpis.php` — per-employee KPI board + custom KPI POST
- `rate_employee.php` — weekly scores
- `reports.php` / `report_view.php` — generate and print
- `settings.php` — own profile, password, deactivate

**Broken link:**
- Dashboard evaluate button targets `evaluate.php?uid=` — file is not in `Employer/` (rating lives at `rate_employee.php`)

## Error Handling

**Strategy:** `try/catch (Throwable)` around Firestore; degrade to empty arrays or user-facing alert strings. Dashboard assign-course catch is empty (failure is silent).

**Patterns:**
- `error_log()` on settings/reports failures
- Exception message sometimes shown in UI (`add_employee.php`, `employee_view.php`, `rate_employee.php`)

## Cross-Cutting Concerns

**Logging:**
- Ad hoc `error_log`; no structured logger

**Validation:**
- Email `FILTER_VALIDATE_EMAIL` on add employee and settings
- Role allow-list on add (`probationary` / `supervisor`)
- Report type allow-list `$reportTypes` in `reports.php`
- Many POSTs have no CSRF token and no employer-tenant check on target `uid`

**Authentication:**
- Session + exact `employer` role. `require_role` does not treat `Employer` or `probationary_employee` aliases

**Output escaping:**
- Widespread `htmlspecialchars(..., ENT_QUOTES)` in templates

---

*Architecture analysis: 2026-09-07*
*Update when major patterns change*

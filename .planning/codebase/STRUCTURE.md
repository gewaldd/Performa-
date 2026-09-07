---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# Codebase Structure

**Analysis Date:** 2026-09-07

**Scope:** Employer module plus the root files it includes.

## Directory Layout

```
Performa-/
├── Employer/                 # Employer portal (this map)
│   ├── includes/             # Module bootstrap (Firebase + auth)
│   ├── employer_dashboard.php
│   ├── employees.php
│   ├── add_employee.php
│   ├── employee_view.php
│   ├── kpis.php
│   ├── rate_employee.php
│   ├── reports.php
│   ├── report_view.php
│   ├── settings.php
│   ├── employer_layout.php   # Live sidebar shell
│   ├── layout.php            # Unused Bootstrap 5 master layout
│   ├── styles.css
│   ├── script.js             # Dashboard filters / CSV / confirm modal
│   ├── employees.js
│   ├── kpis.js
│   └── dropdowns.js
├── firebase_init.php         # Firestore + Identity Toolkit REST
├── auth.php                  # Session require_login / require_role
├── kpi_templates.php         # Industry KPIs + summaries
├── login.php / logout.php    # Shared (outside Employer/)
└── .planning/codebase/       # This map
```

## Directory Purposes

**Employer/:**
- Purpose: All employer-facing screens
- Contains: PHP pages, CSS, vanilla JS, `php_errors.log` (not application source)
- Key files: `employer_dashboard.php` (home), `employer_layout.php` (nav)
- Subdirectories: `includes/` only

**Employer/includes/:**
- Purpose: Thin wrappers so pages stay `require_once __DIR__ . '/includes/...'`
- Key files: `config.php` (Firebase), `auth.php` (login + employer role), `icons.php` (`render_icon` — little used vs page-local `$icons`)

## Key File Locations

**Entry Points:**
- `Employer/employer_dashboard.php` — dashboard
- `Employer/employees.php` — directory
- `Employer/kpis.php` — KPI management
- `Employer/reports.php` — reports list/generate
- `Employer/settings.php` — account settings
- `Employer/rate_employee.php` — weekly rating (auth included without `includes/`)

**Configuration:**
- `Employer/includes/config.php` — `require` root `firebase_init.php`
- Root `.env` / `GOOGLE_APPLICATION_CREDENTIALS` — not under Employer/

**Core Logic:**
- Inline in each page PHP file
- `kpi_templates.php` — KPI domain
- `firebase_init.php` — persistence and identity

**Testing:**
- None under `Employer/`

**Documentation:**
- Root `README.md` (product vision; stack listed there does not match this PHP module)

## Naming Conventions

**Files:**
- `snake_case.php` for pages (`employee_view.php`, `rate_employee.php`)
- `employer_` prefix for dashboard and layout
- `*.js` matching feature (`employees.js`, `kpis.js`) or generic (`script.js`, `dropdowns.js`)
- `styles.css` singular global stylesheet

**Directories:**
- PascalCase module folder `Employer/`
- lowercase `includes/`

**Special Patterns:**
- Query params: `uid`, `employee`, `id`, `created`, `assigned`, `temp_password`, `autoprint`
- POST `action` discriminator: `assign_course`, `add_kpi`, `generate_report`, `save_profile`, `change_password`, `deactivate_account`, `toggle_status`, `save_regularization`

## Where to Add New Code

**New Employer screen:**
- Add `Employer/{feature}.php` with the same three requires (`config`, `auth`, `employer_layout`)
- Add nav item in `$items` inside `employer_render_shell()` in `employer_layout.php` (and `$navItems` in `layout.php` only if that layout is revived)
- Put page CSS in `styles.css`; page JS as `Employer/{feature}.js` if non-trivial

**New POST action:**
- Handle at the top of the owning page before HTML, matching existing `$_POST['action']` switches

**Shared PHP helper (role normalize, collection cache):**
- Prefer a new `Employer/includes/*.php` or root helper — do not copy `normalize_role_key` into a fourth file

**KPI template change:**
- Edit `kpi_templates.php` (affects rating, KPIs, reports)

**Utilities:**
- Icons: extend `includes/icons.php` and actually `require` it, or keep `$icons` on the page (current majority pattern)

## Special Directories

**Employer/includes/:**
- Purpose: Bootstrap only
- Committed: Yes

**Temp cache files:**
- Purpose: `performa_*.json` under the OS temp dir
- Source: `kpis.php` / `reports.php`
- Committed: No

---

*Structure analysis: 2026-09-07*
*Update when directory structure changes*

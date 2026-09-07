---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# Coding Conventions

**Analysis Date:** 2026-09-07

**Scope:** `Employer/` PHP/JS/CSS. Match these when adding Employer code.

## Naming Patterns

**Files:**
- `snake_case.php` page names
- Feature-named JS (`employees.js`, `kpis.js`); dashboard uses `script.js`
- No `*.test.php`

**Functions:**
- `snake_case` in PHP (`normalize_role_key`, `display_role_label`, `get_cached_collection`, `employer_render_shell`, `employer_layout_icon`)
- `camelCase` in JS (`applyFilters`, `matchesFilters`, `getContainer`)
- POST handlers are not functions; they are `if (REQUEST_METHOD === 'POST' && action === '...')` blocks

**Variables:**
- PHP: `$camelCase` mixed with `$snake` (`$liveUsers`, `$cacheTTL`, `$roleKey`)
- Session cache keys: string literals like `dashboard_live_data`, `performa_employee_directory`
- JS: `camelCase`; DOM ids like `dashboardSearch`, `evaluationRows`

**Types:**
- Occasional PHP 7+ type hints on helpers (`string $name`, `?string $role`)
- No classes/interfaces in Employer

## Code Style

**Formatting:**
- No Prettier/PHPCS config in-repo
- Indentation mixed: 4-space (`employer_dashboard.php`, `add_employee.php`) and 2-space (`employees.php`, `kpis.php`, `settings.php`)
- New Employer PHP: pick **2-space** to match the majority of later pages, or match the file you edit
- HTML attributes often split one-per-line in the dashboard
- Large banner comments: `/* === FAST SHORT-SESSION CACHING === */`

**Linting:**
- None configured for Employer

## Import Organization

**PHP requires (canonical live page):**
1. `includes/config.php`
2. `includes/auth.php`
3. `employer_layout.php`
4. `../kpi_templates.php` when KPIs/ratings/reports needed

**Exception:** `rate_employee.php` requires `../auth.php`, `../firebase_init.php`, `../kpi_templates.php`, `employer_layout.php` directly — do not copy this; use `includes/` for new pages.

**JS:** no modules (`<script src="...">` at end of body). `dropdowns.js` is an IIFE.

## Error Handling

**Patterns:**
- Wrap Firestore in `try { } catch (Throwable $e)`
- User message variables: `$message` + `$messageTone` (`info` | `success` | `error`) rendered as `.alert.alert-{tone}`
- Prefer `error_log('Employer ... failed: ' . $e->getMessage())` plus a generic user string (`settings.php`) over leaking exception text
- Do not add empty catch blocks (dashboard assign-course currently swallows errors)

**Error Types:**
- Validation failures set `$message` and skip writes
- Missing `uid` redirects to `employees.php`

## Logging

**Framework:**
- PHP `error_log` only

**Patterns:**
- Prefix messages with `Employer settings` / `Employer reports` when logging

## Comments

**When to Comment:**
- Explain probationary filtering and 180-day rules (dashboard already does)
- Section banners for POST vs cache vs metrics

**TODO Comments:**
- None of note in Employer sources

## Function Design

**Size:**
- Pages are large (dashboard ~1480 lines). Extract only when duplicating (role normalize, collection cache, icons)

**Parameters:**
- Keep POST field names stable (`action`, `uid`, `employee`)

**Return Values:**
- Helpers return arrays; pages `header(); exit;` on success redirects

## Module Design

**Exports:**
- PHP functions in layout/includes; no namespaces
- JS files attach to DOM by id; no exports

**Duplication to avoid:**
- Do not paste another `$icons` SVG map if `includes/icons.php` can be required
- Do not paste another `get_cached_collection` — extract if touching KPIs and reports together

**HTML:**
- Escape all interpolated user/Firestore strings with `htmlspecialchars($x, ENT_QUOTES)`
- Active nav: pass the label key to `employer_render_shell('Dashboard'|'Employees'|'KPIs'|'Reports'|'Settings')`

---

*Convention analysis: 2026-09-07*
*Update when patterns change*

---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# Codebase Concerns

**Analysis Date:** 2026-09-07

**Scope:** `Employer/` employer portal.

## Tech Debt

**Duplicated role normalization:**
- Issue: `normalize_role_key()` / similar `strpos(..., 'probation')` copied across `employer_dashboard.php`, `employees.php`, `employee_view.php`, `kpis.php`, `reports.php`, `rate_employee.php`, `add_employee.php`
- Why: Pages grew independently
- Impact: Dashboard only counts `probationary`; directory excludes `admin`/`employer` but includes supervisors; KPIs/reports use substring `probation` — role string drift changes who appears where
- Fix approach: One include, e.g. `Employer/includes/roles.php`, used by every page

**Duplicated collection disk cache:**
- Issue: Identical `get_cached_collection` / `clear_collection_cache` in `kpis.php` and `reports.php`
- Why: Copy to speed Firestore list-all
- Impact: Cache key is collection name only (not tenant). Stale data up to 600s. Cache files on shared temp dir
- Fix approach: Shared helper with per-session or per-employer invalidation on writes

**Unused Bootstrap layout vs live custom shell:**
- Issue: `layout.php` documents `$content` injection + Bootstrap CDN; every live page uses `employer_layout.php` + `styles.css` and inlines a full HTML document
- Why: Layout rewrite in progress or abandoned
- Impact: Nav/icon changes must be edited in the live shell; `layout.php` will rot
- Fix approach: Delete or wire one layout; stop duplicating `$icons` and `<head>` on every page

**Page-local SVG icon maps:**
- Issue: Large `$icons` arrays duplicated; `includes/icons.php` `render_icon()` is unused by those pages
- Impact: Inconsistent icons, huge diffs
- Fix approach: Require `icons.php` from the shell

**README vs implementation:**
- Issue: Root `README.md` describes React, Random Forest, Gemini, SMS/email alerts, PDF/A-1b
- Impact: Planners may implement the wrong stack
- Fix approach: Treat Employer as PHP+Firestore; AI/alerts are not in this module

## Known Bugs

**Dashboard Evaluate link 404:**
- Symptoms: “Evaluate” control does not open a rating page
- Trigger: Click eval button on `employer_dashboard.php` (href `evaluate.php?uid=`)
- Workaround: Use `rate_employee.php` from KPIs
- Root cause: Target file missing; rating lives in `rate_employee.php`
- Fix: Point href to `rate_employee.php?employee={uid}`

**Hardcoded default password:**
- Symptoms: Blank password on `add_employee.php` creates `TempPass123!`
- Trigger: Submit form without temporary password
- File: `add_employee.php`
- Impact: Predictable credentials if the query-string copy is missed
- Fix: Generate a random password; never use a constant

**Temporary password in URL:**
- Symptoms: Password appears in `employees.php?temp_password=`
- Trigger: Successful create redirect in `add_employee.php`
- Impact: Password in browser history, Referer, server logs
- Fix: Flash once via session, do not put secrets in the query string

## Security Considerations

**No CSRF tokens on state-changing POST:**
- Risk: Cross-site form can assign training, create users, change ratings, disable accounts, update regularization
- Files: All Employer POST handlers (`employer_dashboard.php`, `add_employee.php`, `employee_view.php`, `kpis.php`, `rate_employee.php`, `reports.php`, `settings.php`)
- Current mitigation: Same-site session cookie only (not an explicit token)
- Recommendations: Per-session CSRF token on every form; reject mismatched POST

**No tenant / ownership check on `uid`:**
- Risk: Authenticated employer can GET/POST any Firestore user id (`employee_view.php`, dashboard `assign_course`, `rate_employee.php`)
- Current mitigation: Role gate `employer` only
- Recommendations: Store `employerId`/`orgId` on Users and filter all queries; deny writes outside the org

**Full collection scans with service account:**
- Risk: `firestore_list_documents('Users'|'Ratings')` loads entire collections; service account bypasses client security rules
- Files: dashboard, employees, kpis, reports, add_employee, rate_employee
- Recommendations: Query by employer/org; enforce Firestore rules even for admin SDK-equivalent credentials where possible

**Password change without current password:**
- Risk: Stolen session can set a new password (`settings.php` `change_password`)
- Recommendations: Require current password or reauth

**Account deactivate is one POST:**
- File: `settings.php` `deactivate_account`
- Recommendations: Confirm + CSRF; `script.js` has `data-confirm` modal — use it here if not already

**Disk cache of Users/Ratings:**
- Risk: JSON dumps of PII in `sys_get_temp_dir()`
- Files: `kpis.php`, `reports.php`
- Recommendations: Restrict file perms, encrypt, or cache in session only

## Performance Bottlenecks

**List-all Users and Ratings per page:**
- Problem: Unbounded `firestore_list_documents` then PHP foreach filters
- Files: `employer_dashboard.php`, `employees.php`, `kpis.php`, `reports.php`, `rate_employee.php`
- Measurement: Not benchmarked; cost grows linearly with all tenants’ documents
- Cause: No Firestore query/index by role or employer
- Improvement path: Indexed queries; stop caching Ratings in the dashboard session payload if unused for the table

**Dashboard session cache includes `$allRatings`:**
- File: `employer_dashboard.php`
- Cause: Full ratings list stored in `$_SESSION['dashboard_live_data']`
- Improvement path: Cache derived `$liveUsers` only

## Fragile Areas

**180-day status machine:**
- File: `employer_dashboard.php`
- Why fragile: Overlapping rules (on-track vs needs-review vs near deadline vs ready-for-reg) with magic numbers 30 / 150 / 180 / target 4.2
- Common failures: `createdAt` missing → 0 days / odd progress; hireDate on add-employee not used for timeline (uses `createdAt`)
- Safe modification: Extract functions with tests before changing thresholds
- Test coverage: None

**`require_role('employer')` exact match:**
- File: `auth.php`
- Why fragile: Firestore may store `Employer` or `probationary_employee`-style values; login must set session role to exactly `employer`
- Safe modification: Align create-user roles and session role at login

**Shared global temp cache:**
- Files: `kpis.php`, `reports.php`
- Why fragile: Two PHP processes / two employers share `performa_{md5('Users')}.json`
- Common failures: Employer A sees Employer B’s user list until TTL
- Test coverage: None

## Scaling Limits

**Firestore list-all:**
- Current capacity: Fine for a demo tenant
- Limit: Document count of entire `Users`/`Ratings` collections
- Symptoms at limit: Slow dashboard, PHP memory, 60s session of huge arrays
- Scaling path: Query filters + pagination server-side (JS pagination in `employees.js` is client-only after full render)

## Dependencies at Risk

**Custom Firebase REST instead of Admin SDK:**
- Risk: Hand-rolled JWT/token cache in `firebase_init.php` must track Google API changes
- Impact: All Employer reads/writes fail if token or REST shape breaks
- Migration plan: Official google/cloud-firestore if Composer is adopted

**ui-avatars.com:**
- Risk: Third-party availability; PII (names/emails) sent as query params
- Impact: Broken avatars only
- Migration plan: Initials CSS or local generation

## Missing Critical Features

**evaluate.php / in-module AI training:**
- Problem: Product copy promises RF/Gemini recommendations; dashboard assigns a fixed course string
- Current workaround: Manual “Assign Course”
- Blocks: Personalized training narratives
- Implementation complexity: High (model + API); low to fix the evaluate URL

**CSRF + org scoping:**
- Problem: See Security
- Blocks: Safe multi-tenant production
- Implementation complexity: Medium

## Test Coverage Gaps

**All Employer mutations:**
- What's not tested: create user, assign course, ratings write, report snapshot, disable user, password change
- Risk: Regressions ship unnoticed; `evaluate.php` already broken
- Priority: High
- Difficulty to test: Need Firebase emulator or interface seam around `firebase_init.php`

**Role filter matrix:**
- What's not tested: which roles appear on dashboard vs directory vs KPI picker
- Priority: High
- Difficulty: Low once `normalize_role_key` is extracted

---

*Concerns audit: 2026-09-07*
*Update as issues are fixed or new ones discovered*

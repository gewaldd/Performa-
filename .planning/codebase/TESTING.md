---
last_mapped_commit: 6ecc357b9e217c14960d7fdfb8d4754cf239d6f3
---

# Testing Patterns

**Analysis Date:** 2026-09-07

**Scope:** `Employer/` — there is no automated test suite.

## Test Framework

**Runner:**
- None (no PHPUnit, Pest, Jest, or Playwright config)

**Assertion Library:**
- None

**Run Commands:**
```bash
# No Employer test command exists.
# Manual: log in as role employer and hit Employer/*.php in a browser.
```

Root `test.php` prints `phpinfo()` and must not be treated as a test runner.

## Test File Organization

**Location:**
- No `Employer/**/*.test.php`, no `__tests__/`, no `e2e/` for this module

**Naming:**
- N/A until tests are introduced

**Structure (recommended if adding tests later):**
```
tests/
  Employer/
    normalize_role_key_test.php   # after extracting the helper
    kpi_summary_test.php          # for kpi_templates.php
```

## Test Structure

**Suite Organization:**
- None in repo

**Patterns:**
- Verification today is manual UAT on dashboard, employees, KPIs, reports, settings

## Mocking

**Framework:**
- None

**What to Mock (when tests exist):**
- `firestore_list_documents` / `firestore_get_document` / `firestore_write_document`
- `identitytoolkit_create_user`, `identitytoolkit_update_password`, `identitytoolkit_disable_user`
- Session: `$_SESSION['uid']`, `$_SESSION['role'] = 'employer'`

**What NOT to Mock:**
- Pure KPI math in `kpi_templates.php` once isolated

## Fixtures and Factories

**Test Data:**
- None checked in for Employer
- Live Firebase project is the implicit fixture

**Location:**
- Do not use `Employer/php_errors.log` as a fixture

## Coverage

**Requirements:**
- No coverage target
- Critical untested paths: POST mutations, role filters, 180-day status machine, CSRF-less forms

**View Coverage:**
```bash
# N/A
```

## Test Types

**Unit Tests:**
- Not present. Highest value first extract: `normalize_role_key`, `employee_kpi_summary`, report-type allow-list

**Integration Tests:**
- Not present. Would need a Firebase emulator or recorded REST fixtures

**E2E Tests:**
- Not present. Browser flow: login → dashboard → employees → add employee → KPIs → rate → reports → settings

## Common Patterns

**Manual checks that match current UI:**
- Dashboard empty state: “No probationary employees are currently available.”
- Add employee success: `employees.php?created=1&temp_password=`
- Report missing: `report_view.php` “Report not found.”
- Evaluate button 404: `evaluate.php` is linked from `employer_dashboard.php` but not implemented

**Error Testing:**
- No automated `expect()->toThrow` patterns

**Snapshot Testing:**
- Not used

---

*Testing analysis: 2026-09-07*
*Update when test patterns change*

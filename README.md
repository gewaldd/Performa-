Performa

A Web-Based KPI Probationary Employee Evaluation and Training Recommendation Platform for Small Steps Learning Center

Overview

Performa is a web-based platform that helps small and medium enterprises (SMEs) in the Philippines systematically track, evaluate, and document the performance of probationary employees within the 180-day period mandated by Article 296 of the Philippine Labor Code.

The eventual goal is for a Random Forest classification model to analyze KPI patterns and identify competency gaps, with the Google Gemini API generating personalized training recommendations and regularization narratives. That AI layer is designed but not yet wired into the live product — see Current Status below for exactly what's real today versus what's still planned.

Problem Statement

Small and medium enterprises in the Philippines lack an affordable, structured, and legally compliant system to track, evaluate, and document the performance of probationary employees within the 180-day period required by Article 296 of the Labor Code.

Consequences:
- Missed regularization deadlines — employees become regular by default, even if underperforming
- Inconsistent or missing performance documentation — termination decisions become legally indefensible
- Subjective, memory-based evaluations — leading to bias and unfair decisions
- Costly legal claims (₱50,000–₱150,000 per case) without proper records
- Existing HR tools are too expensive and designed for large corporations

Current Status

This section exists so nobody — including a future Claude session — plans against the wrong stack or assumes a feature is live when it isn't. Last verified: 2026-09-19.

Actually built and working:
- Full Employer portal (dashboard, employee directory, add employee, employee detail/regularization, KPI tracking, weekly ratings, report generation, settings) — PHP + Firestore REST, no framework
- Supervisor and Probationary Employee portals alongside the Employer one, each with their own dashboard/profile/settings
- Admin portal for account management
- Server-generated secure passwords (CSPRNG-based, `security_utils.php`) — no hardcoded or human-typed temporary passwords
- Transactional email on account creation via the Brevo API (`mailer.php`) — credentials are emailed, never exposed in a URL; a one-time session-flash fallback exists only if email delivery fails
- Forced password-change gate (`mustChangePassword` + `require_password_reset()` in `auth.php`) — self-service, lives on each role's own settings/profile page, not an admin-side reset. Applies uniformly across Employer, Supervisor, and Probationary Employee
- CSRF protection on every state-changing Employer POST (`Employer/includes/csrf.php`)
- Audit logging for key account events (`audit_log.php`)
- Automated tests for the reset gate, CSRF, Firestore codec, KPI math, password policy, roles, and security utils (`tests/`, run via `tests/run.php`)
- 5 pre-built industry KPI templates (retail, BPO, food service, logistics, construction) with employer-addable custom KPIs and per-KPI target overrides, persisted in Firestore

Designed but not yet connected:
- `ml/predict.py` — a Random Forest classifier for regularization recommendations. The model code exists and is reasonably designed, but nothing in the PHP application calls it yet, and there is no training-data pipeline. Its hyperparameter values and label-mapping were reconstructed from a compiled bytecode artifact after the original source was lost, and should be reviewed against actual intent before being trusted in production.
- Gemini API narrative generation — not started
- Automated 180-day deadline alerts (email/SMS at 150/165/178 days) — not started; the dashboard shows the nearest deadline but doesn't proactively notify anyone
- PDF/A-1b compliant export — reports currently export via browser print, not a generated PDF file

Known real gap, not just an unimplemented feature: KPI scoring is currently a single 1-5 scale applied uniformly to every KPI regardless of what it actually measures (a sales-target-achievement KPI and an attendance KPI are scored the same way a subjective "customer service" KPI is). This needs real design work, not just a UI tweak — see the planning notes for what a next session should scope out.

Biggest current blocker: there is no labeled historical dataset to train the Random Forest model on. Regularization decisions today are a single overwritable "current decision" field per employee (`employee_view.php`), not an append-only record of what the KPI state looked like at the time a past decision was made and what actually happened afterward (regularized / extended / terminated). Wiring the model in before this exists would mean training on nothing. This needs to be treated as its own workstream, separate from and prior to any actual ML integration work.

Technology Stack

Frontend
- Server-rendered PHP (page-per-route, no framework) + vanilla JavaScript for client-side filtering, custom dropdowns, and CSV export
- No React, no build step, no bundler — this diverges from the original manuscript plan and is the actual implementation

Backend & Data
- PHP, talking to Google Cloud directly over REST (no Firebase Admin SDK, no Composer dependency)
- Cloud Firestore — `Users`, `Ratings`, `Reports`, `CustomKpis` collections
- Google Identity Toolkit REST — account creation, password changes, account disable

AI & Machine Learning (planned, not yet integrated — see Current Status)
- Random Forest (`ml/predict.py`, Python + scikit-learn) — regularization recommendation, not yet called from PHP
- Google Gemini API — training/regularization narrative generation, not started

Email
- Brevo transactional email API (free tier, 300/day) — replaces the originally planned SendGrid

Additional Tools
- GitHub for version control
- No Node/npm tooling; no Figma/Bootstrap dependency in the live UI (an unused Bootstrap layout file exists in the repo but no live page requires it)

Modules

Employer
- Dashboard — probationary headcount, nearing-deadline count, overall KPI performance, active evaluations table, AI-style insight card (currently rule-based worst-gap comparison, not yet the trained model)
- Employee directory — search, filter, CSV export, add employee
- Employee detail — profile edit, deactivate/reactivate, regularization decision entry
- KPI management — per-employee KPI board, custom KPI creation, target overrides
- Weekly performance rating entry
- Reports — generate and view snapshot reports
- Settings — profile, password, account deactivation

Supervisor
- Own dashboard, employee ratings entry, reports, notifications, settings

Probationary Employee
- Own dashboard, workstream, goals, feedback, profile, settings

Admin
- Account management, org-level settings

Getting Started (development)

```
php -c php.ini -S localhost:8000
```

Then open `http://localhost:8000/login.php`. Requires a Firebase project with Firestore and Identity Toolkit enabled, plus `GOOGLE_APPLICATION_CREDENTIALS` (or a `firebase-service-account.json`) and Brevo API credentials in `.env` for email delivery to work — see `mailer.php` and `firebase_init.php` for the exact variable names.

Running tests: `php tests/run.php`
# Release candidate stabilization audit

Date: 2026-09-09. Scope: the cumulative uncommitted working tree on `codex/erp-finance-tax-runtime-hardening`. No production/VPS connection, deployment, destructive migration, Git history mutation, or new business module was performed.

## A. Working tree

- Changed file entries (`git status --porcelain -uall`): 549.
- Modified: 107; deleted: 39; added/untracked: 403; Git-detected renames: 0.
- The 39 deletions are deliberate modularization removals. Every former global business controller/service has an owner-module replacement; the old Document controller is replaced by `EnterpriseDocumentController` and its registered module routes.
- Suspicious accidental findings: 3 classes of issue. Resolved: 3 — a 1,194-line historical prompt appended to Organization routes, prompt-style inline comments in `CostCenterController`, and the stale canonical header in `db.txt`.
- Runtime source contains no developer-machine absolute path or rehearsal database name after cleanup.

Domain distribution of changed entries: Commerce 73, Company 17, Dashboard 14, Document 17, Employee 11, Finance 30, Inventory 11, Maintenance 44, Payroll 10, Procurement 9, Project 44, Support 27, migrations 18, tests 44, frontend 46, documentation 43, dependencies 2, Docker/operations 2, and 86 Core/other-module entries.

## B. Dead code

- Candidates reviewed: 39 deleted global business files.
- Verified superseded: 39.
- Removed in the cumulative modularization change: 39.
- Retained with reason: generated/discovered module skeletons and legacy views are retained where static grep cannot prove safe removal or module discovery may use them.
- No file was deleted merely because its name appeared old.

## C. Architecture and CQRS

- Enabled modules: 26; module manifests discovered by the architecture auditor: 46.
- Declared dependency edges: 97; observed import edges: 74.
- Cycles: 0; undeclared dependencies: 0; layer violations: 0; automated CQRS violations: 0.
- New business CQRS under global `app/`: 0; `Modules/CQRS`: 0.
- Controller direct cross-domain business writes: 0.
- Commerce direct Inventory/Accounting/Treasury writes: 0; Maintenance direct Inventory/Procurement/Finance writes: 0.
- Two legacy transaction-orchestration blocks remain in `EmployeeController` for atomic profile/lifecycle/account/avatar changes. They are P2 debt, not a demonstrated staging failure; moving them without a complete application DTO/compensation boundary would be a risky freeze-time rewrite.
- Explicit Sales commands cover quotation acceptance/conversion, Sales Order confirmation/fulfilment, invoice creation/issue, and Collection allocation. Remaining bounded-context `Mutate*` facades are retained where existing contracts/tests demonstrate cohesive routing rather than split solely for naming purity.

## D. Routes, authorization and tenant boundaries

- Routes audited: 694; named: 689; intentionally unnamed framework routes: 5.
- Duplicate route names: 0; duplicate method/URI pairs: 0.
- Missing auth/verified middleware on admin routes: 0.
- Missing permission/policy boundary: 0 under `AdminRouteSecurityGovernanceTest`.
- Unauthorized Sidebar children, orphan targets and duplicate destinations: 0 under navigation governance.
- New deterministic IDOR/tenant defects: 0. Company, Finance, Procurement, Inventory, Sales, Project, Maintenance, Document and Workflow tenant suites remain green.

## E. Database and migrations

- Canonical matrix: 98 migrations / 329 tables in each governed local database; development schema absent.
- Fresh-install rehearsal: 96/96 migrations and 329 tables.
- New additive migrations reviewed: 18; index definitions: 46; unique definitions: 75; FK/foreign definitions: 267.
- Current schema defects found: 0; destructive migrations introduced by this release slice: 0.
- Migrations 094 and 095 create Project/Maintenance persistence. Migration 096 adds nullable cross-domain source metadata and indexes; production table-size and online-DDL lock review remains mandatory.
- The risk scanner reports 11 historical backfill/raw-DDL migrations requiring release-operator review. This is operational P2/manual debt, not a fresh-schema failure.

## F. Idempotency, transaction and concurrency

- Critical flows reviewed: Checkout/payment, Collection, inventory issue/receipt, Payroll/payslip, Project Expense/PR, Maintenance Expense/PR, request conversion, preventive generation, DMS version upload and accounting adjustment.
- New deterministic defects: 0; fixes required: 0; remaining release blockers: 0.
- Unique source/idempotency evidence and focused concurrency suites remain authoritative; no financial job has an infinite retry policy.

## G. Frontend and design system

- Blade DB/Eloquent access: 0.
- Confirmed unsafe XSS findings: 0. Public article body uses escaped text before line-break formatting; raw module-name output is trusted static configuration in legacy skeleton views.
- Broken imports/assets: 0; Vite production build resolves all manifest entries.
- Debug logging in runtime bundle: 0. Browser runner `console.log` calls only emit its final machine-readable test report.
- Sidebar: PASS; current bright-gold icon token remains `rgb(255, 228, 119)`.

## H. Security and environment hygiene

- Critical security findings: 0; secret exposure: 0; automated external calls: 0.
- Security headers, CSRF, auth, tenant IDOR, private DMS authorization and rate-limit policies remain covered.
- Runtime/config source contains no hard-coded developer path or rehearsal database.
- Default Compose queue owner is `queue:work`; Horizon is opt-in through `horizon-only`, preventing duplicate default consumers.
- Composer audit: 0 advisories; npm audit: 0 vulnerabilities. `maatwebsite/excel` is 3.1.70; related lockfile changes are transitive patch releases, not an unrelated major upgrade.

## I. Tests and skipped test

- Skipped tests: 1.
- Classification: `INTENTIONALLY_SKIPPED`. `AdminRouteSecurityGovernanceTest::test_fixture_login_routes_are_not_registered_in_production` can only assert production-only registration while the suite is deliberately running with `APP_ENV=testing`.
- The Notification fixture guard test executes under `schema_admin_testing` and is not the skipped test.
- Fragile tests changed by this stabilization audit: 0; meaningful tests added solely because of a discovered gap: 0.
- Fixed query ceilings were not loosened during this audit.

## J. Documentation and generated artifacts

- `DU_LIEU_THUC_HIEN_TAI.txt` was regenerated from the current database; totals are not manually edited.
- ERP flow/module maps were regenerated: 120 menu items, 26 enabled modules, 97 declared dependency edges, cycles 0, undeclared dependencies 0.
- Historical matrix values remain only as labelled phase evidence. `db.txt` now leads with the canonical 96/329 matrix.
- No rehearsal database, backup archive, Playwright video/trace, coverage output or task-owned SQL dump remains.

## K. Findings and release classification

| Priority | Count | Disposition |
|---|---:|---|
| P0 blocker | 0 | None |
| P1 before staging | 0 | All three hygiene defects found in this audit were resolved |
| P2 technical debt | 3 | Legacy Employee controller transaction orchestration; historical migration online-DDL/manual review; scheduler single-owner production verification |
| P3 documentation/optional | 3 | Legacy trusted-config raw Blade skeletons; declared runtime/schema dependencies without imports; optional standalone DQ CI log step |

Final classification: **RELEASE_CANDIDATE_READY_WITH_P2_DEBT**. The codebase is suitable for a controlled staging release candidate. Production remains externally blocked until infrastructure, credentials, backups, RPO/RTO and host process supervision are verified.

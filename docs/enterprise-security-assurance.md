# Enterprise Security Assurance

Phase 3F-16 establishes a shared defensive assurance layer without adding a business module or database persistence. It inventories trust boundaries, records typed findings, hardens the Integration URL boundary, validates safe filenames and exposes a permission-protected read-only operations dashboard/command.

## Operational use

Run `php artisan operations:security-assurance --json` for the safe posture summary. Review the threat model and residual risks before enabling external HTTP delivery. The dashboard is available at `/admin/operations/security-assurance` to verified users with `operations.view`.

Accepted design gaps are not hidden: privileged MFA and a malware scanning provider require future product/infrastructure decisions. They do not weaken the existing maker-checker, private-storage, checksum or authorization controls.

## Phase 3F-16 evidence

- Route surface: 497 total; 427 authenticated; 391 explicitly privileged; 70 public/framework/auth/health routes.
- Defensive focused gate: 34 tests / 73 assertions; representative IAM, tenant, webhook, Audit, Payroll, Employee and Workflow regression exited 0.
- Full PHPUnit: 440 tests, 439 passed, 4,546 assertions, 1 skipped, exit 0.
- PHPStan repository and affected security tests: level 10, 0 errors. Pint, Blade, Vite, Composer/npm audits, secret scan and Docker configuration passed.
- Browser: exact 1280×800 and 390×844, zero document overflow and zero console/page problems; fixture cleanup returned Audit rows to zero.
- Runtime image `sha256:f7c5fc44f8133b83085208e5ee291dc7f3aa44a6a38b01706ef89d2a123edbe7`: `www-data` UID 82, no `.env`, tests or private evidence; reduced capabilities and `no-new-privileges` in the production-like Compose contract.
- Schema: no migration; all four environments remain at 61 migrations / 230 tables.
- Production counts remained unchanged and no adversarial request targeted production or an external provider.
# Phase 3F-17 accessibility security regression

Navigation labels, ARIA metadata and localStorage state contain no credentials, PII, restricted business values or permission snapshots. Backend middleware remains authoritative.

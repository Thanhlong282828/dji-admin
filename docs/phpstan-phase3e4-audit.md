# PHPStan Phase 3E-4G scope audit

Audit date: 2026-08-01. Level: 10. No baseline, `ignoreErrors` or inline suppression was added.

## A. Government Filing

Scope: Government Feature, Unit and MySQL process-concurrency tests plus their shared worker. Result: **0 errors** after hardening. The tests cover all seven permissions, tenant record isolation, invalid lifecycle actions, maker-checker, canonical/deterministic XML, changed-source hash, tampered download, success/blocked access logs and create/generate/validate/approve/submit/retry races.

## B. Payroll

Scope: `Modules/Payroll/app`, `tests/Feature/Payroll`, `tests/Unit/Payroll`, and `tests/Concurrency/Payroll`. Result: **0 errors** at level 10.

## C. Legacy outside Phase 3E-4

Whole `tests` scope result: **41 errors**, all outside Payroll/Government Filing. They were audited but deliberately not changed because Phase 3E-4G forbids unrelated implementation changes.

| Legacy scope | Errors | Main categories |
|---|---:|---|
| Attendance Feature | 9 | mixed identifier normalization, nullable/string response values |
| Auth Feature | 5 | nullable refreshed user, untyped reset-token notification |
| Employee Feature | 11 | nullable database projections, enum/string mismatch, iterable value typing |
| EmployeeCore top-level Feature | 7 | nullable database projection, iterable value typing |
| Recruitment top-level Feature | 4 | iterable value typing, nullable relations |
| Workforce top-level Feature | 1 | mixed string normalization |
| Workforce Feature | 2 | mixed identifier normalization |
| Workforce Concurrency | 2 | mixed identifier normalization |
| **Total** | **41** | **Legacy outside Phase 3E-4** |

Evidence command:

```bash
./vendor/bin/phpstan analyse tests --level=10 --memory-limit=2G
```

This repository-wide legacy result does not invalidate the clean Payroll/Government scope, but it remains explicitly visible and is not reported as a repository-wide PHPStan pass.

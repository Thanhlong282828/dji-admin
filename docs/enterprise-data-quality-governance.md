# Enterprise Data Quality Governance

Canonical command: `php artisan operations:data-quality-audit --json`.

Canonical UI: `/admin/operations/data-quality`, protected by `auth`, `verified` and `operations.view`.

The 13-rule registry covers duplicate employee codes, orphan Employee/User links, missing company access, manager integrity, assignment tenant/position-unit/primary/career-level integrity, Attendance employee/time integrity and Payroll employee/snapshot/run reconciliation. Results are evidence, not auto-fixes.

Severity policy: critical means referential, tenant or calculation ambiguity; warning means reconciliation is required but the relationship may be optional under current policy. No PII appears in output.

Final local evidence: 13 passed, zero failed/warning, score 100, checksum `4dd27c0dd82a2b5aafbac057c84b50cce81ae83301403133d92e5d702559ada8`.

Final runtime evidence also covers manual Employee create/edit assignment persistence, deterministic promotion/demotion direction, one effective primary assignment, lifecycle/history initialization and Cloudflare Images compensation. Employee/avatar soft archival preserves the owned avatar as historical evidence; explicit avatar removal deletes the owned provider asset. A hard provider cleanup is never attempted before the replacement database reference commits.

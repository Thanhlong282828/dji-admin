# PR #2 Coverage Remediation Backlog

This is release evidence and a bounded remediation backlog, not permanent governance. Exact executable-line targets must be populated from the sanitized `laraveldji.coverage-inventory.v1` artifact produced by the approved CI diagnostics. Estimates below are provisional until that artifact exists.

## Current evidence

- Source scope remains `app + Modules`; no source exclusion is authorized.
- Required line coverage remains 20%.
- Run `40002766629` completed 1,035 tests and 34,992 assertions with one skipped test. The threshold gate failed at 10.1%.
- The log exposed 2,661 source entries, of which 2,552 reported 0.0%. The failed run did not retain executable/covered-line counts, so no exact line target is asserted here.
- The previous default-branch run reported 39.6% over a materially smaller source tree. PR #2 adds 42,268 production lines across 988 changed production files and 16,943 test lines across 155 changed test files relative to merge commit `85e22f444de7f875303fff8d22ab7d237aeeae8d`.

## Acceptance contract for every batch

- Populate baseline executable, covered and uncovered lines from the signed CI artifact before selecting files.
- Tests must prove business behavior, denial behavior, tenant/company/branch isolation and rollback or compensation where a mutation is involved.
- No test may exist only to execute lines. Assertions must cover output, persisted state, emitted domain behavior and forbidden side effects.
- Finance/Tax calculations, immutable snapshots, RBAC/ABAC, mass-assignment allowlists and database safety guards remain unchanged unless separately approved.
- Run focused PHPUnit, relevant concurrency/security gates, PHPStan level 10 and Pint after each batch.

## Proposed bounded-context batches

| Priority | Context | Business behavior and positive paths | Negative/security paths | Initial line target |
|---|---|---|---|---|
| P0 | IAM + Company/Tenant | Role assignment, scoped permission resolution, company membership and branch context | Cross-tenant denial, stale permission cache, unauthorized role escalation, wrong-company IDs | Select the highest-risk uncovered classes; target 10–15% of the exact repository gap |
| P0 | Finance + Tax | Journal command/handler flows, transaction posting, FX/tax calculations, reconciliation and immutable mapping snapshots | Unbalanced posting, closed period, invalid mapping, cross-company access, rollback after exception | 25–30% of the exact gap, without altering calculations or rounding |
| P0 | Payroll | Payroll state transitions, approved inputs, calculation services, posting/export boundaries | Unapproved run, duplicate processing, cross-tenant employee, transaction rollback, immutable finalized run | 20–25% of the exact gap |
| P1 | Employee + Recruitment | Employee credential/lifecycle commands, recruitment transitions and tenant-scoped queries | Unauthorized lifecycle transition, duplicate identity, invalid company/branch, rollback | 15–20% of the exact gap |
| P1 | Document + Storage | Authorized document lifecycle, server-generated paths, metadata/provider consistency | MIME rejection, malware unavailable/timeout, tenant denial, storage failure compensation | 10–15% of the exact gap |
| P1 | Commerce + Procurement + Inventory | Order/procurement/inventory command-handler transactions and canonical references | Invalid state, duplicate command, ownership denial, partial failure rollback | Remaining gap plus at least a 2 percentage-point safety margin |

## Inventory-driven planning procedure

1. Sort contexts by uncovered executable lines, then sort classes within each context by uncovered lines.
2. Remove no production source from measurement. Flag disabled scaffolds separately but retain them in the denominator until an independently approved product deletion decision exists.
3. Select tests around domain invariants and transaction boundaries, not raw getters or generated boilerplate.
4. Record the expected covered-line delta per proposed test group from the inventory; after implementation, compare actual delta and investigate material variance.
5. Stop when repository coverage is at least 20% and all required quality/security gates pass. Target a small safety margin so harmless compiler/line-map drift does not immediately break the gate.

## Residual risks

- The 20% target may require multiple batches because large Payroll, Employee, Finance, Recruitment and Performance surfaces currently report 0% at class/file level.
- PHPUnit notices are not yet identified. The diagnostic runner now requests notice details; any notice indicating invalid test metadata or incomplete test execution must be resolved before release.
- Coverage artifacts contain relative source paths and numeric metrics only. Raw Clover data is deliberately cleaned and not uploaded.

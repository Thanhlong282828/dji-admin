# Enterprise Performance Audit

Phase 3F-18 audited enabled application and module PHP sources before optimization. The inventory contains 1,146 query/cache iteration pattern matches: Payroll 324, Employee 138, IAM 117, Performance 91, Recruitment 81, Notification 68, Workflow 65, Organization 53, app 51, Attendance 50, Integration 28, Leave 27, Audit 23, Company 20, Onboarding 7 and Dashboard 3.

## Evidence-based findings

| Component | Finding | Evidence | Decision |
|---|---|---|---|
| Employee list | Full Employee rows, including restricted columns, were hydrated then removed from the array | `EloquentEmployeeReadRepository::paginate()` | Select only list/display and relationship keys |
| Employee export | `cursor()` was combined with four accessed relationship families | Eloquent cursor cannot batch eager loads, producing per-row relation reads | Replace with `lazyById(500)` and retain explicit relations |
| IAM lists | Several pages use 100/200 fixed windows | Memory is bounded but navigation is not available past the window | Accepted warning; cursor pagination when operational volume requires it |
| Organization tree | Tree input is assembled from joined/aggregate rows | No per-node query in normal tree build; breadcrumb remains depth-bound | Retain and regression-test large hierarchy |
| Payroll/Organization/Audit exports | Cursor/generator streaming already exists | Formula sanitization and scoped queries are present | Retain; no semantic rewrite |
| Dashboard/cache | Typed arrays/DTO are cached by company/version | Avoids the historical serialized Eloquent object failure | Retain event/version invalidation and bounded TTL |

For 5,000 employees, the former code path had one base cursor plus four lazily accessed relation families per model, an analytical N+1 upper bound of 20,001 SELECTs. The corrected runtime measurement is 41 SELECTs (one base window plus batched relation queries across ten 500-row windows). The former number is a code-path estimate, not a replayed production measurement.

The 5,000-row testing EXPLAIN for the representative Employee list used a limited primary-key index scan with filtering. It did not prove that a new index would improve the real bounded list. No performance migration or production index change is justified by this evidence.

Production was used only for baseline row counts and schema/index metadata. No production query load, seed, `EXPLAIN ANALYZE`, DML or DDL was executed.

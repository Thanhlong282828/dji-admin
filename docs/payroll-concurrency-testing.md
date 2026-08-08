# Payroll concurrency testing

Status: **PARTIAL / NOT VERIFIED for new Payroll Run races**.

Database invariants already protect company-period uniqueness, run/item uniqueness, snapshot versioning, assignment/run/history idempotency and transactional item replacement. Services lock Period/Input Snapshot/Run and use optimistic versions with bounded attempts.

The existing Phase 3A process suite remains green (`mysql-concurrency`: 7 tests / 132 assertions). Required new scenarios—create same period, calculate/calculate, submit/recalculate, approve/reject, finalize/recalculate, finalize/finalize and notification duplicate delivery—have not yet been added to `workforce_concurrency_worker.php`; Phase 3B-1 remains PARTIAL until they pass on `schema_admin_testing`.
# Phase 3B-1D stabilization — 2026-07-28

`MysqlProcessRaceRunner` now uses a unique 128-bit race directory, atomic ready/release markers,
simultaneous process polling, absolute ready/completion deadlines, Symfony timeout checks and bounded cleanup.
The worker verifies the booted runtime is `APP_ENV=testing`, driver `mysql`, and database exactly
`schema_admin_testing` before reading a fixture payload.

Verified after the change:

- Shift assignment race: 1 test, 4 assertions, passed in 1.25s.
- Critical Workforce process races: 6 tests, 128 assertions, passed individually.
- Combined Workforce concurrency suite: 7 tests, 132 assertions, passed in 15.17s.
- No PHPUnit/worker process remained after completion.

The earlier five-minute run was not an infinite worker barrier. The old runner waited on workers
sequentially and started each completion timeout immediately before its own wait, allowing cumulative
delays. Full Feature suite execution also performs repeated MySQL schema setup and remains substantially
slower than Unit/Concurrency suites; it must not be reported passed until a complete run exits normally.

## Phase 3B-1E additions — 2026-07-28

The worker allowlist now includes assignment overlap, same-period creation, calculate/recalculate,
submit, approve, reject, finalize and Payroll notification delivery. Payloads remain scalar-only and
the worker still aborts unless the booted database is exactly `schema_admin_testing`.

Verified process scenarios:

- Assignment overlap: exactly one commit and one controlled rejection.
- Same Company/Period run creation with distinct idempotency keys: exactly one run and one create history.
- Payroll process group: 2 tests, 8 assertions, passed in 1.06s with fixture cleanup.

The remaining calculate/transition/notification races are allowlisted but still require dedicated
fixture/invariant tests before Phase 3B-1 can be closed.

## Phase 3B-1F calculation and transition races — 2026-07-28

Dedicated MySQL process fixtures now cover:

- Calculate vs Calculate: one calculated transition, one item set and one component set.
- Submit vs Recalculate: one winner; final item calculation version matches the Run.
- Approve vs Reject: one terminal transition with mutually exclusive approval/rejection timestamps.
- Finalize vs Recalculate: finalize wins and calculation rows remain byte-for-byte stable.
- Finalize vs Finalize: one finalization history and unchanged calculation rows.
- Payroll notification delivery vs delivery: one marker per channel and one database notification.

Result: 7 Payroll process tests, 46 assertions, passed in 3.94s. PHPStan level 10 and Pint passed.
No worker remained after the group completed. Partial-channel retry remains an integration-test gap.
## Phase 3B-1G delivery retry

`PayrollNotificationRetryTest` verifies channel-level idempotency using the production delivery service: database delivery is sent once, broadcast transitions failed → sent on retry, marker counts remain one per channel, attempts are 1/2 and the database notification remains unique. No Payroll lifecycle command is replayed.

## Phase 3B-3 regression

The HTTP controller split (`store` for creation and `mutate` for record actions) now matches route parameter order without changing service locking semantics. The MySQL process suite re-runs Calculate/Calculate, Submit/Recalculate, Approve/Reject, Finalize/Recalculate, Finalize/Finalize and notification delivery races. UI never bypasses version/idempotency fields.
# Insurance regression

Insurance calculation runs inside the existing Payroll Run transaction/version lock. Submit/finalize consistency checks include current-version item totals; submitted/finalized runs cannot recalculate and persisted insurance lines retain resolved rates.
## Payment Batch locking

Batch creation locks the finalized Payroll Run as its aggregate boundary, then rechecks for an active Batch. A nullable unique `active_intent_key` (`payroll_item_id:calculation_version`) provides database protection per active/released instruction; rejection clears the key. Lifecycle transitions lock the Batch and are retry-safe for same-target transitions. Real two-process MySQL cases cover Create/Create with different idempotency keys, Approve/Reject, Approve/Approve, Release/Release and Release/Reject. They prove one active Batch/item/activity, one terminal transition and one released file/access event. The Payroll concurrency suite passes 11 tests / 68 assertions. Live distributed bank delivery is not part of this phase.
# Accounting concurrency additions

`PayrollProcessConcurrencyTest` and the isolated MySQL worker now cover simultaneous Journal creation for the same Run/calculation intent and approve-vs-reject. Run row locks, state locks and the unique active-intent key guarantee one active Journal and one terminal transition.
# Adjustment concurrency contract

Create locks the finalized source Payroll Item and rechecks a database-unique active intent. Company/idempotency key retries resolve to one request. Lifecycle actions lock the Adjustment Request and only one valid terminal transition can win. Apply is protected by request lock plus unique Adjustment Run, statement, recovery and downstream intent keys.

## Phase 3E-1C3 process races

`PayrollProcessConcurrencyTest` now drives real independent PHP/MySQL workers for Approve/Approve, Approve/Reject, Apply/Apply, Apply/Reject, positive and non-applicable Payment create/create, Accounting create/create, Statement generate/generate, and completed retry/retry for all three downstream types. The final suite is 23 tests / 145 assertions. Assertions prove one terminal history, one Adjustment Run, one artifact per intent, balanced Journal totals, one valid PDF/checksum, stable artifact IDs and unchanged attempt counts on completed retries.

## Annual PIT reconciliation race plan (designed)

Phase 3E-2A adds no worker/test. Phase 3E-2 implementation must run independent MySQL races for Create/Create, Calculate/Calculate, Calculate/Submit, Submit/Submit, Review/Reject, Approve/Reject, Finalize/Finalize and Supersede/Finalize. Database unique active/idempotency keys plus locked expected-version transitions must prove one active aggregate, one immutable source/checksum version, one terminal history winner and no duplicate document/downstream intent.

Phase 3E-2B deliberately does not add process concurrency. Draft creation uses Company/idempotency and active/version database unique guards plus transaction retry; source collection is a locked aggregate transaction and is immutable after Submit. Independent lifecycle/process race proof remains Phase 3E-2D. Existing Payroll process concurrency regression remains 23 tests / 145 assertions passed.

Phase 3E-2C uses row locks and database unique guards for lifecycle, active version, issue fingerprints and intents. Independent multi-process Annual Tax race tests are intentionally deferred to 3E-2D and are not claimed here.
# Annual PIT Phase 3E-2C2 scope

Existing transaction row locks, source checksum guards, unique active/version keys, fingerprint uniqueness and downstream intent uniqueness remain in force. Full Annual Tax process-concurrency completion is explicitly deferred to Phase 3E-2D.

Full PHPUnit regression 2026-08-01 passed 221 tests / 1,908 assertions. Payroll calculation benchmark: 1,000 employees, 1,000 items, 5,000 breakdowns, 1,015.23 ms calculation and 10 MiB peak-memory delta. Export benchmark: 5,000 rows via cursor, 82.16 ms full stream and 2 MiB measured peak-memory delta.

Phase 3E-2C completion supersedes the earlier deferral: `PayrollAnnualTaxProcessConcurrencyTest` runs independent PHP processes against `schema_admin_testing`. Create/create leaves one active aggregate, calculate/calculate is same-source idempotent with one calculated history/current version, and finalize/finalize has one winner, one finalize history and four unique eligibility intents. No Phase 3E-2D work was started.

Phase 3E-2D extends the same process race to finalize+downstream/finalize+downstream, statement generate/generate, payment intent create/create, settlement journal create/create and retry/retry. Expected invariants are asserted: one final transition, one current PDF, one completed intent, one balanced journal and no duplicate resources.
# ERP process concurrency — Phase 3E-3

`ErpIntegrationProcessConcurrencyTest` uses two independent PHP processes, a barrier and MySQL `schema_admin_testing`; it does not use a sleep-based race.

- Create/Create: connection row lock plus unique active intent produces one Export and one complete item snapshot.
- Execute/Execute: export row lock produces one external reference, one artifact and `attempt_count=1`.
- Retry/Retry after export: both calls are idempotent; attempt, checksum and artifact path do not change.
- Reverse/Reverse: one terminal state and one `reversed` history row.

Result: 1 test, 15 assertions, passed.

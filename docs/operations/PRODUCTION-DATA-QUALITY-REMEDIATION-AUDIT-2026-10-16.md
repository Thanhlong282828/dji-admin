# Production Data Quality Remediation Audit — 2026-10-16

## Executive result

Classification: `DQ_REMEDIATION_PLAN_REQUIRES_APPROVAL`

This is a source/evidence audit only. Staging remained stopped; production,
Supabase and all operating records were untouched. The Batch 2 result contained
12 failed rule IDs, but not 12 independent corrupt datasets. Two rule pairs call
the same counting functions, while several other accounting rules describe the
same legacy journal cohort from different governance perspectives.

Authoritative Batch 2 totals: 221 rules, 209 passed, 12 failed, zero warnings,
zero duplicates, zero orphans and zero cross-tenant findings.

## Finding matrix

| # | Rule ID | Count / severity | Tables and columns inspected | Classification and cause | Business impact | Before/after 105–127 |
|---:|---|---|---|---|---|---|
| 1 | `identity.invalid_verified_phone` | 1 / critical | `users.phone_e164`, `users.phone_verified_at` | Legacy validation: a verified user has a missing phone or a value outside `^+84[1-9][0-9]{8,10}$`. | Recovery/MFA/contact trust can rely on a value that is not a valid verified Vietnamese E.164 identity. | Pre-existing. Migrations 105–127 do not alter users or phone verification. |
| 2 | `journal_without_policy` | 31 / critical | `payroll_accounting_journals.id, company_id, source_type, status`; `payroll_accounting_journal_lines.journal_id, mapping_snapshot` | Legacy governance lineage: a posted/exported controlled-source journal has no line containing both `mapping_snapshot.policy_id` and `policy_version`. | The posting cannot prove which approved accounting policy/version governed debit and credit. | Pre-existing. Policy foundation 101 and journal snapshot columns precede the backup; 105–127 do not backfill them. |
| 3 | `missing_account_mapping` | 62 / critical | Above plus `payroll_accounting_journal_lines.account_id`; `accounting_accounts.id, company_id` | Legacy mapping evidence: controlled journal lines have a missing/cross-company account or no policy ID/version in their mapping snapshot. | Account selection and tenant ownership cannot be reproduced from immutable evidence. | Pre-existing; no 105–127 backfill. |
| 4 | `posting_without_policy` | 31 / critical | Identical to rule 2 | DQ reporting alias. It calls `journalWithoutPolicy()` exactly like rule 2. The underlying defect is real; the count is not an additional 31 journals. | Same policy-lineage risk as rule 2. | Pre-existing reporting behavior and legacy data; not migration-created. |
| 5 | `posting_without_mapping` | 62 / critical | Identical to rule 3 | DQ reporting alias. It calls `missingAccountMapping()` exactly like rule 3. The underlying defect is real; the count is not an additional 62 lines. | Same mapping/tenant reproducibility risk as rule 3. | Pre-existing reporting behavior and legacy data; not migration-created. |
| 6 | `legacy_account_mapping_usage` | 62 / critical | Journal scope/status plus `journal_lines.mapping_snapshot.policy` | Legacy posting marker remains `canonical_source_posting_v1` instead of policy-backed snapshot semantics. | Runtime cannot claim 100% policy coverage; audit evidence describes an obsolete resolver path. | Pre-existing. No migration 105–127 rewrites mapping snapshots. |
| 7 | `journal_without_policy_snapshot` | 31 / critical | Journal scope/status plus `journal_lines.mapping_snapshot.policy` | Legacy immutability evidence: no line identifies `policy = accounting_posting_policy`. Counts strongly align with rules 2/6 but row identity was not re-queried after staging shutdown. | Posted journals lack immutable canonical policy snapshot evidence. | Pre-existing; not migration-created. |
| 8 | `receipt_without_policy` | 3 / critical | `procurement_goods_receipts.id,status`; journals `source_id,source_type,status`; journal-line `mapping_snapshot.policy_id` | Three received goods receipts have no qualifying `goods_receipt` journal with policy evidence. This may be a missing journal or a legacy journal lacking policy snapshot; the aggregate rule cannot distinguish those branches. | Inventory/GRNI recognition may be untraceable or absent. | Pre-existing. Goods receipt tables and posting runtime predate 105; migration 105 adds goods returns, not receipt postings. |
| 9 | `collection_without_policy` | 2 / critical | `customer_collections.id,status`; journals and journal-line policy snapshot fields | Two recorded collections have no qualifying `customer_collection` policy-backed journal. Could be missing journal or legacy snapshot. | Cash/Bank versus AR reduction cannot be proven through governed posting evidence. | Pre-existing. Collection runtime predates 105 and is not rewritten by 105–127. |
| 10 | `cogs_without_inventory_source` | 3 / critical | `sales_fulfillments.id,status,inventory_movement_id`; `inventory_movements.id,source_type,source_id`; journals/lines policy fields | Combined lineage rule: a fulfillment has no movement, wrong movement source identity, or no qualifying `sales_fulfillment` policy-backed journal. Exact branch requires a detail dry-run. | Stock decrease and COGS may not reconcile; margin and inventory valuation can be misstated or unauditable. | Pre-existing. Sales fulfillment and inventory movement schemas predate 105; no 105–127 data backfill. |
| 11 | `expense_without_policy` | 5 / critical | `finance_expenses.id,status`; journals `source_id,source_type,posting_type,status`; line policy snapshot | Five approved/paid expenses have no `finance_expense` / `expense_incurred` policy-backed journal. Could be a missing journal or legacy mapping snapshot. | Expense/AP or Cash recognition cannot be proven; statements and audit lineage are at risk. | Pre-existing. Expense runtime predates 105 and is not rewritten by 105–127. |
| 12 | `period_without_fiscal_year` | 4 / critical | `accounting_periods.company_id,fiscal_year_id,start_date,end_date`; `accounting_fiscal_years.id,company_id,start_date,end_date` | Migration-governance gap. Migration 116 adds nullable `fiscal_year_id` and new fiscal-year tables but intentionally does not invent company fiscal calendars. All four existing periods therefore lack a same-company fiscal year. | Period close/fiscal close and statement boundaries are not governed; posting-period validation is incomplete. | Newly detectable/introduced by migration 116. Before 116 the column/table did not exist and the DQ method returned zero. |

## Overlap assessment

- Rules 2 and 4 are implementation-identical; rules 3 and 5 are
  implementation-identical.
- The matching 31-journal and 62-line counts suggest two legacy lines per
  affected journal, but that is an inference, not row-level proof.
- Rules 6 and 7 likely describe the same legacy cohort using the explicit
  `mapping_snapshot.policy` discriminator. Exact set equality must be proven in
  the next approved dry-run.
- Rules 8–11 may overlap the 31 policy-less journals. They additionally assert
  source-document lineage, so they must not be removed merely as duplicates.
- No rule has been proven false-positive. Consolidating duplicate presentation
  is optional code hygiene only and must preserve canonical coverage and tests.

## Safe remediation design

### A. Read-only detail extractor first

Create a non-mutating command such as
`operations:data-quality-remediation-audit --json` that emits redacted IDs or
stable hashes plus company, branch, rule, reason branch and expected evidence.
It must:

- require an explicit database identity and refuse production unless separately
  authorized for read-only execution;
- scope every query by company and branch where available;
- split combined rules into precise reasons (missing journal, wrong source,
  missing movement, missing account, cross-company account, missing policy ID,
  missing version, or legacy marker);
- produce deterministic counts and a checksum without personal phone values,
  descriptions, account secrets or document payloads;
- prove row-set overlap among rules 2–11.

### B. Verified phone

Do not invent or normalize a phone automatically. Present the affected user to
an authorized identity administrator. Safe outcomes are either re-verification
through the canonical OTP flow with a valid E.164 value, or revoking the false
verification by clearing `phone_verified_at` while retaining an audit event.
The repair must use optimistic locking/idempotency and never expose the phone in
logs.

### C. Immutable legacy accounting evidence

Do not update posted journals or their lines. Backfilling
`mapping_snapshot` would rewrite immutable historical evidence and falsely imply
that a policy existed at posting time.

Preferred design is a dedicated, append-only legacy governance attestation,
created only after Finance approval. A possible migration would add an
`accounting_legacy_posting_attestations` table keyed uniquely by company,
journal and journal line, containing:

- source type/ID and posting type;
- original journal/line/account IDs and hashes of the original immutable
  snapshots;
- reviewed legacy mapping classification, currency and amount;
- attestation reason/version, reviewer/approver and timestamps;
- batch/correlation/idempotency key;
- no mutation or deletion cascade into posted journals.

DQ may accept an explicitly approved attestation as `legacy governed`, but must
continue to distinguish it from native `accounting_posting_policy` coverage.
Creating a retroactive normal policy snapshot is prohibited.

### D. Missing source accounting effects

For receipts, collections, COGS and expenses, the detail extractor decides:

1. **Journal exists but snapshot is legacy:** create only the approved append-only
   attestation; do not repost.
2. **Economic movement/journal truly missing:** reconcile source state, inventory,
   Treasury and GL first. Post a compensating journal in an approved open period
   through canonical CQRS/policy/period guards, referencing the original source
   and repair batch. Never backdate into a closed period.
3. **Wrong source/company/branch link:** do not relink immutable history in place;
   use reversal plus corrected posting or an approved compensating entry.
4. **COGS movement missing:** physical quantity/value reconciliation is required
   before any movement or journal is created. No synthetic inventory movement.

### E. Fiscal-year linkage

Require a Finance-approved manifest for each of the four periods:
`company_id`, fiscal-year name, start/end dates and target period IDs. The repair
creates/reuses fiscal years under the existing uniqueness constraints and links
only same-company periods fully contained in the approved date range. It must
reject overlap, gaps, cross-company IDs and closed-year conflicts.

## Repair command/migration contract

No repair file has been created in this audit-only phase. If approved, implement
separate schema and data-repair changes rather than hiding them inside an
ordinary deployment migration:

- default `--dry-run`; mutation requires `--execute`, exact database confirmation,
  approved manifest checksum and repair batch UUID;
- additive/idempotent uniqueness for every attestation or linkage;
- transaction per tenant/bounded batch with row locks and retry safety;
- maker-checker approval for financial attestation and compensation;
- before/after checksums, immutable audit event and actor/reason;
- no direct edits to posted journal amounts, lines or source economic values;
- failure rolls back only the current tenant batch;
- rerun returns the same canonical result, never duplicate journals/effects;
- compensation plan: reverse newly created compensating journals in an open
  period and deactivate/revoke repair evidence through append-only status events;
  never delete posted accounting history;
- fiscal linkage compensation may unlink only before any fiscal close/opening
  balance depends on it; afterward a separately approved governance correction
  is required.

## Required testing before any operating mutation

1. Restore a fresh production backup into isolated staging.
2. Run detail audit and archive redacted manifest/checksum.
3. Apply repair dry-run, then execute on a second disposable clone.
4. Prove tenant/company/branch isolation and maker-checker.
5. Prove exactly-once process concurrency and conflicting-manifest rejection.
6. Verify journals stay balanced and unchanged; prove no duplicate Treasury or
   inventory effects.
7. Run all 221 DQ rules, focused accounting/procurement/commerce/identity tests,
   then the authoritative full suite.
8. Restore/compensation rehearsal must complete before production approval.

## Risk and approval gates

| Remediation | Risk | Approval required |
|---|---|---|
| Read-only detail extractor and fresh staging audit | Low | Source/test change approval; separate staging runtime/cost approval |
| Phone re-verification/revocation | Medium | Identity owner/admin decision and exact user manifest |
| Legacy accounting attestation schema | High | Finance/accounting architecture approval and migration approval |
| Compensating journals/movements | Critical | Exact document manifest, maker-checker, open-period approval and production change window |
| Fiscal-year creation/linkage | High | Company fiscal-calendar manifest approved by Finance |
| Duplicate DQ presentation consolidation | Medium | Test-proven semantic-equivalence approval; cannot reduce coverage |

Production deployment and Batch 3 remain blocked until a fresh rehearsal proves
the approved remediation and all critical DQ findings are resolved without
rewriting immutable accounting history.

## Phase 1 local/testing implementation evidence

The approved local/testing-only phase added the read-only command:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=schema_admin_testing \
php artisan operations:data-quality-remediation-audit \
  --expected-database=schema_admin_testing --json
```

The command requires an exact expected database and independently checks the
configured and PDO database identities. It refuses non-testing purposes,
reports `DRY_RUN_READ_ONLY`, records zero mutations, redacts phone values and
emits a deterministic SHA-256 audit checksum. An optional positive `--company`
scope applies tenant filtering to every supported finding.

The extractor returns record IDs, company/branch scope where available, source
and related journal/movement IDs, plus a precise reason branch. It covers the
12 failed Batch 2 rule IDs. The two canonical alias pairs are built from the
same immutable record-key collections and the output also compares row sets for
the legacy policy/mapping cohorts.

The current `schema_admin_testing` database contained no operating clone after
the focused test refresh, so the live dry-run returned zero records and checksum
`aafef05b586b8825a74a9b53c6bf633e8d025649bf62e91c1abf3b428374aa95`.
This proves command behavior, not the identities of the 12 staging findings.
Exact production-derived record IDs and non-alias overlap remain
`NOT_ENOUGH_EVIDENCE` until a separately approved isolated restore/rehearsal is
available. Production was not queried or changed.

### Verified tests and gates

- Environment: `testing`; configured and actual PDO database:
  `schema_admin_testing`; purpose: `TESTING`.
- DQ remediation + existing DQ regression: 11 tests, 11 passed, 134 assertions.
- Two independent MySQL worker processes produced the same checksum and zero
  mutations.
- PHPStan level 10: PASS, zero errors.
- Pint: PASS.
- `git diff --check`: PASS.

### Prepared non-executable governance artifacts

- `docs/templates/dq-remediation-approval-manifest.yaml` contains exactly four
  fiscal-period mapping slots, intentionally leaving fiscal-year names and
  dates blank for Finance approval. It also reserves separate Finance and
  Identity approval sections. It is not an executable repair manifest.
- `docs/architecture/LEGACY-ACCOUNTING-ATTESTATION-STANDARD.md` defines an
  append-only, maker-checker, checksum-bound design. No table, migration or
  repair mutation was created in Phase 1.

### Decisions still required

1. **Finance:** approve the fiscal-year name/start/end and same-company mapping
   for each of the four period IDs after the detailed restored-data audit.
2. **Finance:** approve or reject the append-only legacy attestation model and
   decide which genuinely missing economic effects require compensating entries
   in an open period.
3. **Identity Admin:** choose canonical re-verification or revocation of false
   verification for the affected user. No phone normalization is inferred.
4. **Release owner:** separately approve an isolated production-backup restore
   to materialize the production-derived record-ID manifest and prove the
   non-alias row-set relationships.

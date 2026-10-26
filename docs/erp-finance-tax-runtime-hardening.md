# ERP Finance and Tax runtime hardening

## Scope and evidence baseline

This document describes the Finance/Tax behavior implemented at commit
`de0c2342115c43313ffbcab6aa0fe7059f5b567f`. Claims below are limited to the
source, migrations, and local tests listed in this document. They are not a
GitHub CI result, a production certification, or a legal/tax certification.

## Payroll-to-Finance reconciliation

Payroll Finance Reconciliation is a read-only projection. For each eligible
Payroll Run it compares:

```text
Payroll gross + employer statutory contribution
  = expected employer payroll cost
  = active Accounting Journal debit
  = active Accounting Journal credit
  = Branch Finance payroll cost from the immutable Branch input snapshot
```

Accounting is due only for finalized, locked, or paid runs. Earlier calculated,
submitted, or approved runs still reconcile to Branch Finance but show
`NOT_DUE` for journal generation. Missing or unbalanced journals on a finalized
run are reported as differences; the projection does not repair them.

Branch Bonus is already included in Payroll gross after an approved Salary
Assignment reaches calculation. Branch P&L presents it as a disclosure and
does not subtract it a second time.

PIT and employee/employer BHXH, BHYT, and BHTN totals are aggregated from
immutable Payroll Item components. Accounting liabilities are compared with
those totals. Journal creation is idempotent per Payroll Run, so a retry cannot
create a second active original journal.

## FX governance and lifecycle

### Rates and immutable snapshots

- FX rates are company-scoped, versioned, and idempotent by company and
  idempotency key. Reusing a key with different economic data fails closed.
- The server-enforced lifecycle is `draft -> submitted -> approved -> active ->
  retired`. Approval and activation enforce maker-checker separation. Activation
  rejects overlapping active windows for the same company, currency pair, and
  rate type.
- Resolution is company-, pair-, rate-type-, and effective-date-scoped. Identity
  conversion uses rate `1.000000000000`; missing, inverse-only, or wrong-type
  rates do not silently substitute for the requested direct rate.
- A rate snapshot records the canonical rate version, currencies, decimal rate,
  source reference, source identity, JSON payload, and SHA-256 checksum. Capture
  is create-once for its scoped source. A retry with the same evidence returns
  the existing snapshot; changed or non-canonical evidence is rejected.
- Monetary conversion uses integer minor units and explicit half-up rounding,
  avoiding floating-point drift.

### Recognition, settlement, and revaluation

- Customer-invoice foreign-currency recognition uses the company's functional
  currency, a direct spot rate where conversion is required, and an immutable
  recognition snapshot. Same-currency recognition requires no rate snapshot.
- Customer-collection settlement evidence is allocation-scoped and create-once.
  Partial and final settlements release carrying value cumulatively; realized
  gains/losses are posted with the applicable policy, and invalid retries or
  over-settlement fail without partial economic effects.
- Revaluation uses direct closing rates, immutable snapshots, an open accounting
  period, and company/optional-branch-scoped source discovery. A source cannot
  have two active revaluations for the same valuation date.
- Revaluation runs follow `draft -> submitted -> approved -> posted`, with a
  separate reversal operation. Maker-checker and distinct server-side
  permissions protect approval, posting, and reversal. Posting produces a
  balanced policy-backed journal; reversal mirrors it and is idempotent.
- Posted prior-period revaluation affects the next carrying basis; reversing it
  removes that active effect. Zero-difference, missing-policy, closed-period,
  closed-fiscal-year, and invalid-state paths fail closed and are tested for
  atomic rollback.
- Accounts-payable settlement and supplier-credit calculations have unit-level
  evidence for carrying-basis release, realized FX signs, overpayment rejection,
  and supplier-receivable outcomes. The migrations contain supplier invoice,
  payment, receipt, return, credit, and refund FX evidence structures, but this
  verification set does not certify every supplier flow end to end.

## VAT policy and posting boundary

### Policy lifecycle

- VAT categories are company-scoped and explicitly identify `output` or `input`
  direction. Policies are company-scoped, versioned by policy key, currency- and
  country-specific, and effective-dated.
- The lifecycle is `draft -> submitted -> approved -> retired`. Only the draft
  owner can update a draft; maker-checker prevents the creator from submitting
  their own policy through the approval command. Approved policy content is
  immutable, and activation rejects overlapping approved windows for the same
  company, category, and currency.
- A policy must reference an active tax category, a postable tax account, and an
  approved three-line accounting policy containing exactly one tax-account
  mapping. Missing or ambiguous policy/mapping evidence fails closed.

### Output VAT

For `customer_invoice` and `customer_credit_note`, the resolver requires an
`output` category. The implemented mapping produces balanced receivable,
revenue, and VAT-payable lines. Posting evidence records the company and branch,
source identity, policy and version, rate, taxable and tax amounts, journal, and
an accounting-mapping snapshot. Evidence is create-once for the scoped source;
a conflicting repost is rejected.

The approved local scope verifies the output-policy lifecycle, Tax workspace
authorization and tenant isolation, and the two-worker VAT posting race. In the
race, both workers complete while the database retains exactly one journal and
one tax-evidence row for the invoice.

### Input VAT

For `vendor_bill`, `finance_expense`, and `purchase`, the resolver requires an
`input` category. Its mapping contract debits input tax and the non-tax amount,
and credits the total, while enforcing one tax-account mapping and a balanced
journal shape.

This is an implementation-level resolver/mapping capability. The approved local
Finance/Tax test set does **not** contain an end-to-end input-VAT posting test for
all three transaction types, so this document does not claim production-ready
input-VAT processing or filing coverage.

## Isolation, authorization, and idempotency

- Finance and Tax workspaces require authentication and named permissions.
  Lifecycle permissions are separated where the operation is higher risk.
- Company access is checked server-side; cross-company workspace and lifecycle
  requests are rejected. FX source discovery and stored evidence preserve company
  scope, with branch scope where the source supplies a branch.
- Database unique constraints, row/aggregate locks, transactions, and semantic
  retry checks protect rate creation, snapshots, revaluation runs, settlements,
  journals, and tax evidence from duplicate or conflicting writes.
- These controls are tenant/company and, where implemented, branch isolation
  evidence. They do not by themselves certify every Finance/Tax entry point or
  external integration.

## Known limits

- The verified scope does not establish statutory tax filing, Government
  certification, legal compliance, or correctness for every jurisdiction.
- CIT remains a management/preparation boundary: no verified effective CIT
  policy plus tax-adjustment ledger is certified by this local batch. Taxable
  income and estimated CIT must not be represented as official filing amounts.
- Input VAT is limited as described above; external tax services, e-invoice
  transmission, tax-authority acknowledgements, and production credentials were
  not exercised.
- Supplier-side FX migrations and calculator contracts exist, but comprehensive
  end-to-end coverage of every AP/receipt/return/credit/refund path is outside the
  67-test evidence set.
- No phase classification or release closeout is changed by this documentation
  reconciliation.

## Local verification evidence

The following results were obtained locally on the baseline commit in isolated
test runtimes. Database-backed suites used a disposable MySQL 8.4 instance with
the canonical `schema_admin_testing` database identity; generated Vite assets
were isolated from the repository worktree.

| Gate | Result |
| --- | ---: |
| Finance Unit | 26 tests / 52 assertions PASS |
| Tax Unit | 3 tests / 17 assertions PASS |
| Finance Feature | 34 tests / 219 assertions PASS |
| Tax Feature | 3 tests / 15 assertions PASS |
| VAT concurrency | 1 test / 6 assertions PASS |
| **Total** | **67 tests / 309 assertions PASS** |
| PHPStan Level 10 (`app` + `Modules`) | PASS |
| Pint | PASS |

This evidence is local verification only. GitHub Actions was not used for this
batch, and these results are not a production release, deployment approval, or
tax/legal certification.

## Traceable implementation evidence

- FX schema: `database/migrations/2026_10_03_000118_create_finance_fx_foundation.php`
- AR FX evidence: `database/migrations/2026_10_07_000119_create_customer_ar_fx_evidence_foundation.php`
- Supplier FX evidence: migrations `2026_10_09_000120` through
  `2026_10_10_000125`
- FX services: `FxRateService`, `FxRateResolver`, `FxRateSnapshotService`,
  `CustomerInvoiceFxRecognitionService`, and `FxRevaluationService`
- VAT schema: `database/migrations/2026_09_30_000117_create_vat_policy_foundation.php`
- VAT services: `VatPolicyService`, `TaxPolicyResolver`, and `TaxEvidenceService`
- Finance tests: `tests/Unit/Finance` and `tests/Feature/Finance`
- Tax tests: `tests/Unit/Tax`, `tests/Feature/Tax`, and
  `tests/Concurrency/Tax/TaxVatPostingProcessConcurrencyTest.php`

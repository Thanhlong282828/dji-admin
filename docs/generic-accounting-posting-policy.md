# Generic Effective-dated Accounting Posting Policy

Finance owns this non-transactional configuration foundation. It supplies reusable account-selection evidence to future source postings without replacing Payroll-specific policies or external ERP export mappings.

## Model and lifecycle

`accounting_posting_policies` stores company, stable policy key, source type, business event, nullable classification, version, currency, draft/submitted/approved status, effective range, note and maker/checker evidence. `accounting_posting_policy_lines` stores ordered debit/credit roles through canonical Account foreign keys plus the Cost Center requirement.

The lifecycle is `draft → submitted → approved`. The creator cannot approve their own policy. Repeated submit/approve calls are idempotent. The company row is locked during create/approval, the policy key/version is unique, and approval rejects an overlapping approved range for the same company/event/classification/currency.

## Resolution and fail-closed behavior

`AccountingPostingPolicyResolver` accepts company, business event, classification, posting date and currency. It selects an approved policy whose effective range contains the posting date. Zero results raises `ACCOUNTING_POSTING_POLICY_MISSING`; multiple results raises `ACCOUNTING_POSTING_POLICY_AMBIGUOUS`. It returns the immutable policy id/version and ordered Account snapshots for future journal construction.

Accounts must belong to the same Company, be active, allow posting and cover the policy effective range. Roles enforce compatible types: expense→expense, accrual/AP/GRNI→liability, cash/inventory/asset→asset. By default a `SERVICE_*` event cannot use `2150 – Goods Received Not Invoiced` as its `ACCRUAL` line.

## Adoption boundary

Existing Goods Receipt, Vendor Bill, AP Payment, Finance Expense, Asset, Depreciation, Commerce Revenue/COGS and Sales/AR postings remain on their current tested mappings. This phase does not perform a broad migration. A source owner may adopt the resolver only with its own state-transition, period-close, reversal, idempotency and historical snapshot tests.

No policy rows are installed into the operating database by migration. The Service Accrual account and Service policies remain configuration-required. No Service Acceptance, Service Bill, Service journal, payment, or Inventory movement is created.

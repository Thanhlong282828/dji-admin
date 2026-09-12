# Service Procurement Accounting Policy Foundation

## 1. Current state

Service Acceptance is not implemented. Procurement line classification already supports `inventory`, `asset`, and `operating_expense`; this taxonomy should remain authoritative. Goods Receipt and Vendor Bill runtime currently supports only received `inventory` lines and correctly uses the goods-specific bridge `2150 – Goods Received Not Invoiced`.

The operating company has these relevant active, posting-enabled VND accounts:

| Code | Name | Type | Normal balance | Effective |
|---|---|---|---|---|
| 1100 | Payment Clearing | Asset | Debit | 2000-01-01 onward |
| 1200 | Inventory Asset | Asset | Debit | 2000-01-01 onward |
| 1300 | Fixed Assets | Asset | Debit | 2000-01-01 onward |
| 1390 | Accumulated Depreciation | Contra asset | Credit | 2000-01-01 onward |
| 2100 | Accounts Payable | Liability | Credit | 2000-01-01 onward |
| 2150 | Goods Received Not Invoiced | Liability | Credit | 2000-01-01 onward |
| 5100 | Operating Expense | Expense | Debit | 2000-01-01 onward |
| 5200 | Depreciation Expense | Expense | Debit | 2000-01-01 onward |

`1100` is payment-clearing evidence, not verified bank settlement. The only other operating liability besides AP/GRNI is a synthetic employee-payable account; it is not semantically eligible for service accrual. No existing account can legitimately represent Service Received Not Invoiced.

## 2. Policy A — accrual at Service Acceptance

Acceptance posts `Dr configured Service Expense / Cr Service Accrual Liability`. A linked Vendor Bill clears `Dr Service Accrual Liability / Cr 2100 Accounts Payable`. Payment settles `Dr 2100 / Cr 1100` and records the canonical Treasury outflow.

This policy recognizes expense when the service-performance obligation has been evidenced. It gives accurate acceptance-period matching and month-end cut-off, keeps AP separate until an invoice exists, and aligns accounting recognition with Budget Actual. It requires a dedicated accrual account, effective-dated mapping, acceptance/billing lineage, partial reconciliation, reversal handling, and immutable policy snapshots.

## 3. Policy B — expense at Vendor Bill

Acceptance is operational evidence and moves Budget Commitment to Actual, but posts no journal. The Vendor Bill posts `Dr configured Service Expense / Cr 2100 Accounts Payable`; payment remains `Dr 2100 / Cr 1100`.

This is operationally simpler and needs no accrual clearing account. However, when acceptance and invoice occur in different periods, Budget Actual and accounting expense are out of phase. Unbilled accepted services are absent from liabilities and P&L at close, weakening accrual matching and cut-off. Corrections are simpler, but the reduced complexity is obtained by losing economically relevant period evidence.

## 4. Comparison

| Concern | Policy A | Policy B |
|---|---|---|
| Accounting correctness | Accrual basis at evidenced performance | Invoice-date recognition |
| Matching/cut-off | Strong; accepted unbilled service is recognized | Weak when invoice arrives later |
| Acceptance semantics | Financial recognition boundary | Operational-only boundary |
| Budget semantics | Acceptance and accounting Actual align | Budget Actual precedes accounting expense |
| AP semantics | AP starts only at Bill; accrual exists beforehand | AP and expense start together at Bill |
| Complexity | Higher: policy, liability, clearing reconciliation | Lower |
| Schema | Acceptance lineage plus policy snapshot required | Acceptance lineage still required; fewer posting fields |
| Reversal | Reverse acceptance accrual; Bill/payment reversed separately | Acceptance reversal is operational; Bill/payment reversed financially |
| Period close | Complete accepted-unbilled liability | Accepted-unbilled service omitted from GL |
| Partial acceptance | Each approved portion accrues independently | No journal until Bill |
| Partial billing | Clears only linked accepted liability | Recognizes billed portion directly |
| Idempotency | Unique acceptance posting and Bill clearing keys | Unique Bill posting key |
| Auditability | Strong source-to-liability-to-AP trace | Simpler but weaker acceptance-to-GL trace |

## 5. Recommendation

Recommend **Policy A**, subject to explicit Finance approval and account configuration. It best fits the current ERP meaning of Service Acceptance as the point where PO commitment becomes realized Budget Actual, while the existing accounting engine already provides open-period enforcement, immutable posted journals, unique source keys, balanced posting, reversal journals, Cost Center snapshots, AP settlement, and Treasury linkage.

This recommendation is not executable configuration. Runtime remains blocked until the account and policy prerequisites below are approved.

## 6. Required accounts and mapping

The required account semantics are:

- Purpose: Service Received Not Invoiced / Accrued Service Liability.
- Type: `liability`.
- Normal balance: `credit`.
- Posting allowed: yes.
- Currency: VND under the current company/account model; future currencies require separate compatible account/policy evidence rather than implicit conversion.
- Scope: company/tenant-specific, active, and effective-dated.

The liability sequence currently contains `2100` AP and `2150` goods GRNI. A neighboring candidate such as `2160 – Service Received Not Invoiced` is structurally consistent, but **is not approved and must not be created automatically**. Finance must approve the authoritative code/name. `2150` is explicitly ineligible because it identifies physical goods receipt.

Expense mapping should initially use the existing `operating_expense` classification and resolve an approved expense account (currently `5100` is the only general candidate). A service-category taxonomy is not required for correctness. Categories such as connectivity, professional, software, or maintenance service should only be added later if Finance needs different expense-account mapping or operational reporting.

## 7. Effective-dated policy

A generic, non-Payroll accounting policy foundation now exists in Finance through `accounting_posting_policies`, `accounting_posting_policy_lines`, explicit create/submit/approve commands, `AccountingPostingPolicyService`, and `AccountingPostingPolicyResolver`. It does not replace `payroll_accounting_policies`; `erp_mapping_rules` remains an external ERP export mapping rather than a source-posting policy. Existing `SourceAccountingPostingService` callers remain on their proven fixed mappings until each owner flow is migrated deliberately.

The generic contract provides:

- policy key and immutable version;
- company, status, currency, `effective_from`, nullable `effective_to`;
- source event `service_acceptance`;
- procurement classification/category selector;
- debit expense account and credit service-accrual account;
- separate Bill-clearing mapping;
- deterministic effective-date resolution using acceptance date;
- resolved account evidence suitable for a complete mapping snapshot on a future journal/line.

Changing a future policy must not rewrite historical acceptance, journal, Cost Center, Branch, or account snapshots.

## 8. Partial acceptance and billing

For a 10,000,000 VND PO service line under Policy A:

- Acceptance 1 for 4,000,000 posts Dr Expense 4,000,000 / Cr Service Accrual 4,000,000.
- Acceptance 2 for 6,000,000 posts Dr Expense 6,000,000 / Cr Service Accrual 6,000,000.
- Cumulative approved acceptance may never exceed the ordered amount.

If Bill 1 is 6,000,000, it clears 6,000,000 of explicitly linked acceptance lines, leaving 4,000,000 accrued. Bill 2 clears the remaining 4,000,000. The invariant is:

`Service accrual balance = approved accepted value − non-reversed billed value linked to those acceptances`.

No free-text/reference matching is acceptable. Concurrent acceptance and billing require locked source lines, cumulative-limit checks, and unique idempotency/source keys.

## 9. Reversal and period close

Posted journals are immutable. Rejected/draft acceptance creates no journal. Reversing an approved acceptance creates a linked reversal in an open permitted period; it never deletes or edits the original. A Bill reversal restores the linked service accrual and reverses AP. A Payment reversal uses the canonical AP/Treasury/accounting correction path and does not reverse expense a second time.

Acceptance can post only when its accounting period is open. A Bill in a later open period clears the accrual in that Bill period. If the acceptance period is closed, no silent back-date or reopen is allowed; an authorized correction/reversal posts in an eligible open target period using the existing accounting-adjustment governance. The original period and source lineage remain unchanged.

## 10. Budget and Cost Center

PO approval establishes Open Commitment. Approved Service Acceptance moves the accepted amount from Open Commitment to Realized Actual:

`Consumed = Open Commitment + Realized Actual`

`Remaining = Approved Budget − Consumed`

The move does not increase total consumed. Vendor Bill, AP, Payment, Treasury, and accrual clearing do not consume Budget again.

Recognition uses immutable company, Branch, Cost Center, account, and policy snapshots from the accepted PO line. It must never infer historical attribution from the requester's current Employee/Organization assignment.

## 11. Implementation prerequisites

1. Finance approval of the service-accrual account code/name and effective date.
2. Approval of Policy A and the general service expense mapping.
3. Creation and maker-checker approval of the effective Service Acceptance and Service Vendor Bill policies through the generic foundation.
4. Additive Service Acceptance persistence with explicit acceptance-line-to-PO-line lineage and policy/dimension snapshots.
5. Open-period validation, reversal behavior, partial acceptance/billing reconciliation, concurrency and idempotency tests.
6. Vendor Bill lineage to acceptance lines; no billing beyond accepted value.
7. Proof that Service creates zero Inventory movement, quantity/value change, COGS, and goods-GRNI posting.

Until prerequisites 1–3 are approved, Service Procurement remains `SERVICE_PROCUREMENT_ACCOUNT_CONFIGURATION_REQUIRED`; the reusable generic foundation is complete but no Service Acceptance transaction runtime or operating scenario is active.

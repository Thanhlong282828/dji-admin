# Enterprise Empty-menu Operating Batch

## Audit decision

The three empty operator destinations were Project Overview, Projects, and Document Repository. The three sparse destinations were Maintenance Requests, Maintenance Work Orders, and Preventive Maintenance Plans. Maintenance already contains a coherent canonical lifecycle, so no filler maintenance rows were added.

AR and AP aging already read canonical outstanding invoices and bills. CRM already derives customer history from Marketplace, POS, and ERP Sales. No duplicate commercial documents were created.

## Chosen operating scenario

`OPS-PROJECT-ERP-2026` represents an internal transformation project to standardize Procurement, Inventory, Finance, and Dashboard operating data.

Chronology:

1. Project starts 2026-06-03 and moves DRAFT → PLANNED → ACTIVE.
2. The manager and one technical member are linked through canonical Employee identities.
3. A milestone and two dependent tasks preserve execution order.
4. The first task moves TODO → IN_PROGRESS → DONE; the second remains IN_PROGRESS.
5. A 420-minute timesheet on 2026-07-08 is submitted by its employee and approved by a different actor.
6. Project-linked internal workshop materials create a Finance Expense of 1,250,000 VND, submitted and approved through Finance maker-checker, then posted to Accounting with TECH Cost Center evidence.
7. A private project charter document is stored through Enterprise DMS with a SHA-256 version and PROJECT subject linkage.

The project does not own a financial ledger. Its expense remains a Finance-owned transaction; Project only supplies immutable business context. Project Revenue remains not implemented.

## Idempotency

Stable project, task, expense, document, and source keys ensure the second `app:extend-local-operating-data` run creates zero records across every tracked table.

## Safety and limitations

- No recovered record is edited or deleted.
- No historical Cost Center is inferred from current employee state.
- No standalone DMS filler document is created.
- No additional Maintenance, Sales, AR, AP, or CRM transaction is created.
- Service Acceptance remains not implemented; service procurement accounting remains policy-required.

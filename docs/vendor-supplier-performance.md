# Vendor / Supplier Performance

The Vendor Performance workspace is a factual procurement read model shown on the existing **Procurement → Vendors** page. It does not create a second vendor, procurement, AP, inventory, or accounting ledger.

## Canonical lineage

The workspace groups existing records by Vendor:

- Purchase Orders and lines provide PO count, ordered value, classification, expected date, open quantity/value, branch, and cost-center evidence.
- Goods Receipts provide received goods value.
- approved Service Acceptances provide accepted service value.
- Vendor Bills and bill lines provide billed value.
- AP Payment Allocations provide paid, outstanding, and overdue amounts.
- Procurement Exception Workbench rows provide factual exception counts.

All monetary values use the existing integer minor-unit convention. Filters are applied to canonical transaction dimensions; summaries are calculated with grouped database queries rather than per-Vendor queries.

## Goods and services

Goods and services remain distinct:

- `inventory`: receipt evidence comes from Goods Receipt and PO received quantity.
- `operating_expense`: realization evidence comes from approved Service Acceptance.

`received_not_billed` and `accepted_not_billed` never merge these two lifecycles. Open commitment is the unrealized portion of the PO line and is not copied into actual/billed amounts.

## AP interpretation

Paid, outstanding, and overdue are facts about the company's AP settlement state. They must not be interpreted as Vendor quality, reliability, or a Vendor-caused invoice delay.

## Fulfillment timing

On-time/late observations are available only when a Purchase Order has a canonical `expected_date` and a received/approved fulfillment event has its own business date. The workspace compares:

- Goods Receipt `receipt_date` to PO `expected_date`.
- Service Acceptance `acceptance_date` to PO `expected_date`.

No timestamp is inferred from `created_at`. Results are labelled `SUPPORTED_FOR_DATED_OBSERVATIONS_ONLY` and are not an SLA score.

## Evidence limitations

The current owner domains do not provide sufficient canonical evidence for:

- quality score;
- SLA score;
- subjective Vendor rating;
- Vendor-caused invoice delay;
- a Vendor-specific DMS completeness policy.

These fields therefore remain `NOT_ENOUGH_EVIDENCE`; the application does not generate synthetic ratings or default green scores.

## Security and ownership

The read model is scoped by `company_id` and reuses the existing Procurement Vendor route and permission. It is read-only. It performs no business mutation and creates no sidebar destination.

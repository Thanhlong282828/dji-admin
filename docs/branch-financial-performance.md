# Branch Financial Performance

The realistic local dataset supplies deterministic multi-month revenue, operating expenses, effective rent contracts, and branch assets for the main branches. Payroll, rent, and depreciation remain derived from their canonical owning domains.

This is a company/branch-scoped management P&L, not a statutory accounting or tax return.

Sources:

- recognized management revenue ledger with reference, source type, Branch and creator evidence;
- local demo revenue is explicitly marked synthetic and split into deterministic service-delivery, managed-service, and training/support lines;
- unpaid or incomplete Commerce Orders contribute `0`; current Commerce revenue requires succeeded Payment evidence plus delivered Fulfillment;
- approved operating expense ledger;
- effective-dated monthly rent contracts;
- effective primary Branch allocation captured in Payroll Input and eligible Payroll Items;
- approved Branch Bonus proposals;
- Finance-owned asset register with deterministic straight-line management depreciation.

Formula, in integer VND minor units:

`management result = recognized revenue - payroll and employer contributions - approved operating expenses - rent - management depreciation`.

Approved Branch Bonus is already included in Payroll gross. The report exposes it as a disclosure column and never subtracts it twice.

Tax is excluded until a separately verified policy and taxable-income calculation exist. Depreciation is a management estimate and makes no statutory compliance claim. Queries are grouped and bounded by company/month; historical payroll attribution uses its snapshot instead of mutable current access.

Delivered Commerce Orders join the same ledger through `branch_management_revenues.order_id` and `source_type=commerce_order`. Recognition uses the Order subtotal exactly once; the unique Order lineage prevents retry duplication. The deterministic August local dataset retains 4,390,400,000 VND of synthetic management/service revenue and adds 60,460,000 VND from three paid-and-delivered demo Orders. These source classes remain visibly distinguishable and are not described as bank-verified or statutory revenue.

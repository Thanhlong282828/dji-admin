# Branch Financial Performance

The realistic local dataset supplies deterministic multi-month revenue, operating expenses, effective rent contracts, and branch assets for the main branches. Payroll, rent, and depreciation remain derived from their canonical owning domains.

This is a company/branch-scoped management P&L, not a statutory accounting or tax return.

Sources:

- recognized management revenue ledger; pending/cancelled Commerce Orders are not revenue;
- approved operating expense ledger;
- effective-dated monthly rent contracts;
- effective primary Branch allocation captured in Payroll Input and eligible Payroll Items;
- approved Branch Bonus proposals;
- Finance-owned asset register with deterministic straight-line management depreciation.

Formula, in integer VND minor units:

`management result = recognized revenue - payroll and employer contributions - approved operating expenses - rent - approved bonus - management depreciation`.

Tax is excluded until a separately verified policy and taxable-income calculation exist. Depreciation is a management estimate and makes no statutory compliance claim. Queries are grouped and bounded by company/month; historical payroll attribution uses its snapshot instead of mutable current access.

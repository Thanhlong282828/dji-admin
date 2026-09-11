# Current operating data report

`php artisan app:current-operating-data-report` creates `DU_LIEU_THUC_HIEN_TAI.txt` from the active database. It is a read-only operational snapshot, not a seed manifest and not a replacement for owner-module reports.

The command delegates to `CurrentOperatingDataReportService`, uses grouped canonical queries, masks account email addresses, and never selects credentials, password hashes, tokens, session data or private file contents. `--company=<id>` limits company-aware sources and `--output=<path>` selects another destination.

CRM metrics are deterministic reads over paid POS receipts and paid Marketplace orders connected by the customer's normalized email within the same seller Company. Segment thresholds live in `config/commerce.php`: `NEW`, `ACTIVE`, `REPEAT`, `AT_RISK`, `DORMANT`. They are explainable operating classifications, not AI scores, and do not mutate customer records.

The Branch Commerce customer list and detail pages reuse that same `CustomerMetricsService`. Search, Branch and segment filters therefore operate on the canonical customer, paid POS receipt and paid Marketplace Order lineage; the UI does not maintain a parallel CRM score table. Customer detail retains POS visit/receipt evidence and adds matching paid Marketplace Orders for the same Company identity.

The generated company summary reconciles source-owned totals, including Marketplace revenue (`commerce_order`) and POS revenue (`branch_pos`). It also reports lifecycle events, durable notification evidence, Vendor/AP activity and Asset assignment/depreciation. A generic closed-journal adjustment remains `NOT_IMPLEMENTED`; owner-specific Payroll Adjustment and append-only journals are not presented as a generic accounting correction facility.

Known limitations are printed explicitly as `PARTIAL`, `CONFIGURATION_REQUIRED`, `NOT_IMPLEMENTED`, or `KHÔNG ĐỦ BẰNG CHỨNG`. In particular, the report does not invent balancing Equity, Cash Flow, bank settlement, government filing, or unsupported AR.

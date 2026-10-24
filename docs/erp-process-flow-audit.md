# ERP process-flow audit

Audit date: 2026-09-11. Scope: current 122 canonical admin navigation destinations.

## Findings before hardening

- 122/122 destinations resolved a specific contextual guide.
- Process ordering was stored centrally in `AdminHelpRegistry::menuFlowKeys()`.
- The flow existed only inside the `?` dialog and was not visible in normal page content.
- Nodes had no explicit upstream/current/future presentation state, so the active path could not be rendered safely.
- Important flows were difficult to locate from their owner module.
- Brand animation existed on the LA THÀNH wordmark, while process connectors, primary actions and input focus did not share its tokens.

## Corrected business edges

| Flow | Previous representation | Corrected representation | Reason |
|---|---|---|---|
| Sales / AR | Seller menu sequence | Customer → Quotation → Sales Order → Fulfillment → Invoice/AR → Collection → Treasury → Accounting | Stock is issued at fulfillment; collection follows invoice. |
| Procurement / AP | One procurement menu followed by finance pages | Vendor → PR → Approval → PO/GR/AP → Inventory → Payment/Treasury → Accounting | Goods receipt supplies inventory; vendor bill supplies AP; payment supplies Treasury. |
| Payroll | Menu chain without explicit result semantics | Employee/Assignment → Attendance/Leave/OT → Input snapshot → Run → Payslip → Accounting | Finalized result is downstream of immutable input evidence. |
| Cost Center | Navigation sequence | Source event → historical dimension snapshot → Budget/Actual → Cost Center read model → Finance overview | A dashboard reads costs; it does not create them. |
| Workflow | Workflow-owned sequence | Owner transaction → Submit → Task → Inbox → Decision → owner transition → Notification | Workflow orchestrates approval; the owner module performs mutation. |
| Asset / Maintenance | Finance menu before maintenance pages | Asset → Request/Plan → Work Order → Employee → parts/expense → completion | Asset master and financial effects remain with their canonical owners. |
| Project | Overview → project → documents | Project → members → milestones → tasks/dependencies → timesheets → Expense/Procurement → Accounting/Cost Center | Project is not a parallel financial ledger. |

## Coverage after hardening

- Specific contextual guides: 122/122.
- Module-owned rich process definitions: 32 important destinations.
- Remaining destinations: established compatibility definitions, suitable for configuration, master-data and narrower read surfaces.
- Invalid generated route links: 0 by construction; only `AdminNavigation` routes may become links.
- Duplicate Blade flow diagrams: 0.

This iteration intentionally does not claim that all 122 destinations require a long transactional flow. Configuration, master-data, report, control and external-integration pages retain concise lineage help until a richer owner definition is justified.

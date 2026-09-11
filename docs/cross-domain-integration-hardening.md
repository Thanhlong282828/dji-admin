# Cross-domain integration hardening

## Ownership decision

Project and Maintenance remain source domains. Finance owns Expense and Asset; Procurement owns PR/PO/GR/AP; Inventory owns balances and movements; Accounting effects are emitted by the owner flows. No integration-owned ledger or copied total is introduced.

The additive source context (`source_type`, `source_id`, `source_reference`, `source_idempotency_key`) is stored on owner records because the existing Finance and Procurement schemas had no business-origin field. It supports multiple origins without adding nullable `project_id`/`work_order_id` columns across every table.

## Implemented flows

- Project → `CreateProjectExpenseCommand` → `ProjectIntegrationService` → `FinanceOperationsService` → `finance_expenses`.
- Project → `CreateProjectPurchaseRequestCommand` → `ProjectIntegrationService` → `ProcurementService` → `purchase_requests` → existing PO/GR/AP flow.
- Maintenance Work Order → `CreateMaintenanceExpenseCommand` → `MaintenanceIntegrationService` → `FinanceOperationsService`.
- Maintenance Work Order → `CreateMaintenancePurchaseRequestCommand` → `MaintenanceIntegrationService` → `ProcurementService`.
- Maintenance spare parts continue to use `InventoryService`; this integration does not write inventory directly.

Every source action uses an owner-table unique idempotency key. Source lookup is company-scoped before the owner service is called. Linked documents survive completion/archive of the source; no cascade foreign key points back into Project or Maintenance.

## Financial semantics

Project actual cost includes approved/paid source-linked Expense only. Commitment uses the existing Budget convention: unreceived amount of approved/sent/partially-received PO. Expense, Vendor Bill and journal are not added together for one economic event.

Maintenance evidence cost is issued-part movement cost plus approved/paid external Expense. Labor is excluded because no approved labor-cost allocation policy exists. Inventory consumption accounting remains `PARTIAL`; Maintenance does not create shortcut journals.

Project revenue remains `NOT_IMPLEMENTED`: Customer Invoice currently has no verified Project commercial source requirement. Workflow and Notification remain `PARTIAL`; native owner state machines are preserved rather than mass-refactored into generic workflow.

## Runtime controls

- Route permissions remain owner permissions (`finance.operations.manage`, `procurement.manage`).
- Controller actions only dispatch explicit commands.
- Cross-company source IDs are rejected before mutation.
- Data Quality checks dangling/cross-company Project and Work Order source references plus duplicate idempotency references.
- Owner screens remain canonical; Project and Work Order details provide contextual actions and deep links without new Sidebar entries.

<?php

namespace App\Support\DataQuality;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class DataQualityAuditService
{
    public function __construct(private readonly DataQualityDatabaseScope $databaseScope) {}

    /** @return array{environment:string,connection:string,configured_database:string,actual_database:string,database_purpose:string,database_matches:bool,rules:int,passed:int,failed:int,warnings:int,duplicates:int,orphans:int,cross_tenant:int,manager_issues:int,invalid_career_levels:int,attendance_issues:int,payroll_issues:int,compensation_issues:int,commerce_issues:int,identity_issues:int,score:int,checksum:string,findings:list<array{key:string,component:string,severity:string,description:string,count:int,status:string}>} */
    public function snapshot(): array
    {
        $findings = [
            new DataQualityFinding('employee.duplicate_code', 'Employee', 'critical', 'Mã nhân viên bị trùng trong cùng công ty.', $this->duplicateEmployeeCodes()),
            new DataQualityFinding('employee.orphan_user', 'Employee/IAM', 'critical', 'Liên kết tài khoản không còn tồn tại.', $this->orphanEmployeeUsers()),
            new DataQualityFinding('employee.missing_company_access', 'Employee/IAM', 'warning', 'Tài khoản liên kết chưa có quyền truy cập công ty.', $this->missingCompanyAccess()),
            new DataQualityFinding('employee.invalid_manager', 'Employee', 'critical', 'Quản lý tự tham chiếu, khác công ty hoặc không tồn tại.', $this->invalidManagers()),
            new DataQualityFinding('assignment.cross_tenant', 'Organization', 'critical', 'Phân công trỏ sang đơn vị hoặc vị trí khác công ty.', $this->crossTenantAssignments()),
            new DataQualityFinding('assignment.position_unit_mismatch', 'Organization', 'critical', 'Phân công dùng vị trí thuộc đơn vị khác.', $this->positionUnitMismatch()),
            new DataQualityFinding('assignment.duplicate_primary', 'Organization', 'critical', 'Nhân viên có nhiều phân công primary đang hiệu lực.', $this->duplicatePrimaryAssignments()),
            new DataQualityFinding('position.invalid_career_level', 'Organization', 'critical', 'Vị trí công việc phải có career level từ 1 đến 6.', $this->invalidCareerLevels()),
            new DataQualityFinding('attendance.orphan_employee', 'Attendance', 'critical', 'Bản ghi chấm công không có nhân viên hợp lệ cùng công ty.', $this->orphanAttendance()),
            new DataQualityFinding('attendance.invalid_time', 'Attendance', 'critical', 'Check-out sớm hơn check-in.', $this->invalidAttendanceTimes()),
            new DataQualityFinding('payroll.orphan_employee', 'Payroll', 'critical', 'Dữ liệu Payroll không có nhân viên hợp lệ cùng công ty.', $this->orphanPayroll()),
            new DataQualityFinding('payroll.snapshot_count_mismatch', 'Payroll', 'critical', 'Số nhân viên của Payroll snapshot không khớp các input đã chụp.', $this->payrollSnapshotCountMismatch()),
            new DataQualityFinding('payroll.run_count_mismatch', 'Payroll', 'critical', 'Số nhân viên của Payroll run không khớp các payroll item đã tính.', $this->payrollRunCountMismatch()),
            new DataQualityFinding('compensation.invalid_money', 'Payroll', 'critical', 'Salary assignment dạng money phải có amount không âm.', $this->invalidCompensationMoney()),
            new DataQualityFinding('compensation.invalid_currency', 'Payroll', 'critical', 'Salary assignment dạng money phải có currency ISO viết hoa hợp lệ.', $this->invalidCompensationCurrency()),
            new DataQualityFinding('compensation.overlap', 'Payroll', 'critical', 'Salary assignment cùng component không được overlap effective period.', $this->overlappingCompensation()),
            new DataQualityFinding('branch.cross_company_access', 'Commerce/Branch', 'critical', 'Branch access phải thuộc company access của cùng user.', $this->crossCompanyBranchAccess()),
            new DataQualityFinding('seller.orphan_owner', 'Commerce', 'critical', 'Seller profile phải có User owner hợp lệ.', $this->orphanSellers()),
            new DataQualityFinding('store.orphan_seller', 'Commerce', 'critical', 'Store phải có Seller owner hợp lệ.', $this->orphanStores()),
            new DataQualityFinding('media.orphan_owner', 'Media', 'critical', 'Media phải trỏ đến Article, Store, Product, Employee hoặc account owner hợp lệ.', $this->orphanMedia()),
            new DataQualityFinding('media.invalid_delivery_metadata', 'Media', 'critical', 'Media phải có provider asset, delivery URL, MIME, size và checksum hợp lệ.', $this->invalidMediaMetadata()),
            new DataQualityFinding('identity.orphan_user', 'Identity', 'critical', 'External identity phải thuộc User hợp lệ.', $this->orphanExternalIdentities()),
            new DataQualityFinding('identity.duplicate_provider_id', 'Identity', 'critical', 'Provider identity không được liên kết với nhiều User.', $this->duplicateProviderIdentities()),
            new DataQualityFinding('identity.duplicate_verified_phone', 'Identity', 'critical', 'Số điện thoại đã xác minh không được dùng chung.', $this->duplicateVerifiedPhones()),
            new DataQualityFinding('identity.invalid_verified_phone', 'Identity', 'critical', 'Số điện thoại đã xác minh phải ở định dạng E.164 Việt Nam.', $this->invalidVerifiedPhones()),
            new DataQualityFinding('branch.invalid_leader', 'Commerce/Branch', 'critical', 'Lãnh đạo chi nhánh phải là Employee cùng công ty, có account hoạt động và vị trí hiện tại.', $this->invalidBranchLeaders()),
            new DataQualityFinding('branch.leader_missing_access', 'Commerce/Branch', 'critical', 'Lãnh đạo chi nhánh phải có quyền truy cập chi nhánh đó.', $this->branchLeadersMissingAccess()),
            new DataQualityFinding('product.invalid_ownership', 'Commerce/Product', 'critical', 'Product phải thuộc Store/Seller hợp lệ và Variant phải thuộc Product hợp lệ.', $this->invalidProductOwnership()),
            new DataQualityFinding('inventory.invalid_balance', 'Commerce/Inventory', 'critical', 'Inventory phải có on_hand/reserved không âm và reserved không vượt on_hand.', $this->invalidInventoryBalances()),
            new DataQualityFinding('cart.invalid_item', 'Commerce/Cart', 'critical', 'Cart item phải thuộc đúng Product/Variant và có quantity, price snapshot hợp lệ.', $this->invalidCartItems()),
            new DataQualityFinding('wishlist.invalid_item', 'Commerce/Wishlist', 'critical', 'Wishlist item phải trỏ đến Product hợp lệ.', $this->invalidWishlistItems()),
            new DataQualityFinding('buyer_address.invalid_default', 'Commerce/Checkout', 'critical', 'Buyer có địa chỉ phải có đúng một địa chỉ mặc định.', $this->invalidAddressDefaults()),
            new DataQualityFinding('checkout.duplicate_idempotency', 'Commerce/Checkout', 'critical', 'Idempotency key của cùng Buyer không được trùng.', $this->duplicateCheckoutIdempotency()),
            new DataQualityFinding('order.invalid_ownership', 'Commerce/Order', 'critical', 'Order phải thuộc Buyer, Store và Seller owner hợp lệ.', $this->invalidOrderOwnership()),
            new DataQualityFinding('order.total_mismatch', 'Commerce/Order', 'critical', 'Order subtotal/total phải khớp tổng line, shipping và discount.', $this->orderTotalMismatch()),
            new DataQualityFinding('order_item.invalid_line', 'Commerce/Order', 'critical', 'Order item phải có quantity dương và line total khớp snapshot price.', $this->invalidOrderItems()),
            new DataQualityFinding('reservation.invalid_reference', 'Commerce/Inventory', 'critical', 'Reservation phải trỏ đúng Order item/Variant/Balance và có quantity dương.', $this->invalidReservations()),
            new DataQualityFinding('reservation.balance_mismatch', 'Commerce/Inventory', 'critical', 'Tổng reservation active không được vượt inventory reserved.', $this->reservationBalanceMismatch()),
            new DataQualityFinding('reservation.cancelled_active', 'Commerce/Inventory', 'critical', 'Order đã hủy không được còn reservation active.', $this->cancelledActiveReservations()),
            new DataQualityFinding('order.multiple_reservation_branches', 'Commerce/Fulfillment', 'critical', 'Một Store Order phải có inventory reservation thuộc đúng một Branch fulfillment.', $this->ordersWithMultipleReservationBranches()),
            new DataQualityFinding('payment.order_mismatch', 'Commerce/Payment', 'critical', 'Payment succeeded phải khớp amount/currency của Order và mọi Order paid phải có payment evidence.', $this->paymentOrderMismatch()),
            new DataQualityFinding('fulfillment.invalid_branch', 'Commerce/Fulfillment', 'critical', 'Fulfillment Branch phải cùng Company với Seller và inventory location của Order.', $this->invalidFulfillmentBranch()),
            new DataQualityFinding('fulfillment.active_reservation', 'Commerce/Fulfillment', 'critical', 'Order đã shipped/delivered không được còn inventory reservation active.', $this->fulfilledOrderActiveReservations()),
            new DataQualityFinding('revenue.order_lineage_mismatch', 'Commerce/Finance', 'critical', 'Commerce revenue phải đúng Order paid + delivered, đúng Branch, currency và subtotal; Order đủ điều kiện phải có đúng một revenue.', $this->commerceRevenueLineageMismatch()),
            new DataQualityFinding('branch_assignment.cross_tenant', 'HRM/Branch', 'critical', 'Phân công chi nhánh vận hành phải cùng công ty với Employee và Branch.', $this->crossTenantBranchAssignments()),
            new DataQualityFinding('branch_assignment.overlap', 'HRM/Branch', 'critical', 'Employee không được có hai primary Branch assignment chồng thời gian.', $this->overlappingBranchAssignments()),
            new DataQualityFinding('branch_bonus.invalid_scope', 'Payroll/Branch', 'critical', 'Branch bonus phải thuộc đúng Employee, Branch access và company.', $this->invalidBranchBonusScope()),
            new DataQualityFinding('finance.payroll_branch_mismatch', 'Payroll/Finance', 'critical', 'Payroll Item đủ điều kiện phải resolve đúng Branch snapshot để Branch Finance nhận đủ employer cost.', $this->payrollBranchMismatch()),
            new DataQualityFinding('finance.payroll_accounting_mismatch', 'Payroll/Accounting', 'critical', 'Finalized Payroll phải có active journal với debit bằng employer cost và debit bằng credit.', $this->payrollAccountingMismatch()),
            new DataQualityFinding('accounting.duplicate_active_journal', 'Accounting', 'critical', 'Một Payroll Run không được có nhiều active original journal.', $this->duplicateActivePayrollJournals()),
            new DataQualityFinding('accounting.insurance_liability_mismatch', 'Payroll/Insurance', 'critical', 'Insurance liability trong journal phải khớp tổng employee và employer contribution của finalized Payroll.', $this->insuranceLiabilityMismatch()),
            new DataQualityFinding('accounting.pit_liability_mismatch', 'Payroll/Tax', 'critical', 'PIT liability trong journal phải khớp PIT withheld của finalized Payroll.', $this->pitLiabilityMismatch()),
            new DataQualityFinding('seller.invalid_company_scope', 'Commerce/Seller', 'critical', 'Seller Profile phải thuộc Company hợp lệ và owner phải có Company access tương ứng.', $this->invalidSellerCompanyScope()),
            new DataQualityFinding('commerce.order_company_mismatch', 'Commerce/Order', 'critical', 'Order, Seller, inventory Branch, payment, fulfillment và revenue phải cùng Company.', $this->commerceOrderCompanyMismatch()),
            new DataQualityFinding('pos.receipt_total_mismatch', 'POS/Receipt', 'critical', 'POS Receipt phải khớp tổng product/service line snapshot của Visit.', $this->posReceiptTotalMismatch()),
            new DataQualityFinding('pos.receipt_revenue_mismatch', 'POS/Finance', 'critical', 'Paid POS Receipt phải có đúng recognized revenue cùng Company, Branch và amount.', $this->posReceiptRevenueMismatch()),
            new DataQualityFinding('commerce.reversal_lineage_mismatch', 'Commerce/Finance', 'critical', 'Sale reversal phải trỏ đúng revenue gốc, khớp Company/Branch/amount và đã hoàn kho.', $this->saleReversalLineageMismatch()),
            new DataQualityFinding('inventory.movement_balance_mismatch', 'Commerce/Inventory', 'critical', 'Movement delta/before/after và movement cuối phải khớp Inventory Balance hiện tại.', $this->inventoryMovementBalanceMismatch()),
            new DataQualityFinding('media.product_company_mismatch', 'Commerce/Media', 'critical', 'Media Product phải mang cùng company scope với Store/Seller sở hữu Product.', $this->productMediaCompanyMismatch()),
            new DataQualityFinding('recruitment.hire_lineage_mismatch', 'Recruitment/Employee', 'critical', 'Application hired phải nối cùng Employee qua Candidate, accepted Offer và consumed reservation.', $this->recruitmentHireLineageMismatch()),
            new DataQualityFinding('accounting.posted_journal_unbalanced', 'Accounting', 'critical', 'Mọi posted source Journal phải có tổng debit bằng credit và khớp Journal Lines.', $this->postedJournalImbalance()),
            new DataQualityFinding('accounting.duplicate_source_posting', 'Accounting', 'critical', 'Một source/posting type không được tạo nhiều automatic Journal.', $this->duplicateSourcePosting()),
            new DataQualityFinding('procurement.receipt_missing_inventory', 'Procurement/Inventory', 'critical', 'Goods Receipt received phải có đúng Inventory Movement evidence.', $this->receivedGoodsMissingMovement()),
            new DataQualityFinding('inventory.value_mismatch', 'Inventory/Accounting', 'critical', 'Movement value và balance value phải reconcile với quantity/cost snapshot.', $this->inventoryValueMismatch()),
            new DataQualityFinding('commerce.sale_missing_gl', 'Commerce/Accounting', 'critical', 'Sale mới có valued inventory movement phải có Revenue và COGS posting.', $this->saleMissingAccounting()),
            new DataQualityFinding('ap.invalid_allocation', 'Procurement/AP', 'critical', 'AP allocation không được vượt Payment hoặc Vendor Bill.', $this->invalidApAllocation()),
            new DataQualityFinding('accounting.closed_period_posting', 'Accounting', 'critical', 'Journal không được post bình thường vào accounting period đã đóng.', $this->closedPeriodPosting()),
            new DataQualityFinding('commerce.reversal_missing_gl', 'Commerce/Accounting', 'critical', 'Sale reversal có valued restock phải có Revenue và COGS reversal Journal.', $this->reversalMissingAccounting()),
            new DataQualityFinding('finance.expense_missing_gl', 'Finance/Expense', 'critical', 'Expense approved/paid phải có balanced accounting journal đúng Company và Branch.', $this->approvedExpenseMissingAccounting()),
            new DataQualityFinding('finance.depreciation_mismatch', 'Finance/Asset', 'critical', 'Depreciation đã post phải có journal cân và khớp số tiền khấu hao.', $this->depreciationAccountingMismatch()),
            new DataQualityFinding('finance.disposed_future_depreciation', 'Finance/Asset', 'critical', 'Asset đã dispose không được có depreciation sau tháng dispose.', $this->disposedAssetFutureDepreciation()),
            new DataQualityFinding('finance.asset_cross_tenant', 'Finance/Asset', 'critical', 'Asset, Branch, Category và Cost Center phải thuộc cùng Company.', $this->assetCrossTenant()),
            new DataQualityFinding('finance.budget_cross_tenant', 'Finance/Budget', 'critical', 'Budget line chỉ được dùng Branch, Cost Center và Account thuộc Company của Budget.', $this->budgetCrossTenant()),
            new DataQualityFinding('treasury.source_lineage_mismatch', 'Finance/Treasury', 'critical', 'Treasury transaction phải cùng Company/Branch với Financial Account và có balanced source Journal.', $this->treasurySourceLineageMismatch()),
            new DataQualityFinding('treasury.reconciliation_mismatch', 'Finance/Treasury', 'critical', 'Statement reconciled phải trỏ đúng Treasury transaction và khớp signed amount.', $this->treasuryReconciliationMismatch()),
            new DataQualityFinding('accounting.period_closed_with_blocker', 'Finance/Accounting', 'critical', 'Kỳ chỉ được close từ approved request không còn checklist blocker.', $this->periodClosedWithBlocker()),
            new DataQualityFinding('hr.contract_renewal_invalid_lineage', 'HR/Contract', 'critical', 'Gia hạn phải nối hợp đồng cũ/mới cùng Employee và ngày hiệu lực liền sau hợp đồng cũ.', $this->invalidContractRenewalLineage()),
            new DataQualityFinding('hr.offboarding_incomplete_mandatory', 'HR/Offboarding', 'critical', 'Offboarding completed không được còn checklist bắt buộc chưa hoàn tất.', $this->completedOffboardingWithBlockers()),
            new DataQualityFinding('hr.completed_resignation_open_assignment', 'HR/Offboarding', 'critical', 'Resignation completed không được để primary assignment còn mở.', $this->completedResignationWithOpenAssignment()),
            new DataQualityFinding('accounting.adjustment_invalid_posting', 'Finance/Accounting', 'critical', 'Adjustment posted phải có đúng một balanced reversal Journal trong kỳ mở tại thời điểm posting.', $this->invalidAccountingAdjustmentPosting()),
            new DataQualityFinding('sales.invoice_missing_gl', 'Sales/Accounting', 'critical', 'Customer Invoice đã issue phải có balanced AR/Revenue Journal đúng số tiền.', $this->issuedInvoiceMissingAccounting()),
            new DataQualityFinding('sales.invoice_outstanding_mismatch', 'Sales/AR', 'critical', 'Invoice paid/outstanding phải khớp tổng Collection Allocation và không âm.', $this->invoiceOutstandingMismatch()),
            new DataQualityFinding('sales.fulfillment_missing_inventory_cogs', 'Sales/Inventory', 'critical', 'Sales fulfillment vật lý phải có Inventory Movement và immutable COGS snapshot.', $this->salesFulfillmentMissingInventoryOrCogs()),
            new DataQualityFinding('sales.collection_missing_treasury_gl', 'Sales/Treasury', 'critical', 'Collection phải có allocation, Treasury inflow và balanced Accounting Journal cùng company.', $this->collectionLineageMismatch()),
            new DataQualityFinding('sales.ar_gl_mismatch', 'Sales/AR/Accounting', 'critical', 'AR subledger outstanding phải reconcile với posted Accounts Receivable GL balance.', $this->arGeneralLedgerMismatch()),
            new DataQualityFinding('document.missing_current_version', 'Document', 'critical', 'Tài liệu phải trỏ đến một phiên bản hiện tại thuộc chính tài liệu đó.', $this->documentsMissingCurrentVersion()),
            new DataQualityFinding('document.version_missing_sha', 'Document', 'critical', 'Mọi phiên bản tài liệu phải có SHA-256 hợp lệ.', $this->documentVersionsMissingSha()),
            new DataQualityFinding('project.invalid_company_scope', 'Project', 'critical', 'Project manager, branch và member phải thuộc cùng Company.', $this->invalidProjectCompanyScope()),
            new DataQualityFinding('project.invalid_task_link', 'Project', 'critical', 'Task milestone, parent và assignee phải thuộc đúng Project.', $this->invalidProjectTaskLinks()),
            new DataQualityFinding('project.invalid_dependency', 'Project', 'critical', 'Task dependency không được self-reference, cross-project hoặc tạo cycle.', $this->invalidProjectDependencies()),
            new DataQualityFinding('project.invalid_timesheet', 'Project', 'critical', 'Timesheet phải có minutes dương, đúng member/task và approval evidence.', $this->invalidProjectTimesheets()),
            new DataQualityFinding('project.invalid_completion', 'Project', 'critical', 'Project hoàn thành phải có ngày kết thúc và không còn task, milestone hoặc timesheet chờ xử lý.', $this->invalidCompletedProjects()),
            new DataQualityFinding('maintenance.invalid_company_scope', 'Maintenance', 'critical', 'Asset, Branch và technician của Maintenance phải thuộc cùng Company.', $this->invalidMaintenanceScope()),
            new DataQualityFinding('maintenance.invalid_request_conversion', 'Maintenance', 'critical', 'Request CONVERTED phải có đúng một Work Order liên kết.', $this->invalidMaintenanceConversion()),
            new DataQualityFinding('maintenance.invalid_work_order_timeline', 'Maintenance', 'critical', 'Work Order không được có timeline sai hoặc completed thiếu completion evidence.', $this->invalidMaintenanceTimeline()),
            new DataQualityFinding('maintenance.invalid_preventive_occurrence', 'Maintenance', 'critical', 'Mỗi kế hoạch/ngày định kỳ chỉ có một Work Order.', $this->invalidPreventiveOccurrence()),
            new DataQualityFinding('maintenance.part_missing_inventory', 'Maintenance/Inventory', 'critical', 'Spare part ISSUED phải có canonical Inventory Movement khớp source.', $this->maintenancePartMissingInventory()),
            new DataQualityFinding('integration.project_source_invalid', 'Project/Finance/Procurement', 'critical', 'Chứng từ mang nguồn PROJECT phải trỏ đến Project tồn tại cùng Company.', $this->invalidProjectSourceReferences()),
            new DataQualityFinding('integration.maintenance_source_invalid', 'Maintenance/Finance/Procurement', 'critical', 'Chứng từ mang nguồn MAINTENANCE_WORK_ORDER phải trỏ đến Work Order tồn tại cùng Company.', $this->invalidMaintenanceSourceReferences()),
            new DataQualityFinding('integration.duplicate_source_action', 'Cross-domain', 'critical', 'Source idempotency key không được tạo nhiều owner document.', $this->duplicateIntegrationSourceActions()),
            new DataQualityFinding('procurement.invalid_realization_quantity', 'Procurement/Budget', 'critical', 'Received không được vượt ordered và billed không được vượt received.', $this->invalidProcurementRealizationQuantity()),
            new DataQualityFinding('procurement.invalid_budget_dimension', 'Procurement/Budget', 'critical', 'PO line classification, Account và Cost Center phải cùng Company và đúng canonical mapping.', $this->invalidProcurementBudgetDimension()),
        ];
        $rows = array_values(array_map(static fn (DataQualityFinding $finding): array => $finding->toArray(), $findings));
        $failed = count(array_filter($rows, static fn (array $row): bool => $row['count'] > 0 && $row['severity'] === 'critical'));
        $warnings = count(array_filter($rows, static fn (array $row): bool => $row['count'] > 0 && $row['severity'] === 'warning'));
        $scope = $this->databaseScope->metadata();
        $checksum = hash('sha256', json_encode(['scope' => $scope, 'findings' => $rows], JSON_THROW_ON_ERROR));

        return $scope + [
            'rules' => count($rows), 'passed' => count($rows) - $failed - $warnings, 'failed' => $failed, 'warnings' => $warnings,
            'duplicates' => $rows[0]['count'] + $rows[6]['count'],
            'orphans' => $rows[1]['count'] + $rows[8]['count'] + $rows[10]['count'],
            'cross_tenant' => $rows[4]['count'], 'manager_issues' => $rows[3]['count'], 'invalid_career_levels' => $rows[7]['count'],
            'attendance_issues' => $rows[8]['count'] + $rows[9]['count'], 'payroll_issues' => $rows[10]['count'] + $rows[11]['count'] + $rows[12]['count'], 'compensation_issues' => $rows[13]['count'] + $rows[14]['count'] + $rows[15]['count'],
            'commerce_issues' => array_sum(array_column(array_slice($rows, 16, 4), 'count')) + array_sum(array_column(array_slice($rows, 24), 'count')),
            'identity_issues' => $rows[20]['count'] + $rows[21]['count'] + $rows[22]['count'] + $rows[23]['count'],
            'score' => max(0, 100 - ($failed * 10) - ($warnings * 3)), 'checksum' => $checksum, 'findings' => $rows,
        ];
    }

    private function duplicateEmployeeCodes(): int
    {
        $duplicates = DB::table('employees')->select(['company_id', 'employee_code'])->whereNull('deleted_at')->groupBy('company_id', 'employee_code')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_employee_codes')->count();
    }

    private function orphanEmployeeUsers(): int
    {
        return DB::table('employees as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->whereNotNull('e.user_id')->whereNull('u.id')->count();
    }

    private function missingCompanyAccess(): int
    {
        return DB::table('employees as e')->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
            $join->on('cua.company_id', '=', 'e.company_id')->on('cua.user_id', '=', 'e.user_id');
        })->whereNotNull('e.user_id')->whereNull('cua.user_id')->count();
    }

    private function invalidManagers(): int
    {
        return DB::table('employees as e')->leftJoin('employees as m', 'm.id', '=', 'e.manager_id')->whereNotNull('e.manager_id')->where(static function (Builder $query): void {
            $query->whereColumn('e.id', 'e.manager_id')->orWhereNull('m.id')->orWhereColumn('m.company_id', '<>', 'e.company_id');
        })->count();
    }

    private function crossTenantAssignments(): int
    {
        return DB::table('employee_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->where(static function (Builder $query): void {
            $query->whereColumn('e.company_id', '<>', 'o.company_id')->orWhereColumn('e.company_id', '<>', 'p.company_id');
        })->count();
    }

    private function duplicatePrimaryAssignments(): int
    {
        $duplicates = DB::table('employee_assignments')->select('employee_id')->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->groupBy('employee_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_primary_assignments')->count();
    }

    private function positionUnitMismatch(): int
    {
        return DB::table('employee_assignments as a')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->whereNotNull('p.organization_unit_id')->whereColumn('a.organization_unit_id', '<>', 'p.organization_unit_id')->count();
    }

    private function invalidCareerLevels(): int
    {
        return DB::table('job_positions')->where(static fn (Builder $query): Builder => $query->whereNull('career_level')->orWhere('career_level', '<', 1)->orWhere('career_level', '>', 6))->count();
    }

    private function orphanAttendance(): int
    {
        return DB::table('attendance_records as a')->leftJoin('employees as e', 'e.id', '=', 'a.employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'a.company_id'))->count();
    }

    private function invalidAttendanceTimes(): int
    {
        return DB::table('attendance_records')->whereNotNull('check_in_at')->whereNotNull('check_out_at')->whereColumn('check_out_at', '<', 'check_in_at')->count();
    }

    private function orphanPayroll(): int
    {
        return DB::table('payroll_items as p')->leftJoin('employees as e', 'e.id', '=', 'p.employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'p.company_id'))->count();
    }

    private function payrollSnapshotCountMismatch(): int
    {
        $counts = DB::table('payroll_employee_inputs')->selectRaw('payroll_input_snapshot_id, COUNT(*) AS input_count')->groupBy('payroll_input_snapshot_id');

        return DB::table('payroll_input_snapshots as s')->leftJoinSub($counts, 'inputs', 'inputs.payroll_input_snapshot_id', '=', 's.id')->whereRaw('s.employee_count <> COALESCE(inputs.input_count, 0)')->count();
    }

    private function payrollRunCountMismatch(): int
    {
        $counts = DB::table('payroll_items')->selectRaw('payroll_run_id, COUNT(*) AS item_count')->groupBy('payroll_run_id');

        return DB::table('payroll_runs as r')->leftJoinSub($counts, 'items', 'items.payroll_run_id', '=', 'r.id')->where('r.calculation_version', '>', 0)->whereRaw('r.employee_count <> COALESCE(items.item_count, 0)')->count();
    }

    private function invalidCompensationMoney(): int
    {
        $snapshotQuantityKeys = ['weekday_overtime_pay', 'weekend_overtime_pay', 'holiday_overtime_pay', 'unpaid_leave_deduction', 'late_time_deduction'];

        return DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('c.value_type', 'money')->where(static function (Builder $query) use ($snapshotQuantityKeys): void {
            $query->where(static fn (Builder $fixed): Builder => $fixed->where('c.calculation_type', 'fixed')->where(static fn (Builder $amount): Builder => $amount->whereNull('a.amount_minor')->orWhere('a.amount_minor', '<', 0)))
                ->orWhere(static fn (Builder $percentage): Builder => $percentage->where('c.calculation_type', 'percentage')->where(static fn (Builder $rate): Builder => $rate->whereNull('a.percentage_basis_points')->orWhere('a.percentage_basis_points', '<', 0)))
                ->orWhere(static fn (Builder $quantity): Builder => $quantity->where('c.calculation_type', 'quantity_rate')->where(static function (Builder $invalid) use ($snapshotQuantityKeys): void {
                    $invalid->whereNull('a.rate_minor')->orWhere('a.rate_minor', '<', 0)->orWhere(static fn (Builder $missing): Builder => $missing->whereNull('a.quantity_basis_points')->whereNotIn('c.system_key', $snapshotQuantityKeys));
                }));
        })->count();
    }

    private function invalidCompensationCurrency(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('c.value_type', 'money')->where(static fn (Builder $query): Builder => $query->whereNull('a.currency_code')->orWhereRaw("a.currency_code NOT REGEXP '^[A-Z]{3}$'"))->count();
    }

    private function overlappingCompensation(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('employee_salary_component_assignments as b', static function (JoinClause $join): void {
            $join->on('a.company_id', '=', 'b.company_id')->on('a.employee_id', '=', 'b.employee_id')->on('a.salary_component_id', '=', 'b.salary_component_id')->whereColumn('a.id', '<', 'b.id');
        })->where('a.status', 'active')->where('b.status', 'active')->whereRaw('a.effective_from <= COALESCE(b.effective_to, \'9999-12-31\')')->whereRaw('b.effective_from <= COALESCE(a.effective_to, \'9999-12-31\')')->count();
    }

    private function crossCompanyBranchAccess(): int
    {
        return DB::table('branch_user_access as bua')->join('branches as b', 'b.id', '=', 'bua.branch_id')->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
            $join->on('cua.company_id', '=', 'b.company_id')->on('cua.user_id', '=', 'bua.user_id');
        })->whereNull('cua.user_id')->count();
    }

    private function orphanSellers(): int
    {
        return DB::table('seller_profiles as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')->whereNull('u.id')->count();
    }

    private function orphanStores(): int
    {
        return DB::table('stores as s')->leftJoin('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->whereNull('sp.id')->count();
    }

    private function invalidSellerCompanyScope(): int
    {
        return DB::table('seller_profiles as sp')->leftJoin('companies as c', 'c.id', '=', 'sp.company_id')->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
            $join->on('cua.company_id', '=', 'sp.company_id')->on('cua.user_id', '=', 'sp.user_id');
        })->where(static fn (Builder $query): Builder => $query->whereNull('sp.company_id')->orWhereNull('c.id')->orWhereNull('cua.user_id'))->count();
    }

    private function commerceOrderCompanyMismatch(): int
    {
        return DB::table('orders as o')->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')
            ->leftJoin('inventory_reservations as ir', 'ir.order_id', '=', 'o.id')->leftJoin('inventory_balances as ib', 'ib.id', '=', 'ir.inventory_balance_id')->leftJoin('inventory_locations as il', 'il.id', '=', 'ib.inventory_location_id')->leftJoin('branches as b', 'b.id', '=', 'il.branch_id')
            ->leftJoin('commerce_payment_records as pay', 'pay.order_id', '=', 'o.id')->leftJoin('commerce_fulfillments as f', 'f.order_id', '=', 'o.id')->leftJoin('branch_management_revenues as r', 'r.order_id', '=', 'o.id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('sp.company_id')->orWhereNotNull('b.id')->whereColumn('b.company_id', '<>', 'sp.company_id')->orWhereNotNull('pay.id')->whereColumn('pay.company_id', '<>', 'sp.company_id')->orWhereNotNull('f.id')->whereColumn('f.company_id', '<>', 'sp.company_id')->orWhereNotNull('r.id')->whereColumn('r.company_id', '<>', 'sp.company_id'))
            ->distinct('o.id')->count('o.id');
    }

    private function orphanMedia(): int
    {
        $owners = ['article' => 'articles', 'store' => 'stores', 'employee' => 'employees', 'user' => 'users', 'seller' => 'seller_profiles', 'product' => 'products'];
        $count = DB::table('media_assets')->whereNotIn('owner_type', array_keys($owners))->count();
        foreach ($owners as $type => $table) {
            $count += DB::table('media_assets as m')->leftJoin($table.' as o', 'o.id', '=', 'm.owner_id')->where('m.owner_type', $type)->whereNull('o.id')->count();
        }

        return $count;
    }

    private function invalidMediaMetadata(): int
    {
        return DB::table('media_assets')->where(static fn (Builder $query): Builder => $query->whereNull('delivery_url')->orWhere('delivery_url', '')->orWhere('provider_asset_id', '')->orWhereNotIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])->orWhere('size_bytes', '<', 1)->orWhereRaw('CHAR_LENGTH(checksum) <> 64'))->count();
    }

    private function productMediaCompanyMismatch(): int
    {
        return DB::table('media_assets as media')->join('products as product', 'product.id', '=', 'media.owner_id')->join('stores as store', 'store.id', '=', 'product.store_id')->join('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')
            ->where('media.owner_type', 'product')
            ->where(static fn (Builder $query): Builder => $query->whereNull('media.company_id')->orWhereColumn('media.company_id', '<>', 'seller.company_id'))
            ->count();
    }

    private function recruitmentHireLineageMismatch(): int
    {
        return DB::table('applications as application')->join('candidates as candidate', 'candidate.id', '=', 'application.candidate_id')->leftJoin('employees as employee', 'employee.id', '=', 'application.hired_employee_id')->leftJoin('candidate_offers as offer', static function (JoinClause $join): void {
            $join->on('offer.application_id', '=', 'application.id')->where('offer.reservation_status', 'consumed');
        })->where('application.stage', 'hired')->where(static fn (Builder $query): Builder => $query->whereNull('application.hired_employee_id')->orWhereNull('employee.id')->orWhereColumn('candidate.hired_employee_id', '<>', 'application.hired_employee_id')->orWhereNull('offer.id')->orWhereColumn('offer.hired_employee_id', '<>', 'application.hired_employee_id'))->distinct()->count('application.id');
    }

    private function orphanExternalIdentities(): int
    {
        return DB::table('user_external_identities as i')->leftJoin('users as u', 'u.id', '=', 'i.user_id')->whereNull('u.id')->count();
    }

    private function duplicateProviderIdentities(): int
    {
        $duplicates = DB::table('user_external_identities')->select(['provider', 'provider_user_id'])->groupBy('provider', 'provider_user_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_external_identities')->count();
    }

    private function duplicateVerifiedPhones(): int
    {
        $duplicates = DB::table('users')->select('phone_e164')->whereNotNull('phone_verified_at')->whereNotNull('phone_e164')->groupBy('phone_e164')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_verified_phones')->count();
    }

    private function invalidVerifiedPhones(): int
    {
        return DB::table('users')->whereNotNull('phone_verified_at')->where(static fn (Builder $query): Builder => $query->whereNull('phone_e164')->orWhereRaw("phone_e164 NOT REGEXP '^\\+84[1-9][0-9]{8,10}$'"))->count();
    }

    private function invalidBranchLeaders(): int
    {
        return DB::table('branches as b')->leftJoin('employees as e', 'e.id', '=', 'b.leader_employee_id')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->leftJoin('employee_assignments as a', static function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->whereNotNull('b.leader_employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'b.company_id')->orWhereNull('u.id')->orWhere('u.access_enabled', false)->orWhereNull('a.id'))->count();
    }

    private function branchLeadersMissingAccess(): int
    {
        return DB::table('branches as b')->join('employees as e', 'e.id', '=', 'b.leader_employee_id')->leftJoin('branch_user_access as bua', static function (JoinClause $join): void {
            $join->on('bua.branch_id', '=', 'b.id')->on('bua.user_id', '=', 'e.user_id');
        })->whereNotNull('b.leader_employee_id')->whereNull('bua.user_id')->count();
    }

    private function invalidProductOwnership(): int
    {
        $products = DB::table('products as p')
            ->leftJoin('stores as s', 's.id', '=', 'p.store_id')
            ->leftJoin('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('s.id')->orWhereNull('sp.id'))
            ->count();
        $variants = DB::table('product_variants as v')->leftJoin('products as p', 'p.id', '=', 'v.product_id')->whereNull('p.id')->count();

        return $products + $variants;
    }

    private function invalidInventoryBalances(): int
    {
        return DB::table('inventory_balances')->where(static fn (Builder $query): Builder => $query
            ->where('on_hand', '<', 0)
            ->orWhere('reserved', '<', 0)
            ->orWhereColumn('reserved', '>', 'on_hand'))
            ->count();
    }

    private function invalidCartItems(): int
    {
        return DB::table('cart_items as ci')
            ->leftJoin('products as p', 'p.id', '=', 'ci.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'ci.product_variant_id')
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('p.id')
                ->orWhereNull('v.id')
                ->orWhereColumn('v.product_id', '<>', 'ci.product_id')
                ->orWhere('ci.quantity', '<', 1)
                ->orWhere('ci.unit_price_minor', '<', 0))
            ->count();
    }

    private function invalidWishlistItems(): int
    {
        return DB::table('wishlist_items as wi')->leftJoin('products as p', 'p.id', '=', 'wi.product_id')->whereNull('p.id')->count();
    }

    private function invalidAddressDefaults(): int
    {
        $invalid = DB::table('buyer_addresses')->select('user_id')->groupBy('user_id')->havingRaw('SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) <> 1');

        return DB::query()->fromSub($invalid, 'invalid_address_defaults')->count();
    }

    private function duplicateCheckoutIdempotency(): int
    {
        $duplicates = DB::table('checkout_sessions')->select(['buyer_user_id', 'idempotency_key'])->groupBy('buyer_user_id', 'idempotency_key')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_checkout_keys')->count();
    }

    private function invalidOrderOwnership(): int
    {
        return DB::table('orders as o')->leftJoin('users as u', 'u.id', '=', 'o.buyer_user_id')->leftJoin('stores as s', 's.id', '=', 'o.store_id')->leftJoin('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->where(static fn (Builder $query): Builder => $query->whereNull('u.id')->orWhereNull('s.id')->orWhereNull('sp.id')->orWhereColumn('s.seller_profile_id', '<>', 'o.seller_profile_id'))->count();
    }

    private function orderTotalMismatch(): int
    {
        $lines = DB::table('order_items')->selectRaw('order_id, SUM(line_total_minor) AS line_total')->groupBy('order_id');

        return DB::table('orders as o')->leftJoinSub($lines, 'lines', 'lines.order_id', '=', 'o.id')->whereRaw('o.subtotal_minor <> COALESCE(lines.line_total, 0) OR o.total_minor <> o.subtotal_minor + o.shipping_minor - o.discount_minor')->count();
    }

    private function invalidOrderItems(): int
    {
        return DB::table('order_items as oi')->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')->where(static fn (Builder $query): Builder => $query->whereNull('o.id')->orWhere('oi.quantity', '<', 1)->orWhereRaw('oi.line_total_minor <> oi.unit_price_minor * oi.quantity'))->count();
    }

    private function invalidReservations(): int
    {
        return DB::table('inventory_reservations as r')->leftJoin('order_items as oi', 'oi.id', '=', 'r.order_item_id')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')->leftJoin('inventory_balances as ib', 'ib.id', '=', 'r.inventory_balance_id')->where(static fn (Builder $query): Builder => $query->whereNull('oi.id')->orWhereNull('o.id')->orWhereNull('ib.id')->orWhere('r.quantity', '<', 1)->orWhereColumn('oi.order_id', '<>', 'r.order_id')->orWhereColumn('oi.product_variant_id', '<>', 'r.product_variant_id')->orWhereColumn('ib.product_variant_id', '<>', 'r.product_variant_id'))->count();
    }

    private function reservationBalanceMismatch(): int
    {
        $reserved = DB::table('inventory_reservations')->selectRaw('inventory_balance_id, SUM(quantity) AS quantity')->where('status', 'reserved')->groupBy('inventory_balance_id');

        return DB::table('inventory_balances as ib')->joinSub($reserved, 'r', 'r.inventory_balance_id', '=', 'ib.id')->whereColumn('r.quantity', '>', 'ib.reserved')->count();
    }

    private function cancelledActiveReservations(): int
    {
        return DB::table('inventory_reservations as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('o.status', 'cancelled')->where('r.status', 'reserved')->count();
    }

    private function ordersWithMultipleReservationBranches(): int
    {
        $invalid = DB::table('inventory_reservations as r')->join('inventory_balances as ib', 'ib.id', '=', 'r.inventory_balance_id')
            ->join('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')->select('r.order_id')->groupBy('r.order_id')
            ->havingRaw('COUNT(DISTINCT l.branch_id) <> 1 OR SUM(CASE WHEN l.branch_id IS NULL THEN 1 ELSE 0 END) > 0');

        return DB::query()->fromSub($invalid, 'invalid_order_fulfillment_branches')->count();
    }

    private function paymentOrderMismatch(): int
    {
        $invalidEvidence = DB::table('commerce_payment_records as p')->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.status', 'succeeded')->where(static fn (Builder $query): Builder => $query
            ->whereColumn('p.amount_minor', '<>', 'o.total_minor')
            ->orWhereColumn('p.currency', '<>', 'o.currency')
            ->orWhere('o.payment_status', '<>', 'paid'))
            ->count();
        $paidWithoutEvidence = DB::table('orders as o')->leftJoin('commerce_payment_records as p', static function (JoinClause $join): void {
            $join->on('p.order_id', '=', 'o.id')->where('p.status', 'succeeded');
        })->where('o.payment_status', 'paid')->whereNull('p.id')->count();

        return $invalidEvidence + $paidWithoutEvidence;
    }

    private function invalidFulfillmentBranch(): int
    {
        return DB::table('commerce_fulfillments as f')
            ->join('orders as o', 'o.id', '=', 'f.order_id')
            ->join('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')
            ->join('branches as b', 'b.id', '=', 'f.branch_id')
            ->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
                $join->on('cua.company_id', '=', 'f.company_id')->on('cua.user_id', '=', 'sp.user_id');
            })
            ->leftJoin('inventory_reservations as r', 'r.order_id', '=', 'o.id')
            ->leftJoin('inventory_balances as ib', 'ib.id', '=', 'r.inventory_balance_id')
            ->leftJoin('inventory_locations as l', 'l.id', '=', 'ib.inventory_location_id')
            ->where(static fn (Builder $query): Builder => $query
                ->whereColumn('b.company_id', '<>', 'f.company_id')
                ->orWhereNull('cua.user_id')
                ->orWhereNull('l.branch_id')
                ->orWhereColumn('l.branch_id', '<>', 'f.branch_id'))
            ->distinct('f.id')->count('f.id');
    }

    private function fulfilledOrderActiveReservations(): int
    {
        return DB::table('commerce_fulfillments as f')->join('inventory_reservations as r', 'r.order_id', '=', 'f.order_id')
            ->whereIn('f.status', ['shipped', 'delivered'])->where('r.status', 'reserved')->count();
    }

    private function commerceRevenueLineageMismatch(): int
    {
        $invalidRevenue = DB::table('branch_management_revenues as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('commerce_fulfillments as f', 'f.order_id', '=', 'o.id')->where('r.source_type', 'commerce_order')
            ->where(static fn (Builder $query): Builder => $query
                ->where('o.payment_status', '<>', 'paid')
                ->orWhere('f.status', '<>', 'delivered')
                ->orWhereColumn('r.branch_id', '<>', 'f.branch_id')
                ->orWhereColumn('r.company_id', '<>', 'f.company_id')
                ->orWhereColumn('r.amount_minor', '<>', 'o.subtotal_minor')
                ->orWhereColumn('r.currency_code', '<>', 'o.currency'))
            ->count();
        $eligibleWithoutRevenue = DB::table('orders as o')->join('commerce_fulfillments as f', 'f.order_id', '=', 'o.id')
            ->leftJoin('branch_management_revenues as r', static function (JoinClause $join): void {
                $join->on('r.order_id', '=', 'o.id')->where('r.source_type', 'commerce_order');
            })->where('o.payment_status', 'paid')->where('f.status', 'delivered')->whereNull('r.id')->count();

        return $invalidRevenue + $eligibleWithoutRevenue;
    }

    private function crossTenantBranchAssignments(): int
    {
        return DB::table('employee_branch_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('branches as b', 'b.id', '=', 'a.branch_id')->where(static fn (Builder $query): Builder => $query->whereColumn('a.company_id', '<>', 'e.company_id')->orWhereColumn('a.company_id', '<>', 'b.company_id'))->count();
    }

    private function overlappingBranchAssignments(): int
    {
        return DB::table('employee_branch_assignments as a')->join('employee_branch_assignments as b', static function (JoinClause $join): void {
            $join->on('a.company_id', '=', 'b.company_id')->on('a.employee_id', '=', 'b.employee_id')->on('a.assignment_type', '=', 'b.assignment_type')->whereColumn('a.id', '<', 'b.id');
        })->where('a.assignment_type', 'primary')->whereRaw("a.effective_from <= COALESCE(b.effective_to, '9999-12-31')")->whereRaw("b.effective_from <= COALESCE(a.effective_to, '9999-12-31')")->count();
    }

    private function invalidBranchBonusScope(): int
    {
        return DB::table('branch_bonus_proposals as p')->join('branches as b', 'b.id', '=', 'p.branch_id')->join('employees as e', 'e.id', '=', 'p.employee_id')->leftJoin('employee_branch_assignments as a', static function (JoinClause $join): void {
            $join->on('a.branch_id', '=', 'p.branch_id')->on('a.employee_id', '=', 'p.employee_id')->on('a.company_id', '=', 'p.company_id')->where('a.assignment_type', 'primary')->whereColumn('a.effective_from', '<=', 'p.effective_period')->where(static fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereColumn('a.effective_to', '>=', 'p.effective_period'));
        })->where(static fn (Builder $query): Builder => $query->whereColumn('p.company_id', '<>', 'b.company_id')->orWhereColumn('p.company_id', '<>', 'e.company_id')->orWhereNull('a.id'))->count();
    }

    private function payrollBranchMismatch(): int
    {
        return DB::table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->leftJoin('payroll_employee_inputs as pi', static function (JoinClause $join): void {
            $join->on('pi.employee_id', '=', 'i.employee_id')->on('pi.payroll_input_snapshot_id', '=', 'r.payroll_input_snapshot_id');
        })->whereIn('r.status', ['calculated', 'submitted', 'approved', 'finalized', 'locked', 'paid'])->whereNull('pi.branch_id')->count();
    }

    private function payrollAccountingMismatch(): int
    {
        $items = DB::table('payroll_items')->selectRaw('payroll_run_id, SUM(gross_amount_minor + employer_contribution_amount_minor) expected')->groupBy('payroll_run_id');
        $journals = DB::table('payroll_accounting_journals')->selectRaw('payroll_run_id, MAX(total_debit_minor) debit, MAX(total_credit_minor) credit')->whereNull('reversal_of_journal_id')->whereNull('reversed_by_journal_id')->groupBy('payroll_run_id');

        return DB::table('payroll_runs as r')->joinSub($items, 'i', 'i.payroll_run_id', '=', 'r.id')->leftJoinSub($journals, 'j', 'j.payroll_run_id', '=', 'r.id')->whereIn('r.status', ['finalized', 'locked', 'paid'])->where(static fn (Builder $query): Builder => $query->whereNull('j.payroll_run_id')->orWhereColumn('i.expected', '<>', 'j.debit')->orWhereColumn('j.debit', '<>', 'j.credit'))->count();
    }

    private function duplicateActivePayrollJournals(): int
    {
        $duplicates = DB::table('payroll_accounting_journals')->select('payroll_run_id')->whereNotNull('payroll_run_id')->whereNull('reversal_of_journal_id')->whereNull('reversed_by_journal_id')->groupBy('payroll_run_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_active_payroll_journals')->count();
    }

    private function insuranceLiabilityMismatch(): int
    {
        $items = DB::table('payroll_items')->selectRaw('payroll_run_id, SUM(insurance_employee_total_minor + insurance_employer_total_minor) expected')->groupBy('payroll_run_id');
        $journal = DB::table('payroll_accounting_journals as j')->join('payroll_accounting_journal_lines as l', 'l.journal_id', '=', 'j.id')->whereNull('j.reversal_of_journal_id')->whereNull('j.reversed_by_journal_id')->whereIn('l.source_type', ['employee_insurance', 'employer_insurance_liability'])->selectRaw('j.payroll_run_id, SUM(l.credit_minor) actual')->groupBy('j.payroll_run_id');

        return DB::table('payroll_runs as r')->joinSub($items, 'i', 'i.payroll_run_id', '=', 'r.id')->leftJoinSub($journal, 'j', 'j.payroll_run_id', '=', 'r.id')->whereIn('r.status', ['finalized', 'locked', 'paid'])->whereRaw('i.expected <> COALESCE(j.actual, 0)')->count();
    }

    private function pitLiabilityMismatch(): int
    {
        $items = DB::table('payroll_items')->selectRaw('payroll_run_id, SUM(personal_income_tax_minor) expected')->groupBy('payroll_run_id');
        $journal = DB::table('payroll_accounting_journals as j')->join('payroll_accounting_journal_lines as l', 'l.journal_id', '=', 'j.id')->whereNull('j.reversal_of_journal_id')->whereNull('j.reversed_by_journal_id')->where('l.source_type', 'personal_income_tax')->selectRaw('j.payroll_run_id, SUM(l.credit_minor) actual')->groupBy('j.payroll_run_id');

        return DB::table('payroll_runs as r')->joinSub($items, 'i', 'i.payroll_run_id', '=', 'r.id')->leftJoinSub($journal, 'j', 'j.payroll_run_id', '=', 'r.id')->whereIn('r.status', ['finalized', 'locked', 'paid'])->whereRaw('i.expected <> COALESCE(j.actual, 0)')->count();
    }

    private function posReceiptTotalMismatch(): int
    {
        $services = DB::table('customer_visit_services')->selectRaw('customer_visit_id, SUM(line_total_minor) total')->groupBy('customer_visit_id');
        $products = DB::table('customer_visit_products')->selectRaw('customer_visit_id, SUM(line_total_minor) total')->groupBy('customer_visit_id');

        return DB::table('branch_pos_receipts as r')->leftJoinSub($services, 's', 's.customer_visit_id', '=', 'r.customer_visit_id')->leftJoinSub($products, 'p', 'p.customer_visit_id', '=', 'r.customer_visit_id')->whereRaw('r.service_total_minor <> COALESCE(s.total, 0) OR r.product_total_minor <> COALESCE(p.total, 0) OR r.total_minor <> COALESCE(s.total, 0) + COALESCE(p.total, 0)')->count();
    }

    private function posReceiptRevenueMismatch(): int
    {
        return DB::table('branch_pos_receipts as receipt')->leftJoin('branch_management_revenues as revenue', 'revenue.id', '=', 'receipt.revenue_id')->where('receipt.status', 'paid')->where(static fn (Builder $query): Builder => $query->whereNull('revenue.id')->orWhere('revenue.source_type', '<>', 'branch_pos')->orWhere('revenue.status', '<>', 'recognized')->orWhereColumn('revenue.company_id', '<>', 'receipt.company_id')->orWhereColumn('revenue.branch_id', '<>', 'receipt.branch_id')->orWhereColumn('revenue.amount_minor', '<>', 'receipt.total_minor'))->count();
    }

    private function inventoryMovementBalanceMismatch(): int
    {
        $invalidRows = DB::table('inventory_movements')->whereRaw('quantity_delta <> CAST(on_hand_after AS SIGNED) - CAST(on_hand_before AS SIGNED)')->count();
        $latestIds = DB::table('inventory_movements')->selectRaw('product_variant_id, inventory_location_id, MAX(id) movement_id')->groupBy('product_variant_id', 'inventory_location_id');
        $latestMismatch = DB::table('inventory_balances as balance')->joinSub($latestIds, 'latest', static function (JoinClause $join): void {
            $join->on('latest.product_variant_id', '=', 'balance.product_variant_id')->on('latest.inventory_location_id', '=', 'balance.inventory_location_id');
        })->join('inventory_movements as movement', 'movement.id', '=', 'latest.movement_id')->whereColumn('movement.on_hand_after', '<>', 'balance.on_hand')->count();

        return $invalidRows + $latestMismatch;
    }

    private function saleReversalLineageMismatch(): int
    {
        $base = DB::table('commerce_sale_reversals as reversal')->join('branch_management_revenues as revenue', 'revenue.id', '=', 'reversal.original_revenue_id')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('reversal.company_id', '<>', 'revenue.company_id')->orWhereColumn('reversal.branch_id', '<>', 'revenue.branch_id')->orWhereColumn('reversal.amount_minor', '<>', 'revenue.amount_minor')->orWhere('reversal.status', '<>', 'completed')->orWhere('reversal.inventory_restocked', false))->count();
        $invalidPos = DB::table('commerce_sale_reversals as reversal')->leftJoin('branch_pos_receipts as receipt', static function (JoinClause $join): void {
            $join->on('receipt.id', '=', 'reversal.source_id')->on('receipt.revenue_id', '=', 'reversal.original_revenue_id');
        })->where('reversal.source_type', 'pos')->whereNull('receipt.id')->count();
        $invalidMarketplace = DB::table('commerce_sale_reversals as reversal')->leftJoin('branch_management_revenues as revenue', static function (JoinClause $join): void {
            $join->on('revenue.id', '=', 'reversal.original_revenue_id')->on('revenue.order_id', '=', 'reversal.source_id');
        })->where('reversal.source_type', 'marketplace')->whereNull('revenue.id')->count();
        $unknown = DB::table('commerce_sale_reversals')->whereNotIn('source_type', ['marketplace', 'pos'])->count();

        return $base + $invalidPos + $invalidMarketplace + $unknown;
    }

    private function postedJournalImbalance(): int
    {
        $lines = DB::table('payroll_accounting_journal_lines')->selectRaw('journal_id, SUM(debit_minor) debit, SUM(credit_minor) credit')->groupBy('journal_id');

        return DB::table('payroll_accounting_journals as j')->leftJoinSub($lines, 'l', 'l.journal_id', '=', 'j.id')->whereIn('j.status', ['internally_posted', 'exported'])->whereRaw('j.total_debit_minor <> COALESCE(l.debit,0) OR j.total_credit_minor <> COALESCE(l.credit,0) OR j.total_debit_minor <> j.total_credit_minor')->count();
    }

    private function duplicateSourcePosting(): int
    {
        $duplicates = DB::table('payroll_accounting_journals')->whereNotNull('generic_source_key')->select('generic_source_key')->groupBy('generic_source_key')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_source_posting')->count();
    }

    private function receivedGoodsMissingMovement(): int
    {
        return DB::table('procurement_goods_receipts as g')->join('procurement_goods_receipt_lines as l', 'l.goods_receipt_id', '=', 'g.id')->leftJoin('inventory_movements as m', 'm.id', '=', 'l.inventory_movement_id')->where('g.status', 'received')->where(static fn (Builder $query): Builder => $query->whereNull('m.id')->orWhere('m.source_type', '<>', 'procurement_goods_receipt_line')->orWhereColumn('m.source_id', '<>', 'l.id'))->count();
    }

    private function inventoryValueMismatch(): int
    {
        $invalid = DB::table('inventory_movements')->whereRaw('CAST(movement_value_minor AS SIGNED) <> CAST(quantity_delta AS SIGNED) * CAST(unit_cost_minor AS SIGNED) OR CAST(balance_value_after_minor AS SIGNED) <> CAST(balance_value_before_minor AS SIGNED) + CAST(movement_value_minor AS SIGNED)')->count();
        $latest = DB::table('inventory_movements')->selectRaw('product_variant_id, inventory_location_id, MAX(id) movement_id')->groupBy('product_variant_id', 'inventory_location_id');
        $balance = DB::table('inventory_balances as b')->joinSub($latest, 'x', static function (JoinClause $join): void {
            $join->on('x.product_variant_id', '=', 'b.product_variant_id')->on('x.inventory_location_id', '=', 'b.inventory_location_id');
        })->join('inventory_movements as m', 'm.id', '=', 'x.movement_id')->whereColumn('m.balance_value_after_minor', '<>', 'b.inventory_value_minor')->count();

        return $invalid + $balance;
    }

    private function saleMissingAccounting(): int
    {
        $pos = DB::table('inventory_movements as m')->join('branch_pos_receipts as r', 'r.customer_visit_id', '=', 'm.source_id')->where('m.source_type', 'branch_pos_visit')->whereNotNull('m.occurred_at')->where('m.movement_value_minor', '<>', 0)->where(static fn (Builder $query): Builder => $query->whereNotExists(static fn (Builder $sub): Builder => $sub->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.source_id', 'r.id')->where('j.source_type', 'pos_receipt')->where('j.posting_type', 'pos_revenue'))->orWhereNotExists(static fn (Builder $sub): Builder => $sub->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.source_id', 'r.id')->where('j.source_type', 'pos_receipt')->where('j.posting_type', 'pos_cogs')))->count();
        $marketplace = DB::table('inventory_movements as m')->where('m.source_type', 'commerce_order')->whereNotNull('m.occurred_at')->where('m.movement_value_minor', '<>', 0)->where(static fn (Builder $query): Builder => $query->whereNotExists(static fn (Builder $sub): Builder => $sub->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.source_id', 'm.source_id')->where('j.source_type', 'marketplace_order')->where('j.posting_type', 'marketplace_revenue'))->orWhereNotExists(static fn (Builder $sub): Builder => $sub->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.source_id', 'm.source_id')->where('j.source_type', 'marketplace_order')->where('j.posting_type', 'marketplace_cogs')))->count();

        return $pos + $marketplace;
    }

    private function invalidApAllocation(): int
    {
        $payments = DB::table('ap_payment_allocations')->selectRaw('payment_id, SUM(allocated_minor) allocated')->groupBy('payment_id');
        $bills = DB::table('ap_payment_allocations')->selectRaw('vendor_bill_id, SUM(allocated_minor) allocated')->groupBy('vendor_bill_id');

        return DB::table('ap_payments as p')->joinSub($payments, 'a', 'a.payment_id', '=', 'p.id')->whereColumn('a.allocated', '>', 'p.amount_minor')->count()
            + DB::table('vendor_bills as b')->joinSub($bills, 'a', 'a.vendor_bill_id', '=', 'b.id')->whereColumn('a.allocated', '>', 'b.total_minor')->count();
    }

    private function closedPeriodPosting(): int
    {
        return DB::table('payroll_accounting_journals as j')->join('accounting_periods as p', 'p.id', '=', 'j.accounting_period_id')->whereIn('j.status', ['internally_posted', 'exported'])->where('p.status', '<>', 'open')->whereColumn('j.created_at', '>', 'p.closed_at')->count();
    }

    private function reversalMissingAccounting(): int
    {
        return DB::table('commerce_sale_reversals as r')->join('inventory_movements as m', static function (JoinClause $join): void {
            $join->on('m.source_id', '=', 'r.id')->where('m.source_type', 'sale_reversal');
        })->whereNotNull('m.occurred_at')->where('m.movement_value_minor', '>', 0)->where(static fn (Builder $query): Builder => $query->whereNotExists(static fn (Builder $sub): Builder => $sub->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.source_id', 'r.id')->where('j.source_type', 'sale_reversal')->whereRaw("j.posting_type = CONCAT(r.source_type, '_revenue_reversal')"))->orWhereNotExists(static fn (Builder $sub): Builder => $sub->selectRaw('1')->from('payroll_accounting_journals as j')->whereColumn('j.source_id', 'r.id')->where('j.source_type', 'sale_reversal')->whereRaw("j.posting_type = CONCAT(r.source_type, '_cogs_reversal')")))->count();
    }

    private function approvedExpenseMissingAccounting(): int
    {
        return DB::table('finance_expenses as e')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'e.accounting_journal_id')->whereIn('e.status', ['approved', 'paid'])->where(static fn (Builder $query): Builder => $query->whereNull('j.id')->orWhereColumn('j.company_id', '<>', 'e.company_id')->orWhereColumn('j.branch_id', '<>', 'e.branch_id')->orWhereColumn('j.total_debit_minor', '<>', 'e.amount_minor')->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
    }

    private function depreciationAccountingMismatch(): int
    {
        return DB::table('finance_depreciation_entries as d')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'd.accounting_journal_id')->where(static fn (Builder $query): Builder => $query->whereNull('j.id')->orWhereColumn('j.company_id', '<>', 'd.company_id')->orWhereColumn('j.total_debit_minor', '<>', 'd.amount_minor')->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
    }

    private function disposedAssetFutureDepreciation(): int
    {
        return DB::table('finance_assets as a')->join('finance_depreciation_entries as d', 'd.asset_id', '=', 'a.id')->where('a.status', 'disposed')->whereRaw('d.period_month > DATE_FORMAT(a.disposed_on, "%Y-%m-01")')->count();
    }

    private function assetCrossTenant(): int
    {
        return DB::table('finance_assets as a')->join('branches as b', 'b.id', '=', 'a.branch_id')->join('finance_asset_categories as c', 'c.id', '=', 'a.asset_category_id')->leftJoin('cost_centers as cc', 'cc.id', '=', 'a.cost_center_id')->where(static fn (Builder $query): Builder => $query->whereColumn('b.company_id', '<>', 'a.company_id')->orWhereColumn('c.company_id', '<>', 'a.company_id')->orWhere(static fn (Builder $nested): Builder => $nested->whereNotNull('a.cost_center_id')->whereColumn('cc.company_id', '<>', 'a.company_id')))->count();
    }

    private function budgetCrossTenant(): int
    {
        return DB::table('finance_budget_lines as l')->join('finance_budgets as b', 'b.id', '=', 'l.budget_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->leftJoin('branches as br', 'br.id', '=', 'l.branch_id')->leftJoin('cost_centers as cc', 'cc.id', '=', 'l.cost_center_id')->where(static fn (Builder $query): Builder => $query->whereColumn('a.company_id', '<>', 'b.company_id')->orWhere(static fn (Builder $nested): Builder => $nested->whereNotNull('l.branch_id')->whereColumn('br.company_id', '<>', 'b.company_id'))->orWhere(static fn (Builder $nested): Builder => $nested->whereNotNull('l.cost_center_id')->whereColumn('cc.company_id', '<>', 'b.company_id')))->count();
    }

    private function treasurySourceLineageMismatch(): int
    {
        return DB::table('treasury_transactions as t')->join('treasury_financial_accounts as f', 'f.id', '=', 't.financial_account_id')->leftJoin('branches as b', 'b.id', '=', 't.branch_id')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 't.accounting_journal_id')->where(static fn (Builder $query): Builder => $query->whereColumn('f.company_id', '<>', 't.company_id')->orWhere(static fn (Builder $q): Builder => $q->whereNotNull('t.branch_id')->where(static fn (Builder $bq): Builder => $bq->whereNull('b.id')->orWhereColumn('b.company_id', '<>', 't.company_id')))->orWhereNull('j.id')->orWhereColumn('j.company_id', '<>', 't.company_id')->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
    }

    private function treasuryReconciliationMismatch(): int
    {
        return DB::table('treasury_statement_lines as l')->leftJoin('treasury_transactions as t', 't.id', '=', 'l.treasury_transaction_id')->where('l.status', 'reconciled')->where(static fn (Builder $query): Builder => $query->whereNull('t.id')->orWhereColumn('t.company_id', '<>', 'l.company_id')->orWhereRaw("l.amount_minor <> CASE WHEN t.direction = 'outflow' THEN -t.amount_minor ELSE t.amount_minor END"))->count();
    }

    private function periodClosedWithBlocker(): int
    {
        return DB::table('accounting_periods as p')->whereIn('p.status', ['soft_closed', 'closed'])->whereExists(static fn (Builder $request): Builder => $request->selectRaw('1')->from('accounting_period_close_requests as any_request')->whereColumn('any_request.accounting_period_id', 'p.id'))->whereNotExists(static function (Builder $query): void {
            $query->selectRaw('1')->from('accounting_period_close_requests as r')->whereColumn('r.accounting_period_id', 'p.id')->where('r.status', 'approved')->whereNotExists(static fn (Builder $checks): Builder => $checks->selectRaw('1')->from('accounting_period_close_checks as c')->whereColumn('c.close_request_id', 'r.id')->where('c.status', 'blocked'));
        })->count();
    }

    private function invalidContractRenewalLineage(): int
    {
        return DB::table('employment_contract_renewals as r')->join('employment_contracts as old', 'old.id', '=', 'r.previous_contract_id')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('employment_contracts as new', 'new.id', '=', 'r.new_contract_id')->where(static fn (Builder $query): Builder => $query->whereColumn('e.company_id', '<>', 'r.company_id')->orWhereColumn('old.employee_id', '<>', 'r.employee_id')->orWhereRaw('r.effective_date <> DATE_ADD(old.ends_at, INTERVAL 1 DAY)')->orWhere(static fn (Builder $approved): Builder => $approved->where('r.status', 'approved')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('new.id')->orWhereColumn('new.employee_id', '<>', 'r.employee_id')->orWhereColumn('new.starts_at', '<>', 'r.effective_date'))))->count();
    }

    private function completedOffboardingWithBlockers(): int
    {
        return DB::table('employee_offboarding_cases as c')->where('c.status', 'completed')->whereExists(static fn (Builder $items): Builder => $items->selectRaw('1')->from('employee_offboarding_items as i')->whereColumn('i.offboarding_case_id', 'c.id')->where('i.mandatory', true)->where('i.status', '<>', 'completed'))->count();
    }

    private function completedResignationWithOpenAssignment(): int
    {
        return DB::table('employee_resignations as r')->where('r.status', 'completed')->whereExists(static fn (Builder $assignments): Builder => $assignments->selectRaw('1')->from('employee_assignments as a')->whereColumn('a.employee_id', 'r.employee_id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to'))->count();
    }

    private function invalidAccountingAdjustmentPosting(): int
    {
        return DB::table('accounting_adjustment_requests as r')->leftJoin('payroll_accounting_journals as result', 'result.id', '=', 'r.resulting_journal_id')->leftJoin('payroll_accounting_journals as original', 'original.id', '=', 'r.original_journal_id')->where('r.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('result.id')->orWhereColumn('result.company_id', '<>', 'r.company_id')->orWhereColumn('result.reversal_of_journal_id', '<>', 'original.id')->orWhereColumn('result.total_debit_minor', '<>', 'result.total_credit_minor')->orWhereColumn('result.total_debit_minor', '<>', 'original.total_credit_minor')->orWhereColumn('result.total_credit_minor', '<>', 'original.total_debit_minor'))->count();
    }

    private function issuedInvoiceMissingAccounting(): int
    {
        return DB::table('customer_invoices as i')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'i.accounting_journal_id')->whereIn('i.status', ['issued', 'partially_paid', 'paid'])->where(static fn (Builder $query): Builder => $query->whereNull('j.id')->orWhereColumn('j.company_id', '<>', 'i.company_id')->orWhereColumn('j.total_debit_minor', '<>', 'i.total_minor')->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
    }

    private function invoiceOutstandingMismatch(): int
    {
        $allocations = DB::table('customer_collection_allocations')->selectRaw('customer_invoice_id, SUM(allocated_minor) allocated')->groupBy('customer_invoice_id');

        return DB::table('customer_invoices as i')->leftJoinSub($allocations, 'a', 'a.customer_invoice_id', '=', 'i.id')->whereIn('i.status', ['issued', 'partially_paid', 'paid'])->whereRaw('i.paid_minor <> COALESCE(a.allocated,0) OR COALESCE(a.allocated,0) > i.total_minor OR (i.status = ? AND COALESCE(a.allocated,0) <> i.total_minor)', ['paid'])->count();
    }

    private function salesFulfillmentMissingInventoryOrCogs(): int
    {
        return DB::table('sales_fulfillments as f')->leftJoin('inventory_movements as m', 'm.id', '=', 'f.inventory_movement_id')->where('f.status', 'fulfilled')->where(static fn (Builder $query): Builder => $query->whereNull('m.id')->orWhere('m.source_type', '<>', 'sales_fulfillment')->orWhereColumn('m.source_id', '<>', 'f.id')->orWhere('f.unit_cogs_minor', '<', 0)->orWhereRaw('f.total_cogs_minor <> f.quantity * f.unit_cogs_minor'))->count();
    }

    private function collectionLineageMismatch(): int
    {
        $allocated = DB::table('customer_collection_allocations')->selectRaw('customer_collection_id, SUM(allocated_minor) allocated')->groupBy('customer_collection_id');

        return DB::table('customer_collections as c')->leftJoinSub($allocated, 'a', 'a.customer_collection_id', '=', 'c.id')->leftJoin('treasury_transactions as t', 't.id', '=', 'c.treasury_transaction_id')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'c.accounting_journal_id')->where('c.status', 'recorded')->where(static fn (Builder $query): Builder => $query->whereRaw('c.allocated_minor <> COALESCE(a.allocated,0) OR c.allocated_minor > c.amount_minor')->orWhereNull('t.id')->orWhere('t.direction', '<>', 'inflow')->orWhereColumn('t.company_id', '<>', 'c.company_id')->orWhereColumn('t.amount_minor', '<>', 'c.amount_minor')->orWhereNull('j.id')->orWhereColumn('j.company_id', '<>', 'c.company_id')->orWhereColumn('j.total_debit_minor', '<>', 'c.amount_minor')->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
    }

    private function arGeneralLedgerMismatch(): int
    {
        $subledger = DB::table('customer_invoices')->whereIn('status', ['issued', 'partially_paid'])->selectRaw('company_id, SUM(total_minor-paid_minor) balance')->groupBy('company_id');
        $ledger = DB::table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->where('l.account_code_snapshot', '1150')->whereIn('j.status', ['internally_posted', 'exported'])->selectRaw('j.company_id, SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)) balance')->groupBy('j.company_id');

        return DB::query()->fromSub($subledger, 'ar')->leftJoinSub($ledger, 'gl', 'gl.company_id', '=', 'ar.company_id')->whereRaw('ar.balance <> COALESCE(gl.balance,0)')->count()
            + DB::query()->fromSub($ledger, 'gl')->leftJoinSub($subledger, 'ar', 'ar.company_id', '=', 'gl.company_id')->whereNull('ar.company_id')->where('gl.balance', '<>', 0)->count();
    }

    private function documentsMissingCurrentVersion(): int
    {
        return DB::table('enterprise_documents as d')->leftJoin('enterprise_document_versions as v', 'v.id', '=', 'd.current_version_id')->where(static fn (Builder $query): Builder => $query->whereNull('v.id')->orWhereColumn('v.document_id', '<>', 'd.id'))->count();
    }

    private function documentVersionsMissingSha(): int
    {
        return DB::table('enterprise_document_versions')->where(static fn (Builder $query): Builder => $query->whereNull('sha256')->orWhereRaw('CHAR_LENGTH(sha256) <> 64'))->count();
    }

    private function invalidProjectCompanyScope(): int
    {
        return DB::table('projects as p')->join('employees as manager', 'manager.id', '=', 'p.manager_employee_id')->leftJoin('branches as b', 'b.id', '=', 'p.branch_id')->where(static fn (Builder $q): Builder => $q->whereColumn('manager.company_id', '<>', 'p.company_id')->orWhere(static fn (Builder $branch): Builder => $branch->whereNotNull('p.branch_id')->whereColumn('b.company_id', '<>', 'p.company_id')))->count()
            + DB::table('project_members as pm')->join('projects as p', 'p.id', '=', 'pm.project_id')->join('employees as e', 'e.id', '=', 'pm.employee_id')->whereColumn('e.company_id', '<>', 'p.company_id')->count();
    }

    private function invalidProjectTaskLinks(): int
    {
        return DB::table('project_tasks as t')->leftJoin('project_milestones as m', 'm.id', '=', 't.milestone_id')->leftJoin('project_tasks as parent', 'parent.id', '=', 't.parent_task_id')->leftJoin('project_members as pm', static fn (JoinClause $join): JoinClause => $join->on('pm.project_id', '=', 't.project_id')->on('pm.employee_id', '=', 't.assignee_employee_id')->where('pm.is_active', true))->where(static fn (Builder $q): Builder => $q->where(static fn (Builder $mismatch): Builder => $mismatch->whereNotNull('t.milestone_id')->whereColumn('m.project_id', '<>', 't.project_id'))->orWhere(static fn (Builder $mismatch): Builder => $mismatch->whereNotNull('t.parent_task_id')->whereColumn('parent.project_id', '<>', 't.project_id'))->orWhere(static fn (Builder $mismatch): Builder => $mismatch->whereNotNull('t.assignee_employee_id')->whereNull('pm.id')))->count();
    }

    private function invalidProjectDependencies(): int
    {
        $edges = DB::table('project_task_dependencies as d')->join('project_tasks as a', 'a.id', '=', 'd.predecessor_task_id')->join('project_tasks as b', 'b.id', '=', 'd.successor_task_id')->get(['d.predecessor_task_id', 'd.successor_task_id', 'a.project_id as predecessor_project', 'b.project_id as successor_project']);
        $value = static function (object $edge, string $field): int {
            $raw = get_object_vars($edge)[$field] ?? null;

            return is_numeric($raw) ? (int) $raw : 0;
        };
        $invalid = $edges->where(fn (object $edge): bool => $value($edge, 'predecessor_task_id') === $value($edge, 'successor_task_id') || $value($edge, 'predecessor_project') !== $value($edge, 'successor_project'))->count();
        $graph = [];
        foreach ($edges as $edge) {
            $graph[$value($edge, 'predecessor_task_id')][] = $value($edge, 'successor_task_id');
        }
        foreach (array_keys($graph) as $start) {
            $queue = $graph[$start];
            $seen = [];
            while ($queue !== []) {
                $node = array_shift($queue);
                if ($node === $start) {
                    return $invalid + 1;
                } if (isset($seen[$node])) {
                    continue;
                } $seen[$node] = true;
                $queue = [...$queue, ...($graph[$node] ?? [])];
            }
        }

        return $invalid;
    }

    private function invalidProjectTimesheets(): int
    {
        return DB::table('project_timesheets as ts')->join('projects as p', 'p.id', '=', 'ts.project_id')->join('employees as e', 'e.id', '=', 'ts.employee_id')->leftJoin('project_tasks as t', 't.id', '=', 'ts.task_id')->leftJoin('project_members as pm', static fn (JoinClause $join): JoinClause => $join->on('pm.project_id', '=', 'ts.project_id')->on('pm.employee_id', '=', 'ts.employee_id')->where('pm.is_active', true))->where(static fn (Builder $q): Builder => $q->where('ts.minutes', '<=', 0)->orWhereColumn('e.company_id', '<>', 'p.company_id')->orWhereNull('pm.id')->orWhere(static fn (Builder $task): Builder => $task->whereNotNull('ts.task_id')->whereColumn('t.project_id', '<>', 'ts.project_id'))->orWhere(static fn (Builder $approval): Builder => $approval->where('ts.status', 'APPROVED')->whereNull('ts.approved_by')))->count();
    }

    private function invalidCompletedProjects(): int
    {
        return DB::table('projects as p')->where('p.status', 'COMPLETED')->where(static fn (Builder $q): Builder => $q->whereNull('p.actual_end_date')->orWhereExists(static fn (Builder $task): Builder => $task->selectRaw('1')->from('project_tasks as t')->whereColumn('t.project_id', 'p.id')->whereNotIn('t.status', ['DONE', 'CANCELLED']))->orWhereExists(static fn (Builder $milestone): Builder => $milestone->selectRaw('1')->from('project_milestones as m')->whereColumn('m.project_id', 'p.id')->where('m.status', '<>', 'COMPLETED'))->orWhereExists(static fn (Builder $timesheet): Builder => $timesheet->selectRaw('1')->from('project_timesheets as ts')->whereColumn('ts.project_id', 'p.id')->where('ts.status', 'SUBMITTED')))->count();
    }

    private function invalidMaintenanceScope(): int
    {
        return DB::table('maintenance_work_orders as w')->join('finance_assets as a', 'a.id', '=', 'w.asset_id')->leftJoin('branches as b', 'b.id', '=', 'w.branch_id')->leftJoin('employees as e', 'e.id', '=', 'w.assigned_employee_id')->where(static fn (Builder $q): Builder => $q->whereColumn('a.company_id', '<>', 'w.company_id')->orWhere(static fn (Builder $branch): Builder => $branch->whereNotNull('w.branch_id')->whereColumn('b.company_id', '<>', 'w.company_id'))->orWhere(static fn (Builder $employee): Builder => $employee->whereNotNull('w.assigned_employee_id')->whereColumn('e.company_id', '<>', 'w.company_id')))->count()
            + DB::table('maintenance_requests as r')->join('finance_assets as a', 'a.id', '=', 'r.asset_id')->leftJoin('branches as b', 'b.id', '=', 'r.branch_id')->where(static fn (Builder $q): Builder => $q->whereColumn('a.company_id', '<>', 'r.company_id')->orWhere(static fn (Builder $branch): Builder => $branch->whereNotNull('r.branch_id')->whereColumn('b.company_id', '<>', 'r.company_id')))->count();
    }

    private function invalidMaintenanceConversion(): int
    {
        return DB::table('maintenance_requests as r')->leftJoin('maintenance_work_orders as w', 'w.maintenance_request_id', '=', 'r.id')->where('r.status', 'CONVERTED')->where(static fn (Builder $q): Builder => $q->whereNull('w.id')->orWhereColumn('w.company_id', '<>', 'r.company_id')->orWhereColumn('w.asset_id', '<>', 'r.asset_id'))->count();
    }

    private function invalidMaintenanceTimeline(): int
    {
        return DB::table('maintenance_work_orders')->where(static fn (Builder $q): Builder => $q->whereRaw('scheduled_end IS NOT NULL AND scheduled_start IS NOT NULL AND scheduled_end < scheduled_start')->orWhereRaw('actual_end IS NOT NULL AND actual_start IS NOT NULL AND actual_end < actual_start')->orWhere(static fn (Builder $done): Builder => $done->where('status', 'COMPLETED')->where(static fn (Builder $missing): Builder => $missing->whereNull('actual_end')->orWhereNull('completion_note'))))->count();
    }

    private function invalidPreventiveOccurrence(): int
    {
        $duplicates = DB::table('maintenance_work_orders')->whereNotNull('preventive_plan_id')->selectRaw('preventive_plan_id, preventive_due_date, COUNT(*) total')->groupBy('preventive_plan_id', 'preventive_due_date')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_occurrences')->count();
    }

    private function maintenancePartMissingInventory(): int
    {
        return DB::table('maintenance_part_requirements as p')->join('maintenance_work_orders as w', 'w.id', '=', 'p.work_order_id')->leftJoin('inventory_movements as m', 'm.id', '=', 'p.inventory_movement_id')->where('p.status', 'ISSUED')->where(static fn (Builder $q): Builder => $q->whereNull('m.id')->orWhere('m.source_type', '<>', 'maintenance_work_order')->orWhereColumn('m.source_id', '<>', 'w.id')->orWhereColumn('m.product_variant_id', '<>', 'p.product_variant_id')->orWhereColumn('m.inventory_location_id', '<>', 'p.inventory_location_id'))->count();
    }

    private function invalidProjectSourceReferences(): int
    {
        return $this->invalidSourceFor('finance_expenses', 'PROJECT', 'projects')
            + $this->invalidSourceFor('purchase_requests', 'PROJECT', 'projects')
            + $this->invalidSourceFor('finance_assets', 'PROJECT', 'projects');
    }

    private function invalidMaintenanceSourceReferences(): int
    {
        return $this->invalidSourceFor('finance_expenses', 'MAINTENANCE_WORK_ORDER', 'maintenance_work_orders')
            + $this->invalidSourceFor('purchase_requests', 'MAINTENANCE_WORK_ORDER', 'maintenance_work_orders')
            + $this->invalidSourceFor('finance_assets', 'MAINTENANCE_WORK_ORDER', 'maintenance_work_orders');
    }

    private function invalidSourceFor(string $ownerTable, string $sourceType, string $sourceTable): int
    {
        return DB::table($ownerTable.' as owner')
            ->leftJoin($sourceTable.' as source', 'source.id', '=', 'owner.source_id')
            ->where('owner.source_type', $sourceType)
            ->where(static fn (Builder $query): Builder => $query->whereNull('source.id')->orWhereColumn('source.company_id', '<>', 'owner.company_id'))
            ->count();
    }

    private function duplicateIntegrationSourceActions(): int
    {
        $count = 0;
        foreach (['finance_expenses', 'purchase_requests', 'finance_assets'] as $table) {
            $duplicates = DB::table($table)->whereNotNull('source_idempotency_key')->selectRaw('source_idempotency_key, COUNT(*) total')->groupBy('source_idempotency_key')->havingRaw('COUNT(*) > 1');
            $count += DB::query()->fromSub($duplicates, 'duplicates')->count();
        }

        return $count;
    }

    private function invalidProcurementRealizationQuantity(): int
    {
        $overReceived = DB::table('purchase_order_lines')->whereColumn('received_quantity', '>', 'ordered_quantity')->count();
        $billed = DB::table('vendor_bill_lines as l')->join('vendor_bills as b', 'b.id', '=', 'l.vendor_bill_id')->whereNotIn('b.status', ['cancelled', 'rejected'])->selectRaw('l.purchase_order_line_id, SUM(l.quantity) billed')->whereNotNull('l.purchase_order_line_id')->groupBy('l.purchase_order_line_id');
        $overBilled = DB::table('purchase_order_lines as p')->joinSub($billed, 'billed', 'billed.purchase_order_line_id', '=', 'p.id')->whereColumn('billed.billed', '>', 'p.received_quantity')->count();

        return $overReceived + $overBilled;
    }

    private function invalidProcurementBudgetDimension(): int
    {
        return DB::table('purchase_order_lines as l')->join('purchase_orders as o', 'o.id', '=', 'l.purchase_order_id')->leftJoin('accounting_accounts as a', 'a.id', '=', 'l.account_id')->leftJoin('cost_centers as c', 'c.id', '=', 'l.cost_center_id')->whereNotNull('l.procurement_classification')->where(static fn (Builder $query): Builder => $query->whereNull('a.id')->orWhereColumn('a.company_id', '<>', 'o.company_id')->orWhere(static fn (Builder $center): Builder => $center->whereNotNull('l.cost_center_id')->whereColumn('c.company_id', '<>', 'o.company_id'))->orWhere(static fn (Builder $mapping): Builder => $mapping->where(static fn (Builder $inventory): Builder => $inventory->where('l.procurement_classification', 'inventory')->where('a.code', '<>', '1200'))->orWhere(static fn (Builder $asset): Builder => $asset->where('l.procurement_classification', 'asset')->where('a.code', '<>', '1300'))->orWhere(static fn (Builder $opex): Builder => $opex->where('l.procurement_classification', 'operating_expense')->where('a.account_type', '<>', 'expense'))))->count();
    }
}

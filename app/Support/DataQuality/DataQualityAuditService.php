<?php

namespace App\Support\DataQuality;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            new DataQualityFinding('journal_without_source', 'Accounting', 'critical', 'Automatic Journal phải có immutable source evidence.', $this->journalWithoutSource()),
            new DataQualityFinding('journal_without_policy', 'Accounting', 'critical', 'Journal thuộc controlled workflow phải có posting policy lineage.', $this->journalWithoutPolicy()),
            new DataQualityFinding('journal_line_unbalanced', 'Accounting', 'critical', 'Journal Lines phải cân và khớp tổng trên Journal.', $this->postedJournalImbalance()),
            new DataQualityFinding('posted_journal_in_closed_period', 'Accounting', 'critical', 'Posted Journal không được phát sinh sau thời điểm đóng kỳ.', $this->closedPeriodPosting()),
            new DataQualityFinding('duplicate_source_posting', 'Accounting', 'critical', 'Một source và posting type chỉ được có một canonical Journal.', $this->duplicateSourcePosting()),
            new DataQualityFinding('missing_account_mapping', 'Accounting', 'critical', 'Journal thuộc controlled workflow phải giữ policy và account mapping evidence trên từng line.', $this->missingAccountMapping()),
            new DataQualityFinding('posting_without_policy', 'Accounting Policy', 'critical', 'Controlled source posting phải resolve approved policy.', $this->journalWithoutPolicy()),
            new DataQualityFinding('posting_without_mapping', 'Accounting Policy', 'critical', 'Controlled source posting phải giữ account mapping evidence hợp lệ.', $this->missingAccountMapping()),
            new DataQualityFinding('legacy_account_mapping_usage', 'Accounting Policy', 'critical', 'Controlled source không được tiếp tục dùng legacy canonical account-code mapping.', $this->legacyAccountMappingUsage()),
            new DataQualityFinding('journal_without_policy_snapshot', 'Accounting Policy', 'critical', 'Mỗi controlled Journal phải giữ immutable policy snapshot trên Journal Lines.', $this->journalWithoutPolicySnapshot()),
            new DataQualityFinding('policy_version_missing', 'Accounting Policy', 'critical', 'Policy-backed Journal Line phải có cả policy id và version.', $this->policyVersionMissing()),
            new DataQualityFinding('receipt_without_policy', 'Procurement/Accounting Policy', 'critical', 'Goods Receipt received phải có INVENTORY_RECEIPT policy snapshot.', $this->sourceWithoutPolicy('procurement_goods_receipts', 'received', 'goods_receipt', 'id')),
            new DataQualityFinding('collection_without_policy', 'Commerce/Accounting Policy', 'critical', 'Customer Collection recorded phải có CUSTOMER_COLLECTION policy snapshot.', $this->sourceWithoutPolicy('customer_collections', 'recorded', 'customer_collection', 'id')),
            new DataQualityFinding('cogs_without_inventory_source', 'Commerce/Inventory Accounting', 'critical', 'COGS phải truy được tới Sales Fulfillment, Inventory Movement và SALES_COGS policy.', $this->cogsWithoutInventorySource()),
            new DataQualityFinding('expense_without_policy', 'Finance/Accounting Policy', 'critical', 'Expense approved/paid phải có OPERATING_EXPENSE policy snapshot.', $this->expenseWithoutPolicy()),
            new DataQualityFinding('tax_posting_without_mapping', 'Tax/Accounting Policy', 'critical', 'Giao dịch có VAT dương phải có tax configuration và policy mapping canonical.', $this->taxPostingWithoutMapping()),
            new DataQualityFinding('tax_without_policy', 'Tax/VAT', 'critical', 'Tax transaction evidence phải trỏ approved effective Tax Policy cùng Company.', $this->taxWithoutPolicy()),
            new DataQualityFinding('tax_without_rate', 'Tax/VAT', 'critical', 'Tax evidence phải có rate hợp lệ và tax amount khớp taxable amount.', $this->taxWithoutRate()),
            new DataQualityFinding('tax_without_mapping', 'Tax/VAT', 'critical', 'Tax Policy phải có tenant-scoped Tax Account và Accounting Policy mapping.', $this->taxWithoutMapping()),
            new DataQualityFinding('tax_journal_without_source', 'Tax/VAT Accounting', 'critical', 'Tax Journal phải truy được về immutable tax source evidence.', $this->taxJournalWithoutSource()),
            new DataQualityFinding('invalid_tax_period', 'Tax/VAT', 'critical', 'Tax evidence phải nằm trong effective period của policy.', $this->invalidTaxPeriod()),
            new DataQualityFinding('tax_policy_without_company', 'Tax/VAT Governance', 'critical', 'VAT Policy, Category và mappings phải cùng Company.', $this->taxPolicyWithoutCompany()),
            new DataQualityFinding('tax_policy_without_mapping', 'Tax/VAT Governance', 'critical', 'VAT Policy active phải có Tax Account và approved Accounting Policy.', $this->taxWithoutMapping()),
            new DataQualityFinding('tax_policy_overlap', 'Tax/VAT Governance', 'critical', 'Không được có hai VAT Policy approved chồng kỳ cho cùng category và currency.', $this->taxPolicyOverlap()),
            new DataQualityFinding('tax_evidence_without_journal', 'Tax/VAT Governance', 'critical', 'VAT evidence phải trỏ đúng immutable Journal cùng source.', $this->taxJournalWithoutSource()),
            new DataQualityFinding('journal_without_tax_evidence', 'Tax/VAT Governance', 'critical', 'Invoice/Bill có VAT đã post phải có tax evidence trỏ lại Journal.', $this->journalWithoutTaxEvidence()),
            new DataQualityFinding('credit_note_without_tax_source', 'Sales/Tax', 'critical', 'Credit Note có VAT phải truy được về immutable VAT evidence của Customer Invoice nguồn.', $this->creditNoteWithoutTaxSource()),
            new DataQualityFinding('credit_note_tax_without_journal', 'Sales/Tax Accounting', 'critical', 'Credit Note VAT reversal phải có evidence mới trỏ đúng Journal reversal.', $this->creditNoteTaxWithoutJournal()),
            new DataQualityFinding('credit_note_tax_over_original', 'Sales/Tax', 'critical', 'Tổng VAT reversal của Credit Note không được vượt VAT evidence gốc.', $this->creditNoteTaxOverOriginal()),
            new DataQualityFinding('duplicate_tax_reversal', 'Sales/Tax', 'critical', 'Mỗi Credit Note chỉ có một canonical VAT reversal evidence.', $this->duplicateTaxReversal()),
            new DataQualityFinding('procurement.receipt_missing_inventory', 'Procurement/Inventory', 'critical', 'Goods Receipt received phải có đúng Inventory Movement evidence.', $this->receivedGoodsMissingMovement()),
            new DataQualityFinding('inventory.value_mismatch', 'Inventory/Accounting', 'critical', 'Movement value và balance value phải reconcile với quantity/cost snapshot.', $this->inventoryValueMismatch()),
            new DataQualityFinding('inventory_stock_count_without_warehouse', 'Inventory/Stock Count', 'critical', 'Stock Count phải thuộc warehouse, branch và company canonical.', $this->inventoryStockCountWithoutWarehouse()),
            new DataQualityFinding('inventory_adjustment_without_reason', 'Inventory/Adjustment', 'critical', 'Inventory Adjustment phải có reason thuộc controlled vocabulary.', $this->inventoryAdjustmentWithoutReason()),
            new DataQualityFinding('inventory_adjustment_without_movement', 'Inventory/Adjustment', 'critical', 'Inventory Adjustment posted phải có đúng movement cho từng line.', $this->inventoryAdjustmentWithoutMovement()),
            new DataQualityFinding('inventory_adjustment_without_journal', 'Inventory/Accounting', 'critical', 'Inventory Adjustment posted phải có balanced policy-backed Journal đúng source.', $this->inventoryAdjustmentWithoutJournal()),
            new DataQualityFinding('inventory_variance_mismatch', 'Inventory/Stock Count', 'critical', 'Variance phải bằng counted quantity trừ system snapshot và khớp Adjustment line.', $this->inventoryVarianceMismatch()),
            new DataQualityFinding('asset_impairment_without_asset', 'Finance/Asset', 'critical', 'Impairment phải trỏ đúng active canonical Asset cùng Company.', $this->assetImpairmentWithoutAsset()),
            new DataQualityFinding('asset_impairment_without_journal', 'Finance/Asset Accounting', 'critical', 'Impairment posted phải có balanced Journal đúng source lineage.', $this->assetImpairmentWithoutJournal()),
            new DataQualityFinding('asset_disposal_without_asset', 'Finance/Asset', 'critical', 'Disposal phải trỏ đúng canonical Asset cùng Company.', $this->assetDisposalWithoutAsset()),
            new DataQualityFinding('asset_disposal_without_journal', 'Finance/Asset Accounting', 'critical', 'Disposal posted phải có balanced Journal đúng source lineage.', $this->assetDisposalWithoutJournal()),
            new DataQualityFinding('asset_gain_loss_mismatch', 'Finance/Asset', 'critical', 'Disposal gain/loss phải bằng proceeds trừ locked book value.', $this->assetGainLossMismatch()),
            new DataQualityFinding('fiscal_year_without_company', 'Finance/Fiscal Year', 'critical', 'Fiscal Year phải thuộc Company canonical.', $this->fiscalYearWithoutCompany()),
            new DataQualityFinding('period_without_fiscal_year', 'Finance/Accounting Period', 'critical', 'Accounting Period phải thuộc Fiscal Year cùng Company.', $this->periodWithoutFiscalYear()),
            new DataQualityFinding('closed_period_with_posting', 'Finance/Accounting Period', 'critical', 'Kỳ đóng không được nhận posting thường sau thời điểm close.', $this->closedPeriodWithPosting()),
            new DataQualityFinding('overlapping_fiscal_year', 'Finance/Fiscal Year', 'critical', 'Fiscal Year trong cùng Company không được chồng khoảng ngày.', $this->overlappingFiscalYear()),
            new DataQualityFinding('invalid_period_date_range', 'Finance/Accounting Period', 'critical', 'Period phải có khoảng ngày hợp lệ và nằm trong Fiscal Year.', $this->invalidPeriodDateRange()),
            new DataQualityFinding('opening_balance_without_source', 'Finance/Opening Balance', 'critical', 'Opening Balance phải truy được về Year Close, source Fiscal Year và balanced Journal.', $this->openingBalanceWithoutSource()),
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
            new DataQualityFinding('treasury.opening_balance_invalid_lineage', 'Finance/Treasury', 'critical', 'Opening balance phải cùng Company/currency với Treasury account và mapped GL hợp lệ.', $this->treasuryOpeningBalanceInvalidLineage()),
            new DataQualityFinding('treasury.opening_balance_invalid_posting', 'Finance/Treasury', 'critical', 'Opening balance posted phải có balanced policy-backed Journal đúng source và amount.', $this->treasuryOpeningBalanceInvalidPosting()),
            new DataQualityFinding('treasury.internal_transfer_invalid_posting', 'Finance/Treasury', 'critical', 'Internal transfer posted phải có đúng hai leg đối ứng, hai Treasury transaction và một balanced Journal đúng account mapping.', $this->treasuryInternalTransferInvalidPosting()),
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
            new DataQualityFinding('sales.customer_credit_note_invalid', 'Sales/AR/Accounting', 'critical', 'Customer Credit Note posted phải đúng tenant/source line, không over-credit, tách AR/customer liability và có Journal cân.', $this->invalidCustomerCreditNote()),
            new DataQualityFinding('crm_lead_without_company', 'CRM', 'critical', 'Lead phải thuộc Company, Branch và owner hợp lệ trong cùng tenant.', $this->crmLeadWithoutCompany()),
            new DataQualityFinding('crm_opportunity_without_company', 'CRM', 'critical', 'Opportunity phải thuộc Company và mọi Lead/Customer nguồn phải cùng tenant.', $this->crmOpportunityWithoutCompany()),
            new DataQualityFinding('crm_invalid_stage', 'CRM', 'critical', 'Lead status và Opportunity stage phải thuộc lifecycle CRM được hỗ trợ.', $this->crmInvalidStage()),
            new DataQualityFinding('customer_refund_without_credit_note', 'Sales/Refund', 'critical', 'Customer Refund phải có allocation tới posted Customer Credit Note cùng tenant/customer/branch/currency.', $this->customerRefundWithoutCreditNote()),
            new DataQualityFinding('customer_refund_over_liability', 'Sales/Refund', 'critical', 'Tổng Customer Refund đã giữ chỗ hoặc posted không được vượt Customer Credit Liability nguồn.', $this->customerRefundOverLiability()),
            new DataQualityFinding('customer_refund_without_treasury', 'Sales/Treasury', 'critical', 'Customer Refund posted phải có đúng Treasury outflow trên cash/bank account.', $this->customerRefundWithoutTreasury()),
            new DataQualityFinding('customer_refund_without_journal', 'Sales/Accounting', 'critical', 'Customer Refund posted phải có balanced Accounting Journal đúng source và amount.', $this->customerRefundWithoutJournal()),
            new DataQualityFinding('credit_note_without_invoice', 'Sales/AR', 'critical', 'Customer Credit Note phải trỏ tới Customer Invoice cùng tenant/customer/branch/currency.', $this->creditNoteWithoutInvoice()),
            new DataQualityFinding('credit_note_over_remaining_creditable', 'Sales/AR', 'critical', 'Tổng posted Credit Note không được vượt giá trị invoice có thể credit.', $this->creditNoteOverRemainingCreditable()),
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
            new DataQualityFinding('procurement.invalid_realization_quantity', 'Procurement/Budget', 'critical', 'Received không vượt ordered; billed không vượt net received; posted return phải đúng receipt/tenant và có Inventory/Journal correction cân.', $this->invalidProcurementRealizationQuantity()),
            new DataQualityFinding('procurement.invalid_budget_dimension', 'Procurement/Budget', 'critical', 'PO line classification, Account và Cost Center phải cùng Company và đúng canonical mapping.', $this->invalidProcurementBudgetDimension()),
            new DataQualityFinding('procurement.service_acceptance_invalid', 'Procurement/Service', 'critical', 'Service Acceptance phải đúng tenant, không vượt PO và Acceptance đã duyệt phải có Journal cân.', $this->invalidServiceAcceptance()),
            new DataQualityFinding('procurement.service_overbilling', 'Procurement/AP', 'critical', 'Service Vendor Bill không được vượt Acceptance đã duyệt hoặc mất lineage.', $this->invalidServiceBilling()),
            new DataQualityFinding('procurement.supplier_credit_invalid', 'Procurement/AP', 'critical', 'Receipt allocation và Supplier Credit posted phải đúng tenant/source, không vượt return/allocation và có Journal cân.', $this->invalidSupplierCredit()),
            new DataQualityFinding('fx_rate_without_company', 'Finance/FX', 'critical', 'FX Rate phải thuộc Company canonical.', $this->fxRateWithoutCompany()),
            new DataQualityFinding('fx_rate_invalid_pair', 'Finance/FX', 'critical', 'FX Rate phải có currency pair ISO khác nhau và rate dương.', $this->fxRateInvalidPair()),
            new DataQualityFinding('fx_rate_without_approval', 'Finance/FX', 'critical', 'Active FX Rate phải có maker-checker approval và activation evidence.', $this->fxRateWithoutApproval()),
            new DataQualityFinding('fx_rate_overlapping_effective_window', 'Finance/FX', 'critical', 'Không được có hai active FX Rate chồng hiệu lực cho cùng pair/type.', $this->fxRateOverlap()),
            new DataQualityFinding('fx_snapshot_mismatch', 'Finance/FX', 'critical', 'FX snapshot phải giữ nguyên rate evidence và checksum canonical.', $this->fxSnapshotMismatch()),
            new DataQualityFinding('fx_revaluation_posted_without_rate_snapshot', 'Finance/FX', 'critical', 'FX revaluation đã post phải có immutable rate snapshot đúng source.', $this->fxRevaluationPostedWithoutRateSnapshot()),
            new DataQualityFinding('fx_revaluation_posted_without_policy', 'Finance/FX', 'critical', 'FX revaluation đã post phải giữ accounting policy id/version.', $this->fxRevaluationPostedWithoutPolicy()),
            new DataQualityFinding('fx_revaluation_posted_without_journal', 'Finance/FX', 'critical', 'FX revaluation đã post phải có journal canonical cân và đúng lineage.', $this->fxRevaluationPostedWithoutJournal()),
            new DataQualityFinding('fx_revaluation_duplicate_source_posting', 'Finance/FX', 'critical', 'Một FX revaluation run chỉ có một original accounting journal.', $this->fxRevaluationDuplicateSourcePosting()),
            new DataQualityFinding('fx_revaluation_reversal_lineage_invalid', 'Finance/FX', 'critical', 'FX revaluation reversed phải có một reversal journal trỏ đúng original journal.', $this->fxRevaluationReversalLineageInvalid()),
            new DataQualityFinding('duplicate_invoice_fx_recognition', 'Sales/FX', 'critical', 'Mỗi Customer Invoice chỉ có một recognition evidence canonical.', $this->duplicateInvoiceFxRecognition()),
            new DataQualityFinding('recognition_company_mismatch', 'Sales/FX', 'critical', 'Recognition evidence phải cùng Company với Invoice.', $this->recognitionCompanyMismatch()),
            new DataQualityFinding('recognition_invoice_currency_mismatch', 'Sales/FX', 'critical', 'Transaction currency và amount phải khớp Invoice nguồn.', $this->recognitionInvoiceMismatch()),
            new DataQualityFinding('recognition_functional_currency_mismatch', 'Sales/FX', 'critical', 'Functional currency phải khớp Company governance.', $this->recognitionFunctionalCurrencyMismatch()),
            new DataQualityFinding('recognition_snapshot_pair_mismatch', 'Sales/FX', 'critical', 'Foreign recognition snapshot phải có đúng transaction/functional pair.', $this->recognitionSnapshotPairMismatch()),
            new DataQualityFinding('recognition_snapshot_rate_mismatch', 'Sales/FX', 'critical', 'Recognition phải giữ đúng rate/date/type của immutable snapshot.', $this->recognitionSnapshotRateMismatch()),
            new DataQualityFinding('recognition_identity_snapshot_invalid', 'Sales/FX', 'critical', 'Identity recognition phải dùng rate 1 và không được tạo FX snapshot.', $this->recognitionIdentitySnapshotInvalid()),
            new DataQualityFinding('recognition_functional_journal_mismatch', 'Sales/FX/Accounting', 'critical', 'Invoice có recognition evidence phải post Journal đúng functional currency và recognized amount.', $this->recognitionFunctionalJournalMismatch()),
            new DataQualityFinding('duplicate_allocation_fx_settlement', 'Sales/FX', 'critical', 'Mỗi collection allocation chỉ có một settlement evidence canonical.', $this->duplicateAllocationFxSettlement()),
            new DataQualityFinding('settlement_without_recognition', 'Sales/FX', 'critical', 'Settlement evidence phải trỏ tới recognition evidence canonical của Invoice nguồn.', $this->settlementWithoutRecognition()),
            new DataQualityFinding('settlement_recognition_lineage_mismatch', 'Sales/FX', 'critical', 'Settlement allocation phải thuộc Invoice của recognition evidence.', $this->settlementRecognitionLineageMismatch()),
            new DataQualityFinding('settlement_currency_mismatch', 'Sales/FX', 'critical', 'Settlement currency phải khớp Collection, Invoice và recognition evidence.', $this->settlementCurrencyMismatch()),
            new DataQualityFinding('settlement_snapshot_pair_mismatch', 'Sales/FX', 'critical', 'Foreign settlement snapshot phải giữ đúng transaction/functional pair và identity settlement không được có snapshot.', $this->settlementSnapshotPairMismatch()),
            new DataQualityFinding('released_carrying_exceeds_recognition', 'Sales/FX', 'critical', 'Tổng carrying basis released không được vượt recognition basis.', $this->releasedCarryingExceedsRecognition()),
            new DataQualityFinding('settled_foreign_exceeds_invoice', 'Sales/FX', 'critical', 'Tổng foreign amount settled không được vượt transaction amount đã recognize.', $this->settledForeignExceedsInvoice()),
            new DataQualityFinding('settlement_realized_fx_arithmetic_mismatch', 'Sales/FX', 'critical', 'Realized FX phải bằng functional settlement trừ carrying basis được release.', $this->settlementRealizedFxArithmeticMismatch()),
            new DataQualityFinding('settlement_journal_currency_mismatch', 'Sales/FX/Accounting', 'critical', 'Collection Journal phải dùng functional currency và khớp functional settlement.', $this->settlementJournalCurrencyMismatch()),
            new DataQualityFinding('settlement_treasury_currency_mismatch', 'Sales/FX/Treasury', 'critical', 'Treasury receipt phải dùng account cùng transaction currency và đúng foreign amount.', $this->settlementTreasuryCurrencyMismatch()),
            new DataQualityFinding('duplicate_supplier_invoice_fx_recognition', 'Procurement/AP/FX', 'critical', 'Mỗi Supplier Invoice chỉ có một AP FX recognition evidence canonical.', $this->duplicateSupplierInvoiceFxRecognition()),
            new DataQualityFinding('supplier_invoice_fx_scope_mismatch', 'Procurement/AP/FX', 'critical', 'AP FX evidence phải cùng Company/Branch và khớp currency/amount với Supplier Invoice.', $this->supplierInvoiceFxScopeMismatch()),
            new DataQualityFinding('supplier_invoice_fx_functional_currency_mismatch', 'Procurement/AP/FX', 'critical', 'Functional currency của AP FX evidence phải khớp Company governance.', $this->supplierInvoiceFxFunctionalCurrencyMismatch()),
            new DataQualityFinding('supplier_invoice_fx_snapshot_pair_mismatch', 'Procurement/AP/FX', 'critical', 'Foreign AP recognition snapshot phải có đúng transaction/functional pair.', $this->supplierInvoiceFxSnapshotPairMismatch()),
            new DataQualityFinding('supplier_invoice_fx_snapshot_rate_mismatch', 'Procurement/AP/FX', 'critical', 'AP recognition phải giữ đúng rate/date/type của immutable snapshot.', $this->supplierInvoiceFxSnapshotRateMismatch()),
            new DataQualityFinding('supplier_invoice_fx_identity_invalid', 'Procurement/AP/FX', 'critical', 'Identity AP recognition phải dùng rate 1 và không tạo governed snapshot.', $this->supplierInvoiceFxIdentityInvalid()),
            new DataQualityFinding('supplier_invoice_fx_journal_mismatch', 'Procurement/AP/FX/Accounting', 'critical', 'Supplier Invoice recognition phải trỏ đúng balanced policy-backed functional Journal.', $this->supplierInvoiceFxJournalMismatch()),
            new DataQualityFinding('supplier_payment_fx_orphan_settlement', 'Procurement/AP/FX', 'critical', 'AP FX settlement phải truy được payment, allocation, recognition và Vendor Bill canonical.', $this->supplierPaymentFxOrphanSettlement()),
            new DataQualityFinding('supplier_payment_fx_scope_mismatch', 'Procurement/AP/FX', 'critical', 'AP FX settlement, payment và recognition phải cùng Company và cùng Supplier Invoice.', $this->supplierPaymentFxScopeMismatch()),
            new DataQualityFinding('supplier_payment_fx_snapshot_mismatch', 'Procurement/AP/FX', 'critical', 'Foreign AP settlement phải có immutable direct spot snapshot đúng currency pair, date và rate.', $this->supplierPaymentFxSnapshotMismatch()),
            new DataQualityFinding('supplier_payment_fx_currency_mismatch', 'Procurement/AP/FX', 'critical', 'Payment, recognition, settlement và Treasury account phải dùng đúng transaction/functional currency.', $this->supplierPaymentFxCurrencyMismatch()),
            new DataQualityFinding('supplier_payment_fx_released_basis_mismatch', 'Procurement/AP/FX', 'critical', 'Cumulative foreign settlement và released carrying basis không được vượt recognition gốc.', $this->supplierPaymentFxReleasedBasisMismatch()),
            new DataQualityFinding('supplier_payment_fx_realized_mismatch', 'Procurement/AP/FX', 'critical', 'AP realized FX phải bằng released carrying basis trừ functional settlement.', $this->supplierPaymentFxRealizedMismatch()),
            new DataQualityFinding('supplier_payment_fx_journal_mismatch', 'Procurement/AP/FX/Accounting', 'critical', 'AP payment Journal phải cân, dùng functional currency và khớp policy/source evidence.', $this->supplierPaymentFxJournalMismatch()),
            new DataQualityFinding('supplier_payment_fx_treasury_mismatch', 'Procurement/AP/FX/Treasury', 'critical', 'Treasury outflow phải khớp payment source, transaction currency, amount và account.', $this->supplierPaymentFxTreasuryMismatch()),
            new DataQualityFinding('inventory_receipt_fx_orphan_or_scope_mismatch', 'Procurement/Inventory/FX', 'critical', 'Receipt FX valuation phải trỏ đúng receipt line, PO và company/branch nguồn.', $this->inventoryReceiptFxOrphanOrScopeMismatch()),
            new DataQualityFinding('inventory_receipt_fx_snapshot_mismatch', 'Procurement/Inventory/FX', 'critical', 'Foreign receipt valuation phải có immutable direct spot snapshot đúng pair/date/source.', $this->inventoryReceiptFxSnapshotMismatch()),
            new DataQualityFinding('inventory_receipt_fx_movement_or_journal_mismatch', 'Procurement/Inventory/Accounting', 'critical', 'Functional inventory movement và GRNI journal phải khớp receipt valuation, policy và source.', $this->inventoryReceiptFxMovementOrJournalMismatch()),
            new DataQualityFinding('inventory_receipt_fx_bill_match_mismatch', 'Procurement/AP/FX', 'critical', 'Bill matching phải release đúng GRNI, AP recognition và purchase price variance.', $this->inventoryReceiptFxBillMatchMismatch()),
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

    private function fxRateWithoutCompany(): int
    {
        if (! Schema::hasTable('finance_fx_rates')) {
            return 0;
        }

        return DB::table('finance_fx_rates as rate')->leftJoin('companies as company', 'company.id', '=', 'rate.company_id')->whereNull('company.id')->count();
    }

    private function fxRateInvalidPair(): int
    {
        if (! Schema::hasTable('finance_fx_rates')) {
            return 0;
        }

        return DB::table('finance_fx_rates')->where(fn (Builder $q): Builder => $q->whereColumn('base_currency', 'quote_currency')->orWhere('rate_decimal', '<=', 0)->orWhereRaw("base_currency NOT REGEXP '^[A-Z]{3}$'")->orWhereRaw("quote_currency NOT REGEXP '^[A-Z]{3}$'"))->count();
    }

    private function fxRateWithoutApproval(): int
    {
        if (! Schema::hasTable('finance_fx_rates')) {
            return 0;
        }

        return DB::table('finance_fx_rates')->where('status', 'active')->where(fn (Builder $q): Builder => $q->whereNull('approved_by')->orWhereNull('approved_at')->orWhereNull('activated_by')->orWhereNull('activated_at')->orWhereColumn('created_by', 'approved_by'))->count();
    }

    private function fxRateOverlap(): int
    {
        if (! Schema::hasTable('finance_fx_rates')) {
            return 0;
        }

        return DB::table('finance_fx_rates as a')->join('finance_fx_rates as b', static function (JoinClause $join): void {
            $join->on('a.company_id', '=', 'b.company_id')->on('a.base_currency', '=', 'b.base_currency')->on('a.quote_currency', '=', 'b.quote_currency')->on('a.rate_type', '=', 'b.rate_type')->whereColumn('a.id', '<', 'b.id');
        })->where('a.status', 'active')->where('b.status', 'active')->whereRaw("a.effective_from <= COALESCE(b.effective_to, '9999-12-31')")->whereRaw("b.effective_from <= COALESCE(a.effective_to, '9999-12-31')")->count();
    }

    private function fxSnapshotMismatch(): int
    {
        if (! Schema::hasTable('finance_fx_rate_snapshots')) {
            return 0;
        }

        return DB::table('finance_fx_rate_snapshots as snapshot')->leftJoin('finance_fx_rates as rate', 'rate.id', '=', 'snapshot.fx_rate_id')->get([
            'snapshot.*', 'rate.id as canonical_rate_id', 'rate.company_id as rate_company_id', 'rate.version as canonical_rate_version',
            'rate.base_currency as canonical_base_currency', 'rate.quote_currency as canonical_quote_currency', 'rate.rate_decimal as canonical_rate_decimal',
            'rate.rate_date as canonical_rate_date', 'rate.rate_type as canonical_rate_type', 'rate.source_reference as canonical_source_reference',
        ])->sum(static function (object $row): int {
            $companyId = self::evidenceInt($row->company_id ?? null);
            $rateId = self::evidenceInt($row->fx_rate_id ?? null);
            $rateVersion = self::evidenceInt($row->rate_version ?? null);
            $sourceId = self::evidenceInt($row->source_id ?? null);
            $base = self::evidenceString($row->base_currency ?? null);
            $quote = self::evidenceString($row->quote_currency ?? null);
            $rateDecimal = self::evidenceNumeric($row->rate_decimal ?? null);
            $rateDate = self::evidenceString($row->rate_date ?? null);
            $rateType = self::evidenceString($row->rate_type ?? null);
            $sourceReference = self::evidenceString($row->source_reference ?? null);
            $sourceType = self::evidenceString($row->source_type ?? null);
            $checksumEvidence = self::evidenceString($row->checksum ?? null);
            $snapshotEvidence = self::evidenceString($row->snapshot ?? null);
            if ($companyId === null || $rateId === null || $rateVersion === null || $sourceId === null || $base === null || $quote === null || $rateDecimal === null || $rateDate === null || $rateType === null || $sourceReference === null || $sourceType === null || $checksumEvidence === null || $snapshotEvidence === null) {
                return 1;
            }
            $payload = [
                'company_id' => $companyId, 'fx_rate_id' => $rateId, 'rate_version' => $rateVersion,
                'base_currency' => $base, 'quote_currency' => $quote, 'rate_decimal' => $rateDecimal,
                'rate_date' => $rateDate, 'rate_type' => $rateType, 'source_reference' => $sourceReference,
                'source_type' => $sourceType, 'source_id' => $sourceId,
            ];
            $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $canonicalCompanyId = self::evidenceInt($row->rate_company_id ?? null);
            $canonicalVersion = self::evidenceInt($row->canonical_rate_version ?? null);
            $canonicalRate = self::evidenceNumeric($row->canonical_rate_decimal ?? null);

            return $row->canonical_rate_id === null
                || $canonicalCompanyId !== $companyId
                || $canonicalVersion !== $rateVersion
                || $row->canonical_base_currency !== $row->base_currency
                || $row->canonical_quote_currency !== $row->quote_currency
                || $canonicalRate === null
                || bccomp($canonicalRate, $rateDecimal, 12) !== 0
                || self::evidenceString($row->canonical_rate_date ?? null) !== $rateDate
                || $row->canonical_rate_type !== $row->rate_type
                || $row->canonical_source_reference !== $row->source_reference
                || $checksum !== $checksumEvidence
                || json_decode($snapshotEvidence, true) !== $payload ? 1 : 0;
        });
    }

    private function fxRevaluationPostedWithoutRateSnapshot(): int
    {
        if (! Schema::hasTable('finance_fx_revaluation_runs')) {
            return 0;
        }

        return DB::table('finance_fx_revaluation_runs as run')->join('finance_fx_revaluation_lines as line', 'line.fx_revaluation_run_id', '=', 'run.id')->leftJoin('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'line.fx_rate_snapshot_id')->whereIn('run.status', ['posted', 'reversed'])->where(static fn (Builder $query): Builder => $query
            ->whereNull('snapshot.id')
            ->orWhereColumn('snapshot.company_id', '<>', 'run.company_id')
            ->orWhereColumn('line.company_id', '<>', 'run.company_id')
            ->orWhereColumn('snapshot.source_id', '<>', 'line.source_id')
            ->orWhereRaw("snapshot.source_type <> CONCAT('fx_revaluation:', run.id, ':', line.source_type)"))
            ->count();
    }

    private function fxRevaluationPostedWithoutPolicy(): int
    {
        if (! Schema::hasTable('finance_fx_revaluation_runs')) {
            return 0;
        }

        return DB::table('finance_fx_revaluation_runs as run')->join('finance_fx_revaluation_lines as line', 'line.fx_revaluation_run_id', '=', 'run.id')->leftJoin('accounting_posting_policies as policy', 'policy.id', '=', 'line.accounting_policy_id')->whereIn('run.status', ['posted', 'reversed'])->where(static fn (Builder $query): Builder => $query
            ->whereNull('line.accounting_policy_id')
            ->orWhereNull('line.policy_version')
            ->orWhereNull('policy.id')
            ->orWhereColumn('policy.company_id', '<>', 'run.company_id')
            ->orWhereColumn('policy.version', '<>', 'line.policy_version'))
            ->count();
    }

    private function fxRevaluationPostedWithoutJournal(): int
    {
        if (! Schema::hasTable('finance_fx_revaluation_runs')) {
            return 0;
        }

        return DB::table('finance_fx_revaluation_runs as run')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'run.accounting_journal_id')->whereIn('run.status', ['posted', 'reversed'])->where(static fn (Builder $query): Builder => $query
            ->whereNull('journal.id')
            ->orWhereColumn('journal.company_id', '<>', 'run.company_id')
            ->orWhereColumn('journal.branch_id', '<>', 'run.branch_id')
            ->orWhere('journal.source_type', '<>', 'fx_revaluation')
            ->orWhereColumn('journal.source_id', '<>', 'run.id')
            ->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))
            ->count();
    }

    private function fxRevaluationDuplicateSourcePosting(): int
    {
        if (! Schema::hasTable('finance_fx_revaluation_runs')) {
            return 0;
        }
        $duplicates = DB::table('payroll_accounting_journals')->where('source_type', 'fx_revaluation')->whereNull('reversal_of_journal_id')->selectRaw('company_id, source_id, COUNT(*) duplicate_count')->groupBy('company_id', 'source_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicates')->count();
    }

    private function fxRevaluationReversalLineageInvalid(): int
    {
        if (! Schema::hasTable('finance_fx_revaluation_runs')) {
            return 0;
        }

        return DB::table('finance_fx_revaluation_runs as run')->leftJoin('payroll_accounting_journals as original', 'original.id', '=', 'run.accounting_journal_id')->leftJoin('payroll_accounting_journals as reversal', 'reversal.id', '=', 'run.reversal_journal_id')->where('run.status', 'reversed')->where(static fn (Builder $query): Builder => $query
            ->whereNull('original.id')
            ->orWhereNull('reversal.id')
            ->orWhereColumn('reversal.reversal_of_journal_id', '<>', 'original.id')
            ->orWhereColumn('original.company_id', '<>', 'run.company_id')
            ->orWhereColumn('reversal.company_id', '<>', 'run.company_id')
            ->orWhere('original.source_type', '<>', 'fx_revaluation')
            ->orWhere('reversal.source_type', '<>', 'fx_revaluation')
            ->orWhereColumn('original.source_id', '<>', 'run.id')
            ->orWhereColumn('reversal.source_id', '<>', 'run.id'))
            ->count();
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
            ->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('commerce_sale_reversals as reversal')
                ->whereColumn('reversal.original_revenue_id', 'r.id')->where('reversal.status', 'completed'))
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

    private function journalWithoutSource(): int
    {
        if (! Schema::hasTable('accounting_journal_source_evidence')) {
            return 0;
        }

        return DB::table('payroll_accounting_journals as journal')
            ->whereNotNull('journal.generic_source_key')
            ->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('accounting_journal_source_evidence as evidence')->whereColumn('evidence.journal_id', 'journal.id'))
            ->count();
    }

    private function journalWithoutPolicy(): int
    {
        return DB::table('payroll_accounting_journals as journal')
            ->whereIn('journal.source_type', $this->policyBackedSourceTypes())
            ->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NOT NULL")->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_version') IS NOT NULL"))
            ->count();
    }

    private function missingAccountMapping(): int
    {
        return DB::table('payroll_accounting_journal_lines as line')
            ->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')
            ->leftJoin('accounting_accounts as account', 'account.id', '=', 'line.account_id')
            ->whereIn('journal.source_type', $this->policyBackedSourceTypes())
            ->whereIn('journal.status', ['internally_posted', 'exported'])
            ->where(static fn (Builder $query): Builder => $query->whereNull('account.id')->orWhereColumn('account.company_id', '<>', 'journal.company_id')->orWhereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NULL")->orWhereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_version') IS NULL"))
            ->count();
    }

    /** @return list<string> */
    private function policyBackedSourceTypes(): array
    {
        return ['customer_invoice', 'vendor_bill', 'ap_payment', 'finance_asset', 'customer_credit_note', 'customer_refund', 'inventory_adjustment', 'asset_impairment', 'asset_disposal', 'treasury_opening_balance', 'treasury_internal_transfer', 'goods_receipt', 'customer_collection', 'sales_fulfillment', 'finance_expense'];
    }

    private function sourceWithoutPolicy(string $table, string $status, string $sourceType, string $idColumn): int
    {
        return DB::table($table.' as source')->where('source.status', $status)
            ->whereNotExists(static fn (Builder $journal): Builder => $journal->selectRaw('1')->from('payroll_accounting_journals as journal')
                ->whereColumn('journal.source_id', 'source.'.$idColumn)->where('journal.source_type', $sourceType)
                ->whereIn('journal.status', ['internally_posted', 'exported'])
                ->whereExists(static fn (Builder $line): Builder => $line->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NOT NULL")))
            ->count();
    }

    private function cogsWithoutInventorySource(): int
    {
        return DB::table('sales_fulfillments as fulfillment')->leftJoin('inventory_movements as movement', 'movement.id', '=', 'fulfillment.inventory_movement_id')
            ->where('fulfillment.status', 'fulfilled')->where(static fn (Builder $query): Builder => $query->whereNull('movement.id')->orWhere('movement.source_type', '<>', 'sales_fulfillment')->orWhereColumn('movement.source_id', '<>', 'fulfillment.id')
            ->orWhereNotExists(static fn (Builder $journal): Builder => $journal->selectRaw('1')->from('payroll_accounting_journals as journal')->whereColumn('journal.source_id', 'fulfillment.id')->where('journal.source_type', 'sales_fulfillment')->whereIn('journal.status', ['internally_posted', 'exported'])
                ->whereExists(static fn (Builder $line): Builder => $line->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NOT NULL"))))
            ->count();
    }

    private function expenseWithoutPolicy(): int
    {
        return DB::table('finance_expenses as source')->whereIn('source.status', ['approved', 'paid'])
            ->whereNotExists(static fn (Builder $journal): Builder => $journal->selectRaw('1')->from('payroll_accounting_journals as journal')->whereColumn('journal.source_id', 'source.id')->where('journal.source_type', 'finance_expense')->where('journal.posting_type', 'expense_incurred')->whereIn('journal.status', ['internally_posted', 'exported'])
                ->whereExists(static fn (Builder $line): Builder => $line->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NOT NULL")))
            ->count();
    }

    private function taxPostingWithoutMapping(): int
    {
        $invalid = 0;
        foreach (['customer_invoices', 'vendor_bills'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tax_minor') || ! Schema::hasColumn($table, 'tax_status')) {
                continue;
            }
            $invalid += DB::table($table)->where('tax_minor', '>', 0)
                ->where(static fn (Builder $query): Builder => $query->whereNull('tax_status')->orWhere('tax_status', '<>', 'configured'))
                ->count();
        }

        return $invalid;
    }

    private function taxWithoutPolicy(): int
    {
        if (! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::table('tax_transaction_evidence as evidence')->leftJoin('vat_policies as policy', 'policy.id', '=', 'evidence.tax_policy_id')->where(static fn (Builder $query): Builder => $query->whereNull('policy.id')->orWhereColumn('policy.company_id', '<>', 'evidence.company_id')->orWhere('policy.status', '<>', 'approved')->orWhereColumn('policy.version', '<>', 'evidence.policy_version'))->count();
    }

    private function taxWithoutRate(): int
    {
        if (! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::table('tax_transaction_evidence')->where(static fn (Builder $query): Builder => $query->where('rate_basis_points', '>', 10_000)->orWhereRaw('tax_amount_minor <> ROUND(taxable_amount_minor * rate_basis_points / 10000)'))->count();
    }

    private function taxWithoutMapping(): int
    {
        if (! Schema::hasTable('vat_policies')) {
            return 0;
        }

        return DB::table('vat_policies as policy')->leftJoin('accounting_accounts as account', 'account.id', '=', 'policy.tax_account_id')->leftJoin('accounting_posting_policies as posting', 'posting.id', '=', 'policy.accounting_posting_policy_id')->where('policy.status', 'approved')->where(static fn (Builder $query): Builder => $query->whereNull('account.id')->orWhereColumn('account.company_id', '<>', 'policy.company_id')->orWhereNull('posting.id')->orWhereColumn('posting.company_id', '<>', 'policy.company_id')->orWhere('posting.status', '<>', 'approved'))->count();
    }

    private function taxJournalWithoutSource(): int
    {
        if (! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::table('tax_transaction_evidence as evidence')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'evidence.accounting_journal_id')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'evidence.company_id')->orWhereColumn('journal.source_type', '<>', 'evidence.source_type')->orWhereColumn('journal.source_id', '<>', 'evidence.source_id'))->count();
    }

    private function invalidTaxPeriod(): int
    {
        if (! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::table('tax_transaction_evidence as evidence')->join('vat_policies as policy', 'policy.id', '=', 'evidence.tax_policy_id')->where('evidence.source_type', '<>', 'customer_credit_note')->where(static fn (Builder $query): Builder => $query->whereColumn('evidence.posting_date', '<', 'policy.effective_from')->orWhere(static fn (Builder $nested): Builder => $nested->whereNotNull('policy.effective_to')->whereColumn('evidence.posting_date', '>', 'policy.effective_to')))->count();
    }

    private function taxPolicyWithoutCompany(): int
    {
        if (! Schema::hasTable('vat_policies')) {
            return 0;
        }

        return DB::table('vat_policies as policy')->leftJoin('tax_categories as category', 'category.id', '=', 'policy.tax_category_id')->leftJoin('accounting_accounts as account', 'account.id', '=', 'policy.tax_account_id')->leftJoin('accounting_posting_policies as posting', 'posting.id', '=', 'policy.accounting_posting_policy_id')->where(static fn (Builder $query): Builder => $query->whereNull('category.id')->orWhereColumn('category.company_id', '<>', 'policy.company_id')->orWhereNull('account.id')->orWhereColumn('account.company_id', '<>', 'policy.company_id')->orWhereNull('posting.id')->orWhereColumn('posting.company_id', '<>', 'policy.company_id'))->count();
    }

    private function taxPolicyOverlap(): int
    {
        if (! Schema::hasTable('vat_policies')) {
            return 0;
        }

        return DB::table('vat_policies as left_policy')->join('vat_policies as right_policy', static function (JoinClause $join): void {
            $join->on('right_policy.company_id', '=', 'left_policy.company_id')->on('right_policy.tax_category_id', '=', 'left_policy.tax_category_id')->on('right_policy.currency', '=', 'left_policy.currency')->on('right_policy.id', '>', 'left_policy.id');
        })->where(['left_policy.status' => 'approved', 'right_policy.status' => 'approved'])->whereRaw("left_policy.effective_from <= COALESCE(right_policy.effective_to, '9999-12-31')")->whereRaw("right_policy.effective_from <= COALESCE(left_policy.effective_to, '9999-12-31')")->count();
    }

    private function journalWithoutTaxEvidence(): int
    {
        if (! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }
        $count = 0;
        foreach ([['customer_invoices', 'customer_invoice'], ['vendor_bills', 'vendor_bill']] as [$table, $sourceType]) {
            $count += DB::table($table.' as source')->join('payroll_accounting_journals as journal', 'journal.source_id', '=', 'source.id')->where('journal.source_type', $sourceType)->where('source.tax_minor', '>', 0)->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('tax_transaction_evidence as evidence')->whereColumn('evidence.accounting_journal_id', 'journal.id')->whereColumn('evidence.source_id', 'source.id')->where('evidence.source_type', $sourceType))->count();
        }

        return $count;
    }

    private function creditNoteWithoutTaxSource(): int
    {
        if (! Schema::hasTable('customer_credit_notes') || ! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::table('customer_credit_notes as note')->leftJoin('tax_transaction_evidence as source', static function (JoinClause $join): void {
            $join->on('source.company_id', '=', 'note.company_id')->on('source.source_id', '=', 'note.customer_invoice_id')->where('source.source_type', 'customer_invoice');
        })->where('note.status', 'posted')->where('note.tax_minor', '>', 0)->where(static fn (Builder $query): Builder => $query->whereNull('source.id')->orWhereColumn('source.branch_id', '<>', 'note.branch_id')->orWhereColumn('source.currency', '<>', 'note.currency'))->count();
    }

    private function creditNoteTaxWithoutJournal(): int
    {
        if (! Schema::hasTable('customer_credit_notes') || ! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::table('customer_credit_notes as note')->leftJoin('tax_transaction_evidence as evidence', static function (JoinClause $join): void {
            $join->on('evidence.company_id', '=', 'note.company_id')->on('evidence.source_id', '=', 'note.id')->where('evidence.source_type', 'customer_credit_note');
        })->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'evidence.accounting_journal_id')->where('note.status', 'posted')->where('note.tax_minor', '>', 0)->where(static fn (Builder $query): Builder => $query->whereNull('evidence.id')->orWhereNull('journal.id')->orWhereColumn('journal.id', '<>', 'note.accounting_journal_id')->orWhereColumn('evidence.tax_amount_minor', '<>', 'note.tax_minor'))->count();
    }

    private function creditNoteTaxOverOriginal(): int
    {
        if (! Schema::hasTable('customer_credit_notes') || ! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }
        $reversals = DB::table('customer_credit_notes')->where('status', 'posted')->where('tax_minor', '>', 0)->selectRaw('company_id, customer_invoice_id, SUM(net_minor) taxable_minor, SUM(tax_minor) tax_minor')->groupBy('company_id', 'customer_invoice_id');

        return DB::query()->fromSub($reversals, 'reversal')->join('tax_transaction_evidence as source', static function (JoinClause $join): void {
            $join->on('source.company_id', '=', 'reversal.company_id')->on('source.source_id', '=', 'reversal.customer_invoice_id')->where('source.source_type', 'customer_invoice');
        })->where(static fn (Builder $query): Builder => $query->whereColumn('reversal.taxable_minor', '>', 'source.taxable_amount_minor')->orWhereColumn('reversal.tax_minor', '>', 'source.tax_amount_minor'))->count();
    }

    private function duplicateTaxReversal(): int
    {
        if (! Schema::hasTable('tax_transaction_evidence')) {
            return 0;
        }

        return DB::query()->fromSub(DB::table('tax_transaction_evidence')->where('source_type', 'customer_credit_note')->selectRaw('company_id, source_id, COUNT(*) duplicate_count')->groupBy('company_id', 'source_id')->havingRaw('COUNT(*) > 1'), 'duplicates')->count();
    }

    private function legacyAccountMappingUsage(): int
    {
        return DB::table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')
            ->whereIn('journal.source_type', $this->policyBackedSourceTypes())->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy')) = 'canonical_source_posting_v1'")->count();
    }

    private function journalWithoutPolicySnapshot(): int
    {
        return DB::table('payroll_accounting_journals as journal')->whereIn('journal.source_type', $this->policyBackedSourceTypes())
            ->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereNotExists(static fn (Builder $query): Builder => $query->selectRaw('1')->from('payroll_accounting_journal_lines as line')->whereColumn('line.journal_id', 'journal.id')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(line.mapping_snapshot, '$.policy')) = 'accounting_posting_policy'"))->count();
    }

    private function policyVersionMissing(): int
    {
        return DB::table('payroll_accounting_journal_lines as line')->join('payroll_accounting_journals as journal', 'journal.id', '=', 'line.journal_id')
            ->whereIn('journal.source_type', $this->policyBackedSourceTypes())->whereIn('journal.status', ['internally_posted', 'exported'])
            ->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_id') IS NOT NULL")
            ->whereRaw("JSON_EXTRACT(line.mapping_snapshot, '$.policy_version') IS NULL")->count();
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

    private function treasuryOpeningBalanceInvalidLineage(): int
    {
        try {
            return DB::table('treasury_opening_balances as opening')->join('treasury_financial_accounts as financial', 'financial.id', '=', 'opening.treasury_financial_account_id')->join('accounting_accounts as ledger', 'ledger.id', '=', 'financial.accounting_account_id')->where(static fn (Builder $query): Builder => $query->whereColumn('opening.company_id', '<>', 'financial.company_id')->orWhereColumn('opening.company_id', '<>', 'ledger.company_id')->orWhereColumn('opening.currency', '<>', 'financial.currency')->orWhere('financial.status', '<>', 'active')->orWhere('ledger.status', '<>', 'active')->orWhere('ledger.allow_posting', false))->count();
        } catch (QueryException) {
            return 0;
        }
    }

    private function treasuryOpeningBalanceInvalidPosting(): int
    {
        try {
            return DB::table('treasury_opening_balances as opening')->leftJoin('accounting_posting_policies as policy', 'policy.id', '=', 'opening.accounting_posting_policy_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'opening.accounting_journal_id')->where('opening.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('policy.id')->orWhereColumn('policy.company_id', '<>', 'opening.company_id')->orWhereColumn('policy.version', '<>', 'opening.accounting_posting_policy_version')->orWhereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'opening.company_id')->orWhereColumn('journal.source_id', '<>', 'opening.id')->orWhere('journal.source_type', '<>', 'treasury_opening_balance')->orWhere('journal.posting_type', '<>', 'treasury_opening_balance')->orWhereColumn('journal.currency', '<>', 'opening.currency')->orWhereColumn('journal.posting_date', '<>', 'opening.effective_date')->orWhereRaw('journal.total_debit_minor <> ABS(opening.posting_adjustment_minor)')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
        } catch (QueryException) {
            return 0;
        }
    }

    private function treasuryInternalTransferInvalidPosting(): int
    {
        try {
            $invalidHeader = DB::table('treasury_internal_transfers as transfer')
                ->join('treasury_financial_accounts as source', 'source.id', '=', 'transfer.source_treasury_account_id')
                ->join('treasury_financial_accounts as destination', 'destination.id', '=', 'transfer.destination_treasury_account_id')
                ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'transfer.accounting_journal_id')
                ->leftJoin('treasury_internal_transfers as original', 'original.id', '=', 'transfer.reversal_of_transfer_id')
                ->leftJoin('payroll_accounting_journals as original_journal', 'original_journal.id', '=', 'original.accounting_journal_id')
                ->where('transfer.status', 'posted')
                ->where(static fn (Builder $query): Builder => $query->whereColumn('source.id', 'destination.id')->orWhereColumn('source.company_id', '<>', 'transfer.company_id')->orWhereColumn('destination.company_id', '<>', 'transfer.company_id')->orWhereColumn('source.currency', '<>', 'transfer.currency')->orWhereColumn('destination.currency', '<>', 'transfer.currency')->orWhereNotIn('source.type', ['cash', 'bank'])->orWhereNotIn('destination.type', ['cash', 'bank'])->orWhereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'transfer.company_id')->orWhereColumn('journal.source_id', '<>', 'transfer.id')->orWhere('journal.source_type', '<>', 'treasury_internal_transfer')->orWhereColumn('journal.total_debit_minor', '<>', 'transfer.amount_minor')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhere(static fn (Builder $reversal): Builder => $reversal->whereNotNull('transfer.reversal_of_transfer_id')->where(static fn (Builder $invalid): Builder => $invalid->whereColumn('transfer.id', 'transfer.reversal_of_transfer_id')->orWhereNull('original.id')->orWhereNotNull('original.reversal_of_transfer_id')->orWhere('original.status', '<>', 'posted')->orWhereColumn('transfer.company_id', '<>', 'original.company_id')->orWhereColumn('transfer.currency', '<>', 'original.currency')->orWhereColumn('transfer.amount_minor', '<>', 'original.amount_minor')->orWhereColumn('transfer.source_treasury_account_id', '<>', 'original.destination_treasury_account_id')->orWhereColumn('transfer.destination_treasury_account_id', '<>', 'original.source_treasury_account_id')->orWhereColumn('journal.reversal_of_journal_id', '<>', 'original_journal.id'))))
                ->count();
            $invalidLegs = DB::table('treasury_internal_transfers as transfer')->where('transfer.status', 'posted')->whereRaw("(SELECT COUNT(*) FROM treasury_internal_transfer_legs leg WHERE leg.treasury_internal_transfer_id = transfer.id) <> 2 OR (SELECT COUNT(*) FROM treasury_internal_transfer_legs leg WHERE leg.treasury_internal_transfer_id = transfer.id AND leg.leg_role = 'SOURCE_OUT' AND leg.direction = 'outflow' AND leg.amount_minor = transfer.amount_minor AND leg.currency = transfer.currency AND leg.treasury_transaction_id IS NOT NULL) <> 1 OR (SELECT COUNT(*) FROM treasury_internal_transfer_legs leg WHERE leg.treasury_internal_transfer_id = transfer.id AND leg.leg_role = 'DESTINATION_IN' AND leg.direction = 'inflow' AND leg.amount_minor = transfer.amount_minor AND leg.currency = transfer.currency AND leg.treasury_transaction_id IS NOT NULL) <> 1")->count();
            $invalidTransactions = DB::table('treasury_internal_transfer_legs as leg')->join('treasury_internal_transfers as transfer', 'transfer.id', '=', 'leg.treasury_internal_transfer_id')->leftJoin('treasury_transactions as tx', 'tx.id', '=', 'leg.treasury_transaction_id')->where('transfer.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('tx.id')->orWhere('tx.source_type', '<>', 'treasury_internal_transfer_leg')->orWhereColumn('tx.source_id', '<>', 'leg.id')->orWhereColumn('tx.company_id', '<>', 'transfer.company_id')->orWhereColumn('tx.financial_account_id', '<>', 'leg.treasury_financial_account_id')->orWhereColumn('tx.direction', '<>', 'leg.direction')->orWhereColumn('tx.amount_minor', '<>', 'leg.amount_minor')->orWhereColumn('tx.currency', '<>', 'leg.currency')->orWhereColumn('tx.accounting_journal_id', '<>', 'transfer.accounting_journal_id'))->count();

            return $invalidHeader + $invalidLegs + $invalidTransactions;
        } catch (QueryException) {
            return 0;
        }
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
        $query = DB::table('customer_invoices as i')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'i.accounting_journal_id');
        if (Schema::hasTable('customer_invoice_fx_recognitions')) {
            $query->leftJoin('customer_invoice_fx_recognitions as recognition', 'recognition.customer_invoice_id', '=', 'i.id');
        }
        $recognizedAmount = Schema::hasTable('customer_invoice_fx_recognitions') ? 'COALESCE(recognition.functional_amount_minor, i.total_minor)' : 'i.total_minor';

        return $query->whereIn('i.status', ['issued', 'partially_paid', 'paid'])->where(static fn (Builder $query): Builder => $query->whereNull('j.id')->orWhereColumn('j.company_id', '<>', 'i.company_id')->orWhereRaw("j.total_debit_minor <> {$recognizedAmount}")->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
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
        $functional = $this->arFxSchemaReady()
            ? DB::table('customer_collection_allocations as allocation')->join('customer_collection_allocation_fx_settlements as settlement', 'settlement.customer_collection_allocation_id', '=', 'allocation.id')->selectRaw('allocation.customer_collection_id, SUM(GREATEST(settlement.settlement_functional_minor, settlement.released_carrying_minor)) journal_amount')->groupBy('allocation.customer_collection_id')
            : null;
        $query = DB::table('customer_collections as c')->leftJoinSub($allocated, 'a', 'a.customer_collection_id', '=', 'c.id')->leftJoin('treasury_transactions as t', 't.id', '=', 'c.treasury_transaction_id')->leftJoin('payroll_accounting_journals as j', 'j.id', '=', 'c.accounting_journal_id');
        if ($functional !== null) {
            $query->leftJoinSub($functional, 'fx', 'fx.customer_collection_id', '=', 'c.id');
        }
        $expectedJournal = $functional === null ? 'c.amount_minor' : 'COALESCE(fx.journal_amount,c.amount_minor)';

        return $query->where('c.status', 'recorded')->where(static fn (Builder $query): Builder => $query->whereRaw('c.allocated_minor <> COALESCE(a.allocated,0) OR c.allocated_minor > c.amount_minor')->orWhereNull('t.id')->orWhere('t.direction', '<>', 'inflow')->orWhereColumn('t.company_id', '<>', 'c.company_id')->orWhereColumn('t.amount_minor', '<>', 'c.amount_minor')->orWhereNull('j.id')->orWhereColumn('j.company_id', '<>', 'c.company_id')->orWhereRaw("j.total_debit_minor <> {$expectedJournal}")->orWhereColumn('j.total_debit_minor', '<>', 'j.total_credit_minor'))->count();
    }

    private function arGeneralLedgerMismatch(): int
    {
        $credit = Schema::hasTable('customer_credit_notes')
            ? DB::table('customer_credit_notes')->where('status', 'posted')->selectRaw('customer_invoice_id, SUM(ar_reduction_minor) reduced')->groupBy('customer_invoice_id')
            : null;
        $subledger = DB::table('customer_invoices as invoice')->whereIn('invoice.status', ['issued', 'partially_paid']);
        if ($credit !== null) {
            $subledger->leftJoinSub($credit, 'credit', 'credit.customer_invoice_id', '=', 'invoice.id');
        }
        $reduced = $credit === null ? '0' : 'COALESCE(credit.reduced,0)';
        $subledger->selectRaw('invoice.company_id, SUM(GREATEST(invoice.total_minor-invoice.paid_minor-'.$reduced.',0)) balance')->groupBy('invoice.company_id');
        $ledger = DB::table('payroll_accounting_journal_lines as l')->join('payroll_accounting_journals as j', 'j.id', '=', 'l.journal_id')->where('l.account_code_snapshot', '1150')->whereIn('j.status', ['internally_posted', 'exported'])->selectRaw('j.company_id, SUM(CAST(l.debit_minor AS SIGNED)-CAST(l.credit_minor AS SIGNED)) balance')->groupBy('j.company_id');

        return DB::query()->fromSub($subledger, 'ar')->leftJoinSub($ledger, 'gl', 'gl.company_id', '=', 'ar.company_id')->whereRaw('ar.balance <> COALESCE(gl.balance,0)')->count()
            + DB::query()->fromSub($ledger, 'gl')->leftJoinSub($subledger, 'ar', 'ar.company_id', '=', 'gl.company_id')->whereNull('ar.company_id')->where('gl.balance', '<>', 0)->count();
    }

    private function invalidCustomerCreditNote(): int
    {
        if (! Schema::hasTable('customer_credit_notes')) {
            return 0;
        }
        $lineSums = DB::table('customer_credit_note_lines')->selectRaw('customer_credit_note_id, SUM(net_minor) net, SUM(tax_minor) tax, SUM(total_minor) total')->groupBy('customer_credit_note_id');
        $invalidHeaders = DB::table('customer_credit_notes as note')
            ->join('customer_invoices as invoice', 'invoice.id', '=', 'note.customer_invoice_id')
            ->leftJoinSub($lineSums, 'line_sum', 'line_sum.customer_credit_note_id', '=', 'note.id')
            ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'note.accounting_journal_id')
            ->where('note.status', 'posted')
            ->where(static fn (Builder $query): Builder => $query
                ->whereColumn('invoice.company_id', '<>', 'note.company_id')->orWhereColumn('invoice.branch_id', '<>', 'note.branch_id')
                ->orWhereColumn('invoice.customer_id', '<>', 'note.customer_id')->orWhereColumn('invoice.currency', '<>', 'note.currency')
                ->orWhereRaw('note.net_minor+note.tax_minor<>note.total_minor')->orWhereRaw('note.ar_reduction_minor+note.customer_credit_liability_minor<>note.total_minor')
                ->orWhereRaw('COALESCE(line_sum.net,0)<>note.net_minor OR COALESCE(line_sum.tax,0)<>note.tax_minor OR COALESCE(line_sum.total,0)<>note.total_minor')
                ->orWhereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'note.company_id')->orWhereColumn('journal.source_id', '<>', 'note.id')
                ->orWhere('journal.source_type', '<>', 'customer_credit_note')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereColumn('journal.total_debit_minor', '<>', 'note.total_minor'))
            ->count();
        $invalidLines = DB::table('customer_credit_note_lines as line')->join('customer_credit_notes as note', 'note.id', '=', 'line.customer_credit_note_id')->join('customer_invoice_lines as source', 'source.id', '=', 'line.customer_invoice_line_id')->where('note.status', 'posted')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('source.customer_invoice_id', '<>', 'note.customer_invoice_id')->orWhereRaw('line.net_minor+line.tax_minor<>line.total_minor'))->count();
        $overCredit = DB::table('customer_credit_notes')->where('status', 'posted')->selectRaw('customer_invoice_id, SUM(total_minor) credited')->groupBy('customer_invoice_id');
        $overCreditCount = DB::query()->fromSub($overCredit, 'credit')->join('customer_invoices as invoice', 'invoice.id', '=', 'credit.customer_invoice_id')->whereColumn('credit.credited', '>', 'invoice.total_minor')->count();

        return $invalidHeaders + $invalidLines + $overCreditCount;
    }

    private function crmLeadWithoutCompany(): int
    {
        if (! Schema::hasTable('crm_leads')) {
            return 0;
        }

        return DB::table('crm_leads as lead')->leftJoin('companies as company', 'company.id', '=', 'lead.company_id')->leftJoin('branches as branch', 'branch.id', '=', 'lead.branch_id')->leftJoin('users as owner', 'owner.id', '=', 'lead.owner_id')->where(static fn (Builder $query): Builder => $query->whereNull('company.id')->orWhereNull('owner.id')->orWhere(static fn (Builder $branchScope): Builder => $branchScope->whereNotNull('lead.branch_id')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('branch.id')->orWhereColumn('branch.company_id', '<>', 'lead.company_id'))))->count();
    }

    private function crmOpportunityWithoutCompany(): int
    {
        if (! Schema::hasTable('crm_opportunities')) {
            return 0;
        }

        return DB::table('crm_opportunities as opportunity')->leftJoin('companies as company', 'company.id', '=', 'opportunity.company_id')->leftJoin('branches as branch', 'branch.id', '=', 'opportunity.branch_id')->leftJoin('crm_leads as lead', 'lead.id', '=', 'opportunity.lead_id')->leftJoin('commerce_customers as customer', 'customer.id', '=', 'opportunity.customer_id')->leftJoin('users as owner', 'owner.id', '=', 'opportunity.owner_id')->where(static fn (Builder $query): Builder => $query->whereNull('company.id')->orWhereNull('owner.id')->orWhere(static fn (Builder $branchScope): Builder => $branchScope->whereNotNull('opportunity.branch_id')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('branch.id')->orWhereColumn('branch.company_id', '<>', 'opportunity.company_id')))->orWhere(static fn (Builder $leadScope): Builder => $leadScope->whereNotNull('opportunity.lead_id')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('lead.id')->orWhereColumn('lead.company_id', '<>', 'opportunity.company_id')))->orWhere(static fn (Builder $customerScope): Builder => $customerScope->whereNotNull('opportunity.customer_id')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('customer.id')->orWhereColumn('customer.company_id', '<>', 'opportunity.company_id'))))->count();
    }

    private function crmInvalidStage(): int
    {
        if (! Schema::hasTable('crm_leads') || ! Schema::hasTable('crm_opportunities')) {
            return 0;
        }

        return DB::table('crm_leads')->whereNotIn('status', ['new', 'contacted', 'qualified', 'converted', 'lost'])->count()
            + DB::table('crm_opportunities')->whereNotIn('stage', ['discovery', 'qualification', 'proposal', 'negotiation', 'won', 'lost'])->count();
    }

    private function customerRefundWithoutCreditNote(): int
    {
        if (! Schema::hasTable('customer_refunds')) {
            return 0;
        }

        return DB::table('customer_refunds as refund')->whereNotExists(static fn (Builder $allocation): Builder => $allocation->selectRaw('1')->from('customer_refund_allocations as allocation')->whereColumn('allocation.customer_refund_id', 'refund.id'))->count()
            + DB::table('customer_refund_allocations as allocation')->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')->leftJoin('customer_credit_notes as note', 'note.id', '=', 'allocation.customer_credit_note_id')->where(static fn (Builder $query): Builder => $query->whereNull('note.id')->orWhere('note.status', '<>', 'posted')->orWhereColumn('note.company_id', '<>', 'refund.company_id')->orWhereColumn('note.branch_id', '<>', 'refund.branch_id')->orWhereColumn('note.customer_id', '<>', 'refund.customer_id')->orWhereColumn('note.currency', '<>', 'refund.currency'))->count();
    }

    private function customerRefundOverLiability(): int
    {
        if (! Schema::hasTable('customer_refunds')) {
            return 0;
        }
        $allocated = DB::table('customer_refund_allocations as allocation')->join('customer_refunds as refund', 'refund.id', '=', 'allocation.customer_refund_id')->whereIn('refund.status', ['draft', 'submitted', 'approved', 'posted'])->selectRaw('allocation.customer_credit_note_id, SUM(allocation.allocated_minor) allocated')->groupBy('allocation.customer_credit_note_id');

        return DB::query()->fromSub($allocated, 'allocated')->join('customer_credit_notes as note', 'note.id', '=', 'allocated.customer_credit_note_id')->whereColumn('allocated.allocated', '>', 'note.customer_credit_liability_minor')->count();
    }

    private function customerRefundWithoutTreasury(): int
    {
        if (! Schema::hasTable('customer_refunds')) {
            return 0;
        }

        return DB::table('customer_refunds as refund')->leftJoin('treasury_transactions as treasury', 'treasury.id', '=', 'refund.treasury_transaction_id')->leftJoin('treasury_financial_accounts as account', 'account.id', '=', 'refund.financial_account_id')->where('refund.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('treasury.id')->orWhereColumn('treasury.company_id', '<>', 'refund.company_id')->orWhereColumn('treasury.branch_id', '<>', 'refund.branch_id')->orWhereColumn('treasury.source_id', '<>', 'refund.id')->orWhere('treasury.source_type', '<>', 'customer_refund')->orWhere('treasury.direction', '<>', 'outflow')->orWhereColumn('treasury.amount_minor', '<>', 'refund.amount_minor')->orWhereColumn('treasury.financial_account_id', '<>', 'refund.financial_account_id')->orWhereNull('account.id')->orWhereNotIn('account.type', ['cash', 'bank']))->count();
    }

    private function customerRefundWithoutJournal(): int
    {
        if (! Schema::hasTable('customer_refunds')) {
            return 0;
        }

        return DB::table('customer_refunds as refund')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'refund.accounting_journal_id')->where('refund.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'refund.company_id')->orWhereColumn('journal.branch_id', '<>', 'refund.branch_id')->orWhereColumn('journal.source_id', '<>', 'refund.id')->orWhere('journal.source_type', '<>', 'customer_refund')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereColumn('journal.total_debit_minor', '<>', 'refund.amount_minor'))->count();
    }

    private function creditNoteWithoutInvoice(): int
    {
        if (! Schema::hasTable('customer_credit_notes')) {
            return 0;
        }

        return DB::table('customer_credit_notes as note')->leftJoin('customer_invoices as invoice', 'invoice.id', '=', 'note.customer_invoice_id')->where(static fn (Builder $query): Builder => $query->whereNull('invoice.id')->orWhereColumn('invoice.company_id', '<>', 'note.company_id')->orWhereColumn('invoice.branch_id', '<>', 'note.branch_id')->orWhereColumn('invoice.customer_id', '<>', 'note.customer_id')->orWhereColumn('invoice.currency', '<>', 'note.currency'))->count();
    }

    private function creditNoteOverRemainingCreditable(): int
    {
        if (! Schema::hasTable('customer_credit_notes')) {
            return 0;
        }
        $credits = DB::table('customer_credit_notes')->where('status', 'posted')->selectRaw('customer_invoice_id, SUM(total_minor) credited')->groupBy('customer_invoice_id');

        return DB::query()->fromSub($credits, 'credit')->join('customer_invoices as invoice', 'invoice.id', '=', 'credit.customer_invoice_id')->whereColumn('credit.credited', '>', 'invoice.total_minor')->count();
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
        $overReceived = DB::table('purchase_order_lines')->whereColumn('received_quantity', '>', 'ordered_quantity')->selectRaw("CONCAT('received:',id) issue_key");
        $billed = DB::table('vendor_bill_lines as l')->join('vendor_bills as b', 'b.id', '=', 'l.vendor_bill_id')->whereNotIn('b.status', ['cancelled', 'rejected'])->where('l.procurement_classification', 'inventory')->selectRaw('l.purchase_order_line_id, SUM(l.quantity) billed')->whereNotNull('l.purchase_order_line_id')->groupBy('l.purchase_order_line_id');
        $overBilled = DB::table('purchase_order_lines as p')->joinSub($billed, 'billed', 'billed.purchase_order_line_id', '=', 'p.id')->whereColumn('billed.billed', '>', 'p.received_quantity')->selectRaw("CONCAT('billed:',p.id) issue_key");

        if (! Schema::hasTable('procurement_goods_returns') || ! Schema::hasTable('procurement_goods_return_lines')) {
            return DB::query()->fromSub($overReceived->unionAll($overBilled), 'procurement_realization_issues')->count();
        }

        $returned = DB::table('procurement_goods_return_lines as line')->join('procurement_goods_returns as header', 'header.id', '=', 'line.goods_return_id')->where('header.status', 'posted')->selectRaw('line.goods_receipt_line_id, SUM(line.quantity) quantity')->groupBy('line.goods_receipt_line_id');
        $overReturned = DB::table('procurement_goods_receipt_lines as receipt_line')->joinSub($returned, 'returned', 'returned.goods_receipt_line_id', '=', 'receipt_line.id')->whereColumn('returned.quantity', '>', 'receipt_line.received_quantity')->selectRaw("CONCAT('returned:',receipt_line.id) issue_key");
        $returnLineage = DB::table('procurement_goods_returns as header')->join('procurement_goods_receipts as receipt', 'receipt.id', '=', 'header.goods_receipt_id')->join('purchase_orders as po', 'po.id', '=', 'header.purchase_order_id')->join('procurement_vendors as vendor', 'vendor.id', '=', 'header.vendor_id')->where(static fn (Builder $query): Builder => $query->whereColumn('receipt.company_id', '<>', 'header.company_id')->orWhereColumn('po.company_id', '<>', 'header.company_id')->orWhereColumn('vendor.company_id', '<>', 'header.company_id')->orWhereColumn('receipt.purchase_order_id', '<>', 'header.purchase_order_id')->orWhereColumn('po.vendor_id', '<>', 'header.vendor_id'))->selectRaw("CONCAT('return-lineage:',header.id) issue_key");
        $returnPosting = DB::table('procurement_goods_returns as header')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'header.accounting_journal_id')->where('header.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'header.company_id')->orWhereColumn('journal.source_id', '<>', 'header.id')->orWhere('journal.source_type', '<>', 'goods_return')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereNotExists(fn (Builder $movement): Builder => $movement->selectRaw('1')->from('procurement_goods_return_lines as posted_line')->join('inventory_movements as inventory_movement', 'inventory_movement.id', '=', 'posted_line.inventory_movement_id')->whereColumn('posted_line.goods_return_id', 'header.id')->where('inventory_movement.source_type', 'procurement_goods_return_line')->whereColumn('inventory_movement.source_id', 'posted_line.id')))->selectRaw("CONCAT('return-posting:',header.id) issue_key");

        return DB::query()->fromSub($overReceived->unionAll($overBilled)->unionAll($overReturned)->unionAll($returnLineage)->unionAll($returnPosting), 'procurement_realization_issues')->count();
    }

    private function invalidProcurementBudgetDimension(): int
    {
        return DB::table('purchase_order_lines as l')->join('purchase_orders as o', 'o.id', '=', 'l.purchase_order_id')->leftJoin('accounting_accounts as a', 'a.id', '=', 'l.account_id')->leftJoin('cost_centers as c', 'c.id', '=', 'l.cost_center_id')->whereNotNull('l.procurement_classification')->where(static fn (Builder $query): Builder => $query->whereNull('a.id')->orWhereColumn('a.company_id', '<>', 'o.company_id')->orWhere(static fn (Builder $center): Builder => $center->whereNotNull('l.cost_center_id')->whereColumn('c.company_id', '<>', 'o.company_id'))->orWhere(static fn (Builder $mapping): Builder => $mapping->where(static fn (Builder $inventory): Builder => $inventory->where('l.procurement_classification', 'inventory')->where('a.code', '<>', '1200'))->orWhere(static fn (Builder $asset): Builder => $asset->where('l.procurement_classification', 'asset')->where('a.code', '<>', '1300'))->orWhere(static fn (Builder $opex): Builder => $opex->where('l.procurement_classification', 'operating_expense')->where('a.account_type', '<>', 'expense'))))->count();
    }

    private function invalidSupplierCredit(): int
    {
        foreach (['procurement_goods_returns', 'procurement_goods_return_lines', 'supplier_credit_notes', 'supplier_credit_note_lines', 'vendor_refund_receivables', 'supplier_refund_receipts'] as $table) {
            if (! Schema::hasTable($table)) {
                return 0;
            }
        }

        $allocationTotals = DB::table('vendor_bill_receipt_allocations')->selectRaw('vendor_bill_line_id, SUM(quantity) quantity, SUM(allocated_minor) amount')->groupBy('vendor_bill_line_id');
        $invalidAllocations = DB::table('vendor_bill_lines as bill_line')->joinSub($allocationTotals, 'allocation', 'allocation.vendor_bill_line_id', '=', 'bill_line.id')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('allocation.quantity', '>', 'bill_line.quantity')->orWhereColumn('allocation.amount', '>', 'bill_line.line_total_minor'))
            ->selectRaw("CONCAT('allocation:',bill_line.id) issue_key");
        $creditLineage = DB::table('supplier_credit_note_lines as credit_line')
            ->join('supplier_credit_notes as credit', 'credit.id', '=', 'credit_line.supplier_credit_note_id')
            ->join('procurement_goods_return_lines as return_line', 'return_line.id', '=', 'credit_line.goods_return_line_id')
            ->join('procurement_goods_returns as goods_return', 'goods_return.id', '=', 'return_line.goods_return_id')
            ->join('vendor_bill_receipt_allocations as allocation', 'allocation.id', '=', 'credit_line.vendor_bill_receipt_allocation_id')
            ->join('vendor_bill_lines as bill_line', 'bill_line.id', '=', 'credit_line.vendor_bill_line_id')
            ->join('vendor_bills as bill', 'bill.id', '=', 'bill_line.vendor_bill_id')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('credit.company_id', '<>', 'goods_return.company_id')->orWhereColumn('credit.company_id', '<>', 'bill.company_id')->orWhereColumn('credit.goods_return_id', '<>', 'goods_return.id')->orWhereColumn('credit.vendor_bill_id', '<>', 'bill.id')->orWhereColumn('credit_line.goods_receipt_line_id', '<>', 'return_line.goods_receipt_line_id')->orWhereColumn('allocation.goods_receipt_line_id', '<>', 'return_line.goods_receipt_line_id')->orWhereColumn('credit_line.quantity', '>', 'return_line.quantity')->orWhereColumn('credit_line.quantity', '>', 'allocation.quantity')->orWhereColumn('credit_line.credit_minor', '>', 'allocation.allocated_minor'))
            ->selectRaw("CONCAT('credit-line:',credit_line.id) issue_key");
        $posted = DB::table('supplier_credit_notes as credit')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'credit.accounting_journal_id')->where('credit.status', 'posted')
            ->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'credit.company_id')->orWhereColumn('journal.source_id', '<>', 'credit.id')->orWhere('journal.source_type', '<>', 'supplier_credit')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereRaw('credit.ap_reduction_minor+credit.vendor_receivable_minor<>credit.total_minor')->orWhereRaw('(credit.vendor_receivable_minor>0 AND NOT EXISTS (SELECT 1 FROM vendor_refund_receivables receivable WHERE receivable.supplier_credit_note_id=credit.id AND receivable.company_id=credit.company_id AND receivable.original_minor=credit.vendor_receivable_minor))'))
            ->selectRaw("CONCAT('credit-posting:',credit.id) issue_key");
        $receivables = DB::table('vendor_refund_receivables as receivable')->join('supplier_credit_notes as credit', 'credit.id', '=', 'receivable.supplier_credit_note_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'receivable.accounting_journal_id')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('receivable.company_id', '<>', 'credit.company_id')->orWhereColumn('receivable.vendor_id', '<>', 'credit.vendor_id')->orWhereColumn('receivable.original_minor', '<>', 'credit.vendor_receivable_minor')->orWhereColumn('receivable.refunded_minor', '>', 'receivable.original_minor')->orWhereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'receivable.company_id')->orWhereRaw("(receivable.status='refunded' AND receivable.refunded_minor<>receivable.original_minor)"))
            ->selectRaw("CONCAT('vendor-receivable:',receivable.id) issue_key");
        $refunds = DB::table('supplier_refund_receipts as refund')->join('vendor_refund_receivables as receivable', 'receivable.id', '=', 'refund.vendor_refund_receivable_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'refund.accounting_journal_id')->leftJoin('treasury_transactions as treasury', 'treasury.id', '=', 'refund.treasury_transaction_id')->where('refund.status', 'posted')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('refund.company_id', '<>', 'receivable.company_id')->orWhereColumn('refund.vendor_id', '<>', 'receivable.vendor_id')->orWhereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'refund.company_id')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereNull('treasury.id')->orWhereColumn('treasury.company_id', '<>', 'refund.company_id')->orWhereColumn('treasury.amount_minor', '<>', 'refund.amount_minor')->orWhere('treasury.direction', '<>', 'inflow'))
            ->selectRaw("CONCAT('supplier-refund:',refund.id) issue_key");

        return DB::query()->fromSub($invalidAllocations->unionAll($creditLineage)->unionAll($posted)->unionAll($receivables)->unionAll($refunds), 'supplier_credit_issues')->count();
    }

    private function invalidServiceAcceptance(): int
    {
        try {
            $scopeIssues = DB::table('service_acceptances as sa')->join('purchase_orders as po', 'po.id', '=', 'sa.purchase_order_id')->join('procurement_vendors as v', 'v.id', '=', 'sa.vendor_id')->join('branches as b', 'b.id', '=', 'sa.branch_id')->where(static fn (Builder $q): Builder => $q->whereColumn('po.company_id', '<>', 'sa.company_id')->orWhereColumn('v.company_id', '<>', 'sa.company_id')->orWhereColumn('b.company_id', '<>', 'sa.company_id'))->selectRaw('1 issue_count');
            $documentIssues = DB::table('enterprise_documents as document')->leftJoin('service_acceptances as sa', 'sa.id', '=', 'document.subject_id')->where('document.subject_type', 'SERVICE_ACCEPTANCE')->where(static fn (Builder $q): Builder => $q->whereNull('sa.id')->orWhereColumn('sa.company_id', '<>', 'document.company_id'))->selectRaw('1 issue_count');
            $workflowIssues = DB::table('workflow_instances as workflow')->leftJoin('service_acceptances as sa', 'sa.id', '=', 'workflow.subject_id')->whereIn('workflow.subject_type', ['SERVICE_ACCEPTANCE_APPROVAL', 'SERVICE_ACCEPTANCE_REVERSAL_APPROVAL'])->where(static fn (Builder $q): Builder => $q->whereNull('sa.id')->orWhereColumn('sa.company_id', '<>', 'workflow.company_id'))->selectRaw('1 issue_count');
            $duplicateWorkflowIssues = DB::table('workflow_instances')->whereIn('subject_type', ['SERVICE_ACCEPTANCE_APPROVAL', 'SERVICE_ACCEPTANCE_REVERSAL_APPROVAL'])->where('engine_status', 'running')->selectRaw('COUNT(*) - 1 issue_count')->groupBy('company_id', 'subject_type', 'subject_id')->havingRaw('COUNT(*) > 1');
            $scope = $scopeIssues->unionAll($documentIssues)->unionAll($workflowIssues)->unionAll($duplicateWorkflowIssues)->get()->sum(static function (object $row): int {
                $issueCount = $row->issue_count ?? null;

                if (! is_int($issueCount) && ! is_string($issueCount)) {
                    return 0;
                }

                return max(0, intval($issueCount));
            });
            $approved = DB::table('service_acceptance_lines as sal')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where('sa.status', 'approved')->selectRaw('sal.purchase_order_line_id, SUM(sal.accepted_quantity) accepted')->groupBy('sal.purchase_order_line_id');
            $over = DB::table('purchase_order_lines as pol')->joinSub($approved, 'accepted', 'accepted.purchase_order_line_id', '=', 'pol.id')->whereColumn('accepted.accepted', '>', 'pol.ordered_quantity')->count();
            $journal = DB::table('service_acceptances as sa')->leftJoin('payroll_accounting_journals as original', 'original.id', '=', 'sa.accounting_journal_id')->leftJoin('payroll_accounting_journals as reversal', 'reversal.id', '=', 'sa.reversal_journal_id')->whereIn('sa.status', ['approved', 'reversed'])->where(static fn (Builder $q): Builder => $q
                ->where(static fn (Builder $approved): Builder => $approved->where('sa.status', 'approved')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('original.id')->orWhereColumn('original.company_id', '<>', 'sa.company_id')->orWhereColumn('original.total_debit_minor', '<>', 'sa.total_minor')->orWhereColumn('original.total_credit_minor', '<>', 'sa.total_minor')))
                ->orWhere(static fn (Builder $reversed): Builder => $reversed->where('sa.status', 'reversed')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('reversal.id')->orWhereColumn('reversal.company_id', '<>', 'sa.company_id')->orWhereColumn('reversal.reversal_of_journal_id', '<>', 'original.id')->orWhereColumn('reversal.total_debit_minor', '<>', 'original.total_credit_minor')->orWhereColumn('reversal.total_credit_minor', '<>', 'original.total_debit_minor'))))->count();

            return $scope + $over + $journal;
        } catch (QueryException $exception) {
            if ($this->isMissingServiceAcceptanceTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    private function invalidServiceBilling(): int
    {
        try {
            $billed = DB::table('vendor_bill_lines as vbl')->join('vendor_bills as vb', 'vb.id', '=', 'vbl.vendor_bill_id')->whereNotIn('vb.status', ['cancelled', 'rejected'])->whereNotNull('vbl.service_acceptance_line_id')->selectRaw('vbl.service_acceptance_line_id, SUM(vbl.quantity) billed')->groupBy('vbl.service_acceptance_line_id');

            return DB::table('service_acceptance_lines as sal')->joinSub($billed, 'billed', 'billed.service_acceptance_line_id', '=', 'sal.id')->join('service_acceptances as sa', 'sa.id', '=', 'sal.service_acceptance_id')->where(static fn (Builder $q): Builder => $q->where('sa.status', '<>', 'approved')->orWhereColumn('billed.billed', '>', 'sal.accepted_quantity'))->count();
        } catch (QueryException $exception) {
            if ($this->isMissingServiceAcceptanceTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    private function inventoryStockCountWithoutWarehouse(): int
    {
        if (! Schema::hasTable('inventory_stock_counts')) {
            return 0;
        }

        return DB::table('inventory_stock_counts as count')->leftJoin('inventory_locations as location', 'location.id', '=', 'count.inventory_location_id')->leftJoin('stores as store', 'store.id', '=', 'location.store_id')->leftJoin('seller_profiles as seller', 'seller.id', '=', 'store.seller_profile_id')->leftJoin('branches as branch', 'branch.id', '=', 'count.branch_id')->where(static fn (Builder $query): Builder => $query->whereNull('location.id')->orWhereNull('branch.id')->orWhereColumn('seller.company_id', '<>', 'count.company_id')->orWhereColumn('branch.company_id', '<>', 'count.company_id')->orWhereColumn('location.branch_id', '<>', 'count.branch_id'))->count();
    }

    private function inventoryAdjustmentWithoutReason(): int
    {
        if (! Schema::hasTable('inventory_adjustments')) {
            return 0;
        }

        return DB::table('inventory_adjustments')->whereNotIn('reason', ['STOCK_COUNT', 'DAMAGE', 'LOSS', 'EXPIRED', 'FOUND'])->count();
    }

    private function inventoryAdjustmentWithoutMovement(): int
    {
        if (! Schema::hasTable('inventory_adjustments')) {
            return 0;
        }

        return DB::table('inventory_adjustment_lines as line')->join('inventory_adjustments as adjustment', 'adjustment.id', '=', 'line.adjustment_id')->leftJoin('inventory_movements as movement', 'movement.id', '=', 'line.inventory_movement_id')->where('adjustment.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('movement.id')->orWhere('movement.source_type', '<>', 'inventory_adjustment_line')->orWhereColumn('movement.source_id', '<>', 'line.id')->orWhereColumn('movement.product_variant_id', '<>', 'line.product_variant_id')->orWhereColumn('movement.inventory_location_id', '<>', 'adjustment.inventory_location_id')->orWhereColumn('movement.quantity_delta', '<>', 'line.quantity_delta'))->count();
    }

    private function inventoryAdjustmentWithoutJournal(): int
    {
        if (! Schema::hasTable('inventory_adjustments')) {
            return 0;
        }

        return DB::table('inventory_adjustments as adjustment')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'adjustment.accounting_journal_id')->where('adjustment.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'adjustment.company_id')->orWhereColumn('journal.branch_id', '<>', 'adjustment.branch_id')->orWhereColumn('journal.source_id', '<>', 'adjustment.id')->orWhere('journal.source_type', '<>', 'inventory_adjustment')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
    }

    private function inventoryVarianceMismatch(): int
    {
        if (! Schema::hasTable('inventory_stock_count_lines') || ! Schema::hasTable('inventory_adjustment_lines')) {
            return 0;
        }
        $countVariance = DB::table('inventory_stock_count_lines')->whereRaw('variance_qty <> CAST(counted_qty AS SIGNED) - CAST(system_qty AS SIGNED)')->count();
        $adjustmentVariance = DB::table('inventory_adjustment_lines as adjustment_line')->join('inventory_adjustments as adjustment', 'adjustment.id', '=', 'adjustment_line.adjustment_id')->join('inventory_stock_count_lines as count_line', static function (JoinClause $join): void {
            $join->on('count_line.stock_count_id', '=', 'adjustment.stock_count_id')->on('count_line.product_variant_id', '=', 'adjustment_line.product_variant_id');
        })->whereColumn('adjustment_line.quantity_delta', '<>', 'count_line.variance_qty')->count();

        return $countVariance + $adjustmentVariance;
    }

    private function assetImpairmentWithoutAsset(): int
    {
        if (! Schema::hasTable('finance_asset_impairments')) {
            return 0;
        }

        return DB::table('finance_asset_impairments as document')->leftJoin('finance_assets as asset', 'asset.id', '=', 'document.asset_id')->where(static fn (Builder $query): Builder => $query->whereNull('asset.id')->orWhereColumn('asset.company_id', '<>', 'document.company_id'))->count();
    }

    private function assetImpairmentWithoutJournal(): int
    {
        if (! Schema::hasTable('finance_asset_impairments')) {
            return 0;
        }

        return DB::table('finance_asset_impairments as document')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'document.accounting_journal_id')->where('document.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'document.company_id')->orWhereColumn('journal.source_id', '<>', 'document.id')->orWhere('journal.source_type', '<>', 'asset_impairment')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
    }

    private function assetDisposalWithoutAsset(): int
    {
        if (! Schema::hasTable('finance_asset_disposals')) {
            return 0;
        }

        return DB::table('finance_asset_disposals as document')->leftJoin('finance_assets as asset', 'asset.id', '=', 'document.asset_id')->where(static fn (Builder $query): Builder => $query->whereNull('asset.id')->orWhereColumn('asset.company_id', '<>', 'document.company_id'))->count();
    }

    private function assetDisposalWithoutJournal(): int
    {
        if (! Schema::hasTable('finance_asset_disposals')) {
            return 0;
        }

        return DB::table('finance_asset_disposals as document')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'document.accounting_journal_id')->where('document.status', 'posted')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'document.company_id')->orWhereColumn('journal.source_id', '<>', 'document.id')->orWhere('journal.source_type', '<>', 'asset_disposal')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
    }

    private function assetGainLossMismatch(): int
    {
        if (! Schema::hasTable('finance_asset_disposals')) {
            return 0;
        }

        return DB::table('finance_asset_disposals')->whereRaw('gain_loss_minor <> proceeds_minor - book_value_minor')->count();
    }

    private function fiscalYearWithoutCompany(): int
    {
        if (! Schema::hasTable('accounting_fiscal_years')) {
            return 0;
        }

        return DB::table('accounting_fiscal_years as fiscal')->leftJoin('companies as company', 'company.id', '=', 'fiscal.company_id')->whereNull('company.id')->count();
    }

    private function periodWithoutFiscalYear(): int
    {
        if (! Schema::hasColumn('accounting_periods', 'fiscal_year_id')) {
            return 0;
        }

        return DB::table('accounting_periods as period')->leftJoin('accounting_fiscal_years as fiscal', 'fiscal.id', '=', 'period.fiscal_year_id')->where(static fn (Builder $query): Builder => $query->whereNull('fiscal.id')->orWhereColumn('fiscal.company_id', '<>', 'period.company_id'))->count();
    }

    private function closedPeriodWithPosting(): int
    {
        if (! Schema::hasColumn('accounting_periods', 'fiscal_year_id')) {
            return 0;
        }

        return DB::table('payroll_accounting_journals as journal')->join('accounting_periods as period', 'period.id', '=', 'journal.accounting_period_id')->where('period.status', 'closed')->whereNotNull('period.closed_at')->whereColumn('journal.posted_at', '>', 'period.closed_at')->where('journal.source_type', '<>', 'fiscal_year_close')->count();
    }

    private function overlappingFiscalYear(): int
    {
        if (! Schema::hasTable('accounting_fiscal_years')) {
            return 0;
        }

        return DB::table('accounting_fiscal_years as left_year')->join('accounting_fiscal_years as right_year', static function (JoinClause $join): void {
            $join->on('right_year.company_id', '=', 'left_year.company_id')->whereColumn('right_year.id', '>', 'left_year.id')->whereColumn('right_year.start_date', '<=', 'left_year.end_date')->whereColumn('right_year.end_date', '>=', 'left_year.start_date');
        })->count();
    }

    private function invalidPeriodDateRange(): int
    {
        if (! Schema::hasColumn('accounting_periods', 'fiscal_year_id')) {
            return 0;
        }

        return DB::table('accounting_periods as period')->join('accounting_fiscal_years as fiscal', 'fiscal.id', '=', 'period.fiscal_year_id')->where(static fn (Builder $query): Builder => $query->whereColumn('period.start_date', '>', 'period.end_date')->orWhereColumn('period.start_date', '<', 'fiscal.start_date')->orWhereColumn('period.end_date', '>', 'fiscal.end_date'))->count();
    }

    private function openingBalanceWithoutSource(): int
    {
        if (! Schema::hasTable('accounting_opening_balance_lines')) {
            return 0;
        }

        return DB::table('accounting_opening_balance_lines as line')->leftJoin('accounting_fiscal_year_close_requests as close', 'close.id', '=', 'line.fiscal_year_close_request_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'line.opening_journal_id')->where(static fn (Builder $query): Builder => $query->whereNull('close.id')->orWhereNull('journal.id')->orWhereColumn('close.company_id', '<>', 'line.company_id')->orWhereColumn('journal.company_id', '<>', 'line.company_id')->orWhere('journal.source_type', '<>', 'fiscal_year_carry_forward')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
    }

    private function duplicateInvoiceFxRecognition(): int
    {
        return $this->arFxSchemaReady() ? DB::query()->fromSub(DB::table('customer_invoice_fx_recognitions')->selectRaw('customer_invoice_id, COUNT(*) total')->groupBy('customer_invoice_id')->havingRaw('COUNT(*) > 1'), 'duplicate')->count() : 0;
    }

    private function recognitionCompanyMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_invoice_fx_recognitions as recognition')->leftJoin('customer_invoices as invoice', 'invoice.id', '=', 'recognition.customer_invoice_id')->where(static fn (Builder $query): Builder => $query->whereNull('invoice.id')->orWhereColumn('invoice.company_id', '<>', 'recognition.company_id')->orWhereColumn('invoice.branch_id', '<>', 'recognition.branch_id'))->count() : 0;
    }

    private function recognitionInvoiceMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_invoice_fx_recognitions as recognition')->join('customer_invoices as invoice', 'invoice.id', '=', 'recognition.customer_invoice_id')->where(static fn (Builder $query): Builder => $query->whereColumn('invoice.currency', '<>', 'recognition.transaction_currency')->orWhereColumn('invoice.total_minor', '<>', 'recognition.transaction_amount_minor'))->count() : 0;
    }

    private function recognitionFunctionalCurrencyMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_invoice_fx_recognitions as recognition')->join('companies as company', 'company.id', '=', 'recognition.company_id')->where(static fn (Builder $query): Builder => $query->whereNull('company.functional_currency_code')->orWhereColumn('company.functional_currency_code', '<>', 'recognition.functional_currency'))->count() : 0;
    }

    private function recognitionSnapshotPairMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_invoice_fx_recognitions as recognition')->leftJoin('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'recognition.fx_rate_snapshot_id')->where('recognition.rate_type', '<>', 'identity')->where(static fn (Builder $query): Builder => $query->whereNull('snapshot.id')->orWhereColumn('snapshot.company_id', '<>', 'recognition.company_id')->orWhereColumn('snapshot.base_currency', '<>', 'recognition.transaction_currency')->orWhereColumn('snapshot.quote_currency', '<>', 'recognition.functional_currency'))->count() : 0;
    }

    private function recognitionSnapshotRateMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_invoice_fx_recognitions as recognition')->join('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'recognition.fx_rate_snapshot_id')->where(static fn (Builder $query): Builder => $query->whereColumn('snapshot.rate_decimal', '<>', 'recognition.rate_decimal')->orWhereColumn('snapshot.rate_date', '<>', 'recognition.rate_date')->orWhereColumn('snapshot.rate_type', '<>', 'recognition.rate_type'))->count() : 0;
    }

    private function recognitionIdentitySnapshotInvalid(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_invoice_fx_recognitions')->where('rate_type', 'identity')->where(static fn (Builder $query): Builder => $query->whereNotNull('fx_rate_snapshot_id')->orWhere('rate_decimal', '<>', '1.000000000000')->orWhereColumn('transaction_currency', '<>', 'functional_currency'))->count() : 0;
    }

    private function recognitionFunctionalJournalMismatch(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }

        return DB::table('customer_invoice_fx_recognitions as recognition')->join('customer_invoices as invoice', 'invoice.id', '=', 'recognition.customer_invoice_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'invoice.accounting_journal_id')->whereIn('invoice.status', ['issued', 'partially_paid', 'paid'])->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'recognition.company_id')->orWhereColumn('journal.currency', '<>', 'recognition.functional_currency')->orWhereColumn('journal.total_debit_minor', '<>', 'recognition.functional_amount_minor')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
    }

    private function duplicateAllocationFxSettlement(): int
    {
        return $this->arFxSchemaReady() ? DB::query()->fromSub(DB::table('customer_collection_allocation_fx_settlements')->selectRaw('customer_collection_allocation_id, COUNT(*) total')->groupBy('customer_collection_allocation_id')->havingRaw('COUNT(*) > 1'), 'duplicate')->count() : 0;
    }

    private function settlementRecognitionLineageMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_collection_allocation_fx_settlements as settlement')->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')->join('customer_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'settlement.customer_invoice_fx_recognition_id')->where(static fn (Builder $query): Builder => $query->whereColumn('allocation.customer_invoice_id', '<>', 'recognition.customer_invoice_id')->orWhereColumn('settlement.company_id', '<>', 'recognition.company_id'))->count() : 0;
    }

    private function settlementWithoutRecognition(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_collection_allocation_fx_settlements as settlement')->leftJoin('customer_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'settlement.customer_invoice_fx_recognition_id')->whereNull('recognition.id')->count() : 0;
    }

    private function settlementCurrencyMismatch(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }

        return DB::table('customer_collection_allocation_fx_settlements as settlement')->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')->join('customer_invoices as invoice', 'invoice.id', '=', 'allocation.customer_invoice_id')->join('customer_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'settlement.customer_invoice_fx_recognition_id')->where(static fn (Builder $query): Builder => $query->whereColumn('settlement.transaction_currency', '<>', 'collection.currency')->orWhereColumn('settlement.transaction_currency', '<>', 'invoice.currency')->orWhereColumn('settlement.transaction_currency', '<>', 'recognition.transaction_currency')->orWhereColumn('settlement.functional_currency', '<>', 'recognition.functional_currency')->orWhereColumn('settlement.company_id', '<>', 'collection.company_id'))->count();
    }

    private function settlementSnapshotPairMismatch(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }

        return DB::table('customer_collection_allocation_fx_settlements as settlement')->leftJoin('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'settlement.settlement_fx_snapshot_id')->where(static fn (Builder $query): Builder => $query->where(static fn (Builder $foreign): Builder => $foreign->where('settlement.rate_type', '<>', 'identity')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('snapshot.id')->orWhereColumn('snapshot.company_id', '<>', 'settlement.company_id')->orWhereColumn('snapshot.base_currency', '<>', 'settlement.transaction_currency')->orWhereColumn('snapshot.quote_currency', '<>', 'settlement.functional_currency')->orWhereColumn('snapshot.rate_decimal', '<>', 'settlement.rate_decimal')->orWhereColumn('snapshot.rate_date', '<>', 'settlement.rate_date')))->orWhere(static fn (Builder $identity): Builder => $identity->where('settlement.rate_type', 'identity')->where(static fn (Builder $invalid): Builder => $invalid->whereNotNull('settlement.settlement_fx_snapshot_id')->orWhereColumn('settlement.transaction_currency', '<>', 'settlement.functional_currency')->orWhere('settlement.rate_decimal', '<>', '1.000000000000'))))->count();
    }

    private function releasedCarryingExceedsRecognition(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }
        $released = DB::table('customer_collection_allocation_fx_settlements')->selectRaw('customer_invoice_fx_recognition_id, SUM(released_carrying_minor) released')->groupBy('customer_invoice_fx_recognition_id');

        return DB::table('customer_invoice_fx_recognitions as recognition')->joinSub($released, 'settlement', 'settlement.customer_invoice_fx_recognition_id', '=', 'recognition.id')->whereColumn('settlement.released', '>', 'recognition.functional_amount_minor')->count();
    }

    private function settledForeignExceedsInvoice(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }
        $settled = DB::table('customer_collection_allocation_fx_settlements')->selectRaw('customer_invoice_fx_recognition_id, SUM(foreign_settled_minor) settled')->groupBy('customer_invoice_fx_recognition_id');

        return DB::table('customer_invoice_fx_recognitions as recognition')->joinSub($settled, 'settlement', 'settlement.customer_invoice_fx_recognition_id', '=', 'recognition.id')->whereColumn('settlement.settled', '>', 'recognition.transaction_amount_minor')->count();
    }

    private function settlementRealizedFxArithmeticMismatch(): int
    {
        return $this->arFxSchemaReady() ? DB::table('customer_collection_allocation_fx_settlements')->whereRaw('realized_fx_minor <> settlement_functional_minor - released_carrying_minor')->count() : 0;
    }

    private function settlementJournalCurrencyMismatch(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }

        return DB::table('customer_collection_allocation_fx_settlements as settlement')->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'collection.accounting_journal_id')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('journal.company_id', '<>', 'settlement.company_id')->orWhereColumn('journal.currency', '<>', 'settlement.functional_currency')->orWhereRaw('journal.total_debit_minor <> GREATEST(settlement.settlement_functional_minor, settlement.released_carrying_minor)')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))->count();
    }

    private function settlementTreasuryCurrencyMismatch(): int
    {
        if (! $this->arFxSchemaReady()) {
            return 0;
        }

        return DB::table('customer_collection_allocation_fx_settlements as settlement')->join('customer_collection_allocations as allocation', 'allocation.id', '=', 'settlement.customer_collection_allocation_id')->join('customer_collections as collection', 'collection.id', '=', 'allocation.customer_collection_id')->leftJoin('treasury_transactions as treasury', 'treasury.id', '=', 'collection.treasury_transaction_id')->leftJoin('treasury_financial_accounts as account', 'account.id', '=', 'treasury.financial_account_id')->where(static fn (Builder $query): Builder => $query->whereNull('treasury.id')->orWhereNull('account.id')->orWhereColumn('treasury.company_id', '<>', 'settlement.company_id')->orWhereColumn('treasury.currency', '<>', 'settlement.transaction_currency')->orWhereColumn('account.currency', '<>', 'settlement.transaction_currency')->orWhereColumn('treasury.amount_minor', '<>', 'settlement.foreign_settled_minor'))->count();
    }

    private function duplicateSupplierInvoiceFxRecognition(): int
    {
        return $this->apFxSchemaReady() ? DB::query()->fromSub(DB::table('supplier_invoice_fx_recognitions')->selectRaw('vendor_bill_id, COUNT(*) total')->groupBy('vendor_bill_id')->havingRaw('COUNT(*) > 1'), 'duplicate')->count() : 0;
    }

    private function supplierInvoiceFxScopeMismatch(): int
    {
        return $this->apFxSchemaReady() ? DB::table('supplier_invoice_fx_recognitions as recognition')->leftJoin('vendor_bills as bill', 'bill.id', '=', 'recognition.vendor_bill_id')->where(static fn (Builder $query): Builder => $query->whereNull('bill.id')->orWhereColumn('bill.company_id', '<>', 'recognition.company_id')->orWhereRaw('NOT (bill.branch_id <=> recognition.branch_id)')->orWhereColumn('bill.currency', '<>', 'recognition.transaction_currency')->orWhereColumn('bill.total_minor', '<>', 'recognition.transaction_amount_minor'))->count() : 0;
    }

    private function supplierInvoiceFxFunctionalCurrencyMismatch(): int
    {
        return $this->apFxSchemaReady() ? DB::table('supplier_invoice_fx_recognitions as recognition')->join('companies as company', 'company.id', '=', 'recognition.company_id')->where(static fn (Builder $query): Builder => $query->whereNull('company.functional_currency_code')->orWhereColumn('company.functional_currency_code', '<>', 'recognition.functional_currency'))->count() : 0;
    }

    private function supplierInvoiceFxSnapshotPairMismatch(): int
    {
        return $this->apFxSchemaReady() ? DB::table('supplier_invoice_fx_recognitions as recognition')->leftJoin('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'recognition.fx_rate_snapshot_id')->where('recognition.rate_type', '<>', 'identity')->where(static fn (Builder $query): Builder => $query->whereNull('snapshot.id')->orWhereColumn('snapshot.company_id', '<>', 'recognition.company_id')->orWhereColumn('snapshot.base_currency', '<>', 'recognition.transaction_currency')->orWhereColumn('snapshot.quote_currency', '<>', 'recognition.functional_currency'))->count() : 0;
    }

    private function supplierInvoiceFxSnapshotRateMismatch(): int
    {
        return $this->apFxSchemaReady() ? DB::table('supplier_invoice_fx_recognitions as recognition')->join('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'recognition.fx_rate_snapshot_id')->where(static fn (Builder $query): Builder => $query->whereColumn('snapshot.rate_decimal', '<>', 'recognition.rate_decimal')->orWhereColumn('snapshot.rate_date', '<>', 'recognition.rate_date')->orWhereColumn('snapshot.rate_type', '<>', 'recognition.rate_type')->orWhere('snapshot.source_type', '<>', 'supplier_invoice_recognition')->orWhereColumn('snapshot.source_id', '<>', 'recognition.vendor_bill_id'))->count() : 0;
    }

    private function supplierInvoiceFxIdentityInvalid(): int
    {
        return $this->apFxSchemaReady() ? DB::table('supplier_invoice_fx_recognitions')->where('rate_type', 'identity')->where(static fn (Builder $query): Builder => $query->whereNotNull('fx_rate_snapshot_id')->orWhere('rate_decimal', '<>', '1.000000000000')->orWhereColumn('transaction_currency', '<>', 'functional_currency'))->count() : 0;
    }

    private function supplierInvoiceFxJournalMismatch(): int
    {
        if (! $this->apFxSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_invoice_fx_recognitions as recognition')->join('vendor_bills as bill', 'bill.id', '=', 'recognition.vendor_bill_id')->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'recognition.accounting_journal_id')->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereColumn('bill.accounting_journal_id', '<>', 'journal.id')->orWhereColumn('journal.company_id', '<>', 'recognition.company_id')->orWhereColumn('journal.currency', '<>', 'recognition.functional_currency')->orWhereColumn('journal.total_debit_minor', '<>', 'recognition.functional_amount_minor')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereColumn('bill.accounting_posting_policy_id', '<>', 'recognition.accounting_policy_id')->orWhereColumn('bill.accounting_posting_policy_version', '<>', 'recognition.accounting_policy_version'))->count();
    }

    private function supplierPaymentFxOrphanSettlement(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_payment_fx_settlements as settlement')
            ->leftJoin('ap_payments as payment', 'payment.id', '=', 'settlement.ap_payment_id')
            ->leftJoin('ap_payment_allocations as allocation', 'allocation.id', '=', 'settlement.ap_payment_allocation_id')
            ->leftJoin('supplier_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'settlement.supplier_invoice_fx_recognition_id')
            ->leftJoin('vendor_bills as bill', 'bill.id', '=', 'recognition.vendor_bill_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('payment.id')->orWhereNull('allocation.id')->orWhereNull('recognition.id')->orWhereNull('bill.id'))
            ->count();
    }

    private function supplierPaymentFxScopeMismatch(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_payment_fx_settlements as settlement')
            ->join('ap_payments as payment', 'payment.id', '=', 'settlement.ap_payment_id')
            ->join('ap_payment_allocations as allocation', 'allocation.id', '=', 'settlement.ap_payment_allocation_id')
            ->join('supplier_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'settlement.supplier_invoice_fx_recognition_id')
            ->where(static fn (Builder $query): Builder => $query->whereColumn('allocation.payment_id', '<>', 'payment.id')->orWhereColumn('allocation.vendor_bill_id', '<>', 'recognition.vendor_bill_id')->orWhereColumn('payment.company_id', '<>', 'settlement.company_id')->orWhereColumn('recognition.company_id', '<>', 'settlement.company_id'))
            ->count();
    }

    private function supplierPaymentFxSnapshotMismatch(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_payment_fx_settlements as settlement')
            ->leftJoin('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'settlement.settlement_fx_snapshot_id')
            ->where(static fn (Builder $query): Builder => $query->where(static fn (Builder $foreign): Builder => $foreign->where('settlement.rate_type', '<>', 'identity')->where(static fn (Builder $invalid): Builder => $invalid->whereNull('snapshot.id')->orWhereColumn('snapshot.company_id', '<>', 'settlement.company_id')->orWhereColumn('snapshot.base_currency', '<>', 'settlement.transaction_currency')->orWhereColumn('snapshot.quote_currency', '<>', 'settlement.functional_currency')->orWhereColumn('snapshot.rate_decimal', '<>', 'settlement.rate_decimal')->orWhereColumn('snapshot.rate_date', '<>', 'settlement.rate_date')->orWhere('snapshot.rate_type', '<>', 'spot')->orWhere('snapshot.source_type', '<>', 'supplier_payment_allocation_settlement')->orWhereColumn('snapshot.source_id', '<>', 'settlement.ap_payment_allocation_id')))->orWhere(static fn (Builder $identity): Builder => $identity->where('settlement.rate_type', 'identity')->where(static fn (Builder $invalid): Builder => $invalid->whereNotNull('settlement.settlement_fx_snapshot_id')->orWhereColumn('settlement.transaction_currency', '<>', 'settlement.functional_currency')->orWhere('settlement.rate_decimal', '<>', '1.000000000000'))))
            ->count();
    }

    private function supplierPaymentFxCurrencyMismatch(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_payment_fx_settlements as settlement')
            ->join('ap_payments as payment', 'payment.id', '=', 'settlement.ap_payment_id')
            ->join('supplier_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'settlement.supplier_invoice_fx_recognition_id')
            ->leftJoin('treasury_financial_accounts as account', 'account.id', '=', 'settlement.treasury_financial_account_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('account.id')->orWhereColumn('payment.currency', '<>', 'settlement.transaction_currency')->orWhereColumn('recognition.transaction_currency', '<>', 'settlement.transaction_currency')->orWhereColumn('recognition.functional_currency', '<>', 'settlement.functional_currency')->orWhereColumn('account.currency', '<>', 'settlement.transaction_currency')->orWhereColumn('account.company_id', '<>', 'settlement.company_id'))
            ->count();
    }

    private function supplierPaymentFxReleasedBasisMismatch(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }
        $totals = DB::table('supplier_payment_fx_settlements')->selectRaw('supplier_invoice_fx_recognition_id, SUM(foreign_settled_minor) foreign_settled, SUM(released_carrying_minor) released')->groupBy('supplier_invoice_fx_recognition_id');

        return DB::table('supplier_invoice_fx_recognitions as recognition')->joinSub($totals, 'settlement', 'settlement.supplier_invoice_fx_recognition_id', '=', 'recognition.id')->where(static fn (Builder $query): Builder => $query->whereColumn('settlement.foreign_settled', '>', 'recognition.transaction_amount_minor')->orWhereColumn('settlement.released', '>', 'recognition.functional_amount_minor'))->count();
    }

    private function supplierPaymentFxRealizedMismatch(): int
    {
        return $this->apFxSettlementSchemaReady()
            ? DB::table('supplier_payment_fx_settlements')->whereRaw('realized_fx_minor <> CAST(released_carrying_minor AS SIGNED) - CAST(settlement_functional_minor AS SIGNED)')->count()
            : 0;
    }

    private function supplierPaymentFxJournalMismatch(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_payment_fx_settlements as settlement')
            ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'settlement.accounting_journal_id')
            ->leftJoin('accounting_posting_policies as policy', 'policy.id', '=', 'settlement.accounting_policy_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('journal.id')->orWhereNull('policy.id')->orWhereColumn('journal.company_id', '<>', 'settlement.company_id')->orWhereColumn('journal.currency', '<>', 'settlement.functional_currency')->orWhere('journal.source_type', '<>', 'ap_payment')->orWhereColumn('journal.source_id', '<>', 'settlement.ap_payment_id')->orWhereColumn('policy.company_id', '<>', 'settlement.company_id')->orWhereColumn('policy.version', '<>', 'settlement.accounting_policy_version')->orWhereRaw('journal.total_debit_minor <> GREATEST(settlement.settlement_functional_minor, settlement.released_carrying_minor)')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor'))
            ->count();
    }

    private function supplierPaymentFxTreasuryMismatch(): int
    {
        if (! $this->apFxSettlementSchemaReady()) {
            return 0;
        }

        return DB::table('supplier_payment_fx_settlements as settlement')
            ->leftJoin('treasury_transactions as treasury', 'treasury.id', '=', 'settlement.treasury_transaction_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('treasury.id')->orWhereColumn('treasury.company_id', '<>', 'settlement.company_id')->orWhere('treasury.source_type', '<>', 'ap_payment')->orWhereColumn('treasury.source_id', '<>', 'settlement.ap_payment_id')->orWhere('treasury.direction', '<>', 'outflow')->orWhereColumn('treasury.financial_account_id', '<>', 'settlement.treasury_financial_account_id')->orWhereColumn('treasury.currency', '<>', 'settlement.transaction_currency')->orWhereColumn('treasury.amount_minor', '<>', 'settlement.foreign_settled_minor')->orWhereColumn('treasury.accounting_journal_id', '<>', 'settlement.accounting_journal_id'))
            ->count();
    }

    private function inventoryReceiptFxOrphanOrScopeMismatch(): int
    {
        if (! $this->inventoryReceiptFxSchemaReady()) {
            return 0;
        }

        return DB::table('inventory_receipt_fx_valuations as valuation')
            ->leftJoin('procurement_goods_receipt_lines as line', 'line.id', '=', 'valuation.goods_receipt_line_id')
            ->leftJoin('procurement_goods_receipts as receipt', 'receipt.id', '=', 'line.goods_receipt_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'receipt.purchase_order_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('line.id')->orWhereNull('receipt.id')->orWhereNull('po.id')->orWhereColumn('valuation.goods_receipt_id', '<>', 'receipt.id')->orWhereColumn('valuation.purchase_order_id', '<>', 'po.id')->orWhereColumn('valuation.company_id', '<>', 'receipt.company_id')->orWhereColumn('valuation.branch_id', '<>', 'receipt.branch_id')->orWhereColumn('valuation.transaction_currency', '<>', 'po.currency'))
            ->count();
    }

    private function inventoryReceiptFxSnapshotMismatch(): int
    {
        if (! $this->inventoryReceiptFxSchemaReady()) {
            return 0;
        }

        return DB::table('inventory_receipt_fx_valuations as valuation')
            ->leftJoin('finance_fx_rate_snapshots as snapshot', 'snapshot.id', '=', 'valuation.fx_rate_snapshot_id')
            ->where('valuation.rate_type', '<>', 'identity')
            ->where(static fn (Builder $query): Builder => $query->whereNull('snapshot.id')->orWhereColumn('snapshot.company_id', '<>', 'valuation.company_id')->orWhereColumn('snapshot.base_currency', '<>', 'valuation.transaction_currency')->orWhereColumn('snapshot.quote_currency', '<>', 'valuation.functional_currency')->orWhereColumn('snapshot.rate_decimal', '<>', 'valuation.rate_decimal')->orWhereColumn('snapshot.rate_date', '<>', 'valuation.recognition_date')->orWhere('snapshot.rate_type', '<>', 'spot')->orWhere('snapshot.source_type', '<>', 'inventory_receipt_valuation')->orWhereColumn('snapshot.source_id', '<>', 'valuation.goods_receipt_line_id'))
            ->count();
    }

    private function inventoryReceiptFxMovementOrJournalMismatch(): int
    {
        if (! $this->inventoryReceiptFxSchemaReady()) {
            return 0;
        }

        return DB::table('inventory_receipt_fx_valuations as valuation')
            ->leftJoin('inventory_movements as movement', 'movement.id', '=', 'valuation.inventory_movement_id')
            ->leftJoin('payroll_accounting_journals as journal', 'journal.id', '=', 'valuation.accounting_journal_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('movement.id')->orWhereNull('journal.id')->orWhereColumn('movement.source_id', '<>', 'valuation.goods_receipt_line_id')->orWhere('movement.source_type', '<>', 'procurement_goods_receipt_line')->orWhereColumn('movement.movement_value_minor', '<>', 'valuation.functional_amount_minor')->orWhereColumn('journal.company_id', '<>', 'valuation.company_id')->orWhereColumn('journal.branch_id', '<>', 'valuation.branch_id')->orWhereColumn('journal.currency', '<>', 'valuation.functional_currency')->orWhereColumn('journal.total_debit_minor', '<>', 'journal.total_credit_minor')->orWhereColumn('journal.total_debit_minor', '<>', 'valuation.functional_amount_minor')->orWhere('journal.source_type', '<>', 'goods_receipt')->orWhereColumn('journal.source_id', '<>', 'valuation.goods_receipt_id'))
            ->count();
    }

    private function inventoryReceiptFxBillMatchMismatch(): int
    {
        if (! $this->inventoryReceiptFxSchemaReady() || ! Schema::hasTable('vendor_bill_receipt_fx_matches')) {
            return 0;
        }

        return DB::table('vendor_bill_receipt_fx_matches as matching')
            ->leftJoin('vendor_bill_receipt_allocations as allocation', 'allocation.id', '=', 'matching.vendor_bill_receipt_allocation_id')
            ->leftJoin('supplier_invoice_fx_recognitions as recognition', 'recognition.id', '=', 'matching.supplier_invoice_fx_recognition_id')
            ->leftJoin('inventory_receipt_fx_valuations as valuation', 'valuation.id', '=', 'matching.inventory_receipt_fx_valuation_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('allocation.id')->orWhereNull('recognition.id')->orWhereNull('valuation.id')->orWhereColumn('recognition.vendor_bill_id', '<>', 'matching.vendor_bill_id')->orWhereColumn('recognition.company_id', '<>', 'matching.company_id')->orWhereColumn('valuation.company_id', '<>', 'matching.company_id')->orWhereColumn('allocation.allocated_minor', '<>', 'matching.foreign_amount_minor')->orWhereRaw('matching.purchase_price_variance_minor <> CAST(matching.ap_functional_minor AS SIGNED) - CAST(matching.released_grni_functional_minor AS SIGNED)')->orWhereColumn('recognition.accounting_journal_id', '<>', 'matching.accounting_journal_id'))
            ->count();
    }

    private function inventoryReceiptFxSchemaReady(): bool
    {
        return Schema::hasTable('inventory_receipt_fx_valuations') && Schema::hasTable('procurement_goods_receipt_lines') && Schema::hasTable('procurement_goods_receipts') && Schema::hasTable('purchase_orders') && Schema::hasTable('finance_fx_rate_snapshots');
    }

    private function apFxSettlementSchemaReady(): bool
    {
        return $this->apFxSchemaReady() && Schema::hasTable('supplier_payment_fx_settlements') && Schema::hasTable('ap_payments') && Schema::hasTable('ap_payment_allocations') && Schema::hasTable('treasury_financial_accounts') && Schema::hasTable('treasury_transactions');
    }

    private function apFxSchemaReady(): bool
    {
        return Schema::hasTable('supplier_invoice_fx_recognitions') && Schema::hasTable('vendor_bills') && Schema::hasTable('finance_fx_rate_snapshots');
    }

    private function arFxSchemaReady(): bool
    {
        return Schema::hasTable('customer_invoice_fx_recognitions') && Schema::hasTable('customer_collection_allocation_fx_settlements');
    }

    private function isMissingServiceAcceptanceTable(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'service_acceptances')
            || str_contains($message, 'service_acceptance_lines')
            || str_contains($message, 'service_acceptance_line_id')
            || str_contains($message, 'reversal_journal_id');
    }

    private static function evidenceInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private static function evidenceString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** @return numeric-string|null */
    private static function evidenceNumeric(mixed $value): ?string
    {
        return is_string($value) && is_numeric($value) ? $value : null;
    }
}

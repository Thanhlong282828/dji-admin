<?php

namespace Modules\Procurement\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;

final readonly class ProcurementProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string, mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $scope = ['company_id' => $companyId, 'branch_id' => $branchId];
        $proc = $this->url($actor, 'procurement.view', 'admin.procurement.reports', $scope);
        $orders = $this->url($actor, 'purchase-order.view', 'admin.procurement.orders', $scope);
        $receipts = $this->url($actor, 'goods-receipt.view', 'admin.procurement.receipts', $scope);
        $returns = $this->url($actor, 'procurement-goods-return.view', 'admin.procurement.goods-returns', $scope);
        $credits = $this->url($actor, 'supplier-credit.view', 'admin.procurement.supplier-credits', $scope);
        $services = $this->url($actor, 'service-acceptance.view', 'admin.procurement.service-acceptances', $scope);
        $bills = $this->url($actor, 'vendor-bill.view', 'admin.procurement.payables', $scope);
        $treasury = $this->url($actor, 'treasury.view', 'admin.finance.treasury', $scope);
        $journal = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $scope);
        $cash = $this->url($actor, 'payroll.accounting.view', 'admin.finance.financial-statements', $scope);

        return ['title' => 'Procure-to-pay workflow', 'description' => 'Lineage canonical từ nhu cầu mua đến Cash Flow, gồm Return, Supplier Credit và Vendor Refund; không tạo dữ liệu minh hoạ.', 'owner' => 'Procurement + Finance', 'accounting_impact' => 'Receipt/acceptance ghi nhận GRNI/accrual; Bill tạo AP; Return/Credit đảo carrying basis; payment/refund đi qua Treasury.', 'steps' => [
            $this->step('Purchase Request', 'Procurement Module', 'GetProcurementPageQuery → ProcurementReadService', 'Nhu cầu mua đã được phê duyệt theo branch/cost center.', 'Chưa hạch toán; là bằng chứng nhu cầu và kiểm soát budget.', 'Purchase Order', $proc, $this->count('purchase_requests', $companyId, $branchId), 'Create from upstream workflow'),
            $this->step('Purchase Order', 'Procurement Module', 'GetProcurementPageQuery → ProcurementReadService', 'Cam kết mua đã phê duyệt với Vendor.', 'Tạo open commitment; chưa phải AP.', 'Goods Receipt', $orders, $this->count('purchase_orders', $companyId, $branchId), 'Create from approved Purchase Request'),
            $this->step('Goods Receipt', 'Procurement Module', 'GetProcurementPageQuery → ProcurementReadService', 'Xác nhận hàng hóa đã nhận thực tế.', 'Dr Inventory/Asset · Cr GRNI theo classification.', 'Service Acceptance', $receipts, $this->count('goods_receipts', $companyId, $branchId), 'Create from Purchase Order'),
            $this->step('Goods Return FX Reversal', 'Procurement / Inventory', 'GetProcurementPageQuery → InventoryReturnFxReversalService', 'Trả hàng giữ lineage về receipt valuation và inventory movement gốc.', 'Đảo functional inventory/GRNI basis; không sửa evidence nhận hàng cũ.', 'Supplier Credit', $returns, $this->count('procurement_goods_returns', $companyId, $branchId), 'Create from Goods Receipt'),
            $this->step('Service Acceptance', 'Procurement Module', 'GetProcurementPageQuery → ProcurementReadService', 'Nghiệm thu dịch vụ thật theo Purchase Order.', 'Dr Expense/Asset · Cr Service Accrual.', 'Vendor Bill', $services, $this->count('service_acceptances', $companyId, $branchId), 'Create from service Purchase Order'),
            $this->step('Vendor Bill', 'Procurement Module', 'GetProcurementPageQuery → SupplierInvoiceFxRecognitionService', 'Hóa đơn NCC giữ transaction currency; foreign bill dùng direct spot rate tại ngày ghi nhận, same-currency dùng identity rate.', 'Immutable FX evidence → policy-backed Dr GRNI/Accrual hoặc Expense · Cr Accounts Payable bằng functional currency. Tax FX chưa hỗ trợ sẽ rollback.', 'AP Liability', $bills, $this->count('vendor_bills', $companyId, $branchId), 'Create from receipt or acceptance'),
            $this->step('AP Liability', 'Procurement Module', 'GetProcurementPageQuery → ProcurementReadService', 'Nghĩa vụ phải trả còn mở theo Vendor Bill.', 'Accounts Payable giữ nguyên tới khi allocation được post.', 'Vendor Payment', $bills, $this->count('vendor_bills', $companyId, $branchId, ['status' => 'approved']), 'Create from approved Vendor Bill'),
            $this->step('Supplier Credit', 'Procurement Module', 'GetProcurementPageQuery → SupplierCreditFxEvidenceService', 'Credit gắn Goods Return với Supplier Invoice và phân bổ phần còn nợ/phần đã trả.', 'Giảm AP carrying basis; phần đã trả tạo Supplier Receivable; PPV dùng policy version canonical.', 'Vendor Refund', $credits, $this->count('supplier_credit_notes', $companyId, $branchId), 'Create from approved Goods Return'),
            $this->step('Vendor Refund', 'Procurement + Treasury', 'GetProcurementPageQuery → VendorRefundFxSettlementService', 'Thu hồi tiền từ Supplier Receivable bằng Treasury account cùng transaction currency.', 'Direct spot snapshot; release receivable carrying basis; realized FX; Treasury inflow và balanced Journal.', 'Accounting Journal', $credits, $this->count('supplier_refund_receipts', $companyId, $branchId), 'Create from Supplier Credit receivable'),
            $this->step('Vendor Payment', 'Procurement Module', 'GetProcurementPageQuery → SupplierPaymentFxSettlementService', 'Payment allocation gắn trực tiếp vào Vendor Bill; foreign payment dùng direct spot rate và Treasury account cùng transaction currency.', 'Release functional AP carrying basis; Dr AP, Cr Cash/Bank và ghi realized FX Gain/Loss bằng policy-backed journal.', 'Treasury Outflow', $bills, $this->count('ap_payments', $companyId, $branchId), 'Create from AP Liability'),
            $this->step('Treasury Outflow', 'Finance / Treasury', 'GetTreasuryCashPositionQuery → TreasuryCashPositionReadService', 'Dòng tiền ra canonical do payment đã post.', 'Giảm available cash.', 'Accounting Journal', $treasury, $this->count('treasury_transactions', $companyId, $branchId), 'Post Vendor Payment upstream'),
            $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery → SourceAccountingPostingService', 'Journal cân bằng giữ source evidence và policy version.', 'Ghi nhận inventory/expense/AP/cash theo từng nguồn.', 'Cash Flow', $journal, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Post upstream document'),
            $this->step('Cash Flow', 'Finance Module', 'FinancialStatementReadService', 'Báo cáo dòng tiền lấy từ Treasury và posted GL.', 'Vendor payment là operating/investing outflow theo classification.', 'Financial reporting', $cash, $this->count('treasury_transactions', $companyId, $branchId), 'Post Vendor Payment upstream'),
        ]];
    }
}

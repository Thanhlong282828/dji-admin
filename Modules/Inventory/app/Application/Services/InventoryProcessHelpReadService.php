<?php

namespace Modules\Inventory\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;

final readonly class InventoryProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string, mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $s = ['company_id' => $companyId, 'branch_id' => $branchId];
        $moves = $this->url($actor, 'inventory.view', 'admin.seller.inventory.movements', $s);
        $stock = $this->url($actor, 'inventory.view', 'admin.seller.inventory', $s);
        $sales = $this->url($actor, 'sales.view', 'admin.sales.index', $s);
        $journal = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $s);
        $governance = $this->url($actor, 'stock-count.view', 'admin.seller.inventory.governance', $s);

        return ['title' => 'Inventory source-to-COGS workflow', 'description' => 'Lineage tồn kho vật lý, reservation và COGS theo nguồn canonical.', 'owner' => 'Inventory + Commerce + Finance', 'accounting_impact' => 'Receipt tăng inventory; fulfillment giảm inventory và ghi COGS; journal giữ source evidence.', 'steps' => [
            $this->step('Purchase Receipt', 'Inventory Module', 'GetInventoryMovementListQuery → InventoryReadService', 'Chứng từ nhận hàng đã receive từ luồng mua.', 'Dr Inventory · Cr GRNI khi nguồn là procurement receipt.', 'Inventory Movement', $moves, $this->count('inventory_receipts', $companyId, $branchId), 'Create from Purchase Order'),
            $this->step('Inventory Movement', 'Inventory Module', 'GetInventoryMovementListQuery → InventoryReadService', 'Ledger biến động before/after theo nguồn thật.', 'Là bằng chứng lượng cho valuation/posting.', 'Stock Balance', $moves, $this->count('inventory_movements', $companyId, $branchId), 'Create from upstream workflow'),
            $this->step('Stock Balance', 'Inventory Module', 'InventoryBalanceQuery → InventoryReadService', 'On hand canonical theo SKU và kho.', 'Giá trị tồn kho lấy theo valuation, không theo giá bán.', 'Reservation', $stock, $this->count('inventory_balances', $companyId, $branchId), 'Receive inventory upstream'),
            $this->step('Stock Count', 'Inventory Module', 'GetStockCountWorkspaceQuery → InventoryAdjustmentReadService', 'Snapshot system quantity và số đếm vật lý theo warehouse.', 'Chưa hạch toán; tạo variance evidence bất biến.', 'Inventory Adjustment', $governance, $this->count('inventory_stock_counts', $companyId, $branchId), 'Create Stock Count'),
            $this->step('Inventory Adjustment', 'Inventory Module', 'GetInventoryAdjustmentWorkspaceQuery → InventoryAdjustmentReadService', 'Maker-checker document sinh từ approved Stock Count variance.', 'Post movement và policy-backed Inventory loss/gain journal.', 'Inventory Movement', $governance, $this->count('inventory_adjustments', $companyId, $branchId), 'Create from approved Stock Count'),
            $this->step('Reservation', 'Inventory Module', 'InventoryReservationQuery → InventoryReadService', 'Giữ hàng cho Sales Order hợp lệ.', 'Không hạch toán; giảm available, không giảm on hand.', 'Sales Fulfillment', $stock, $this->count('inventory_reservations', $companyId, $branchId), 'Create from Sales Order'),
            $this->step('Sales Fulfillment', 'Commerce Module', 'GetSalesReceivableQuery → SalesReceivableService', 'Xuất hàng thực tế cho đơn bán.', 'Cr Inventory theo weighted-average valuation.', 'COGS', $sales, $this->count('sales_orders', $companyId, $branchId, ['status' => 'fulfilled']), 'Create from reserved Sales Order'),
            $this->step('COGS', 'Finance Module', 'COGS posting query → SourceAccountingPostingService', 'Chi phí giá vốn phát sinh từ fulfillment.', 'Dr COGS · Cr Inventory.', 'Accounting Journal', $journal, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Fulfill Sales Order upstream'),
            $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery → SourceAccountingPostingService', 'Journal cân bằng gắn source fulfillment/receipt.', 'Cập nhật GL và financial statements.', 'Financial reporting', $journal, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Post upstream workflow'),
        ]];
    }
}

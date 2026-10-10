<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final readonly class CustomerReceivableProcessHelpReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{title:string,description:string,owner:string,accounting_impact:string,steps:list<array<string,mixed>>} */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $scope = array_filter(['company_id' => $companyId, 'branch_id' => $branchId], static fn (mixed $value): bool => $value !== null);
        $sales = $this->url($actor, 'sales.view', 'admin.sales.index', $scope);
        $treasury = $this->url($actor, 'treasury.view', 'admin.finance.treasury', $scope);
        $journal = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $scope);
        $cashFlow = $this->url($actor, 'payroll.accounting.view', 'admin.finance.financial-statements', $scope);
        $orders = $this->count('sales_orders', $companyId, $branchId);
        $invoices = $this->count('customer_invoices', $companyId, $branchId);
        $collections = $this->count('customer_collections', $companyId, $branchId);
        $creditNotes = $this->count('customer_credit_notes', $companyId, $branchId, ['status' => 'posted']);
        $liabilities = $this->sum('customer_credit_notes', 'customer_credit_liability_minor', $companyId, $branchId, ['status' => 'posted']);
        $refunds = $this->count('customer_refunds', $companyId, $branchId);
        $refundOutflows = $this->sourceCount('treasury_transactions', 'customer_refund', $companyId, $branchId);
        $refundJournals = $this->sourceCount('payroll_accounting_journals', 'customer_refund', $companyId, $branchId);

        return [
            'title' => 'Customer revenue-to-refund workflow',
            'description' => 'Giải thích lineage từ Sales Order đến Cash Flow; dữ liệu được đọc từ nguồn canonical, không tạo bản ghi để minh hoạ.',
            'owner' => 'Commerce + Finance',
            'accounting_impact' => 'Invoice tạo AR; Collection giảm AR và tăng cash; Credit Note giảm AR/tạo Customer Credit Liability; Refund giảm liability và cash.',
            'steps' => [
                $this->step('Sales Order', 'Commerce Module', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Đơn bán đã convert từ quotation và xác nhận.', 'Chưa tạo journal ở bước order.', 'Fulfillment', $sales, null, $orders, 'Create from upstream workflow'),
                $this->step('Fulfillment', 'Commerce Module', 'GetSalesReceivableQuery → SalesReceivableService', 'Xuất hàng thực tế từ Sales Order; tạo inventory/COGS evidence.', 'Dr COGS / Cr Inventory theo posting nguồn.', 'Customer Invoice', $sales, null, $orders, 'Open Sales Orders'),
                $this->step('Customer Invoice', 'Commerce Module', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Invoice đã phát hành từ Sales Order fulfilled.', 'Tạo nghĩa vụ Accounts Receivable và revenue evidence.', 'Collection', $sales, null, $invoices, 'Create from Sales Order'),
                $this->step('Collection', 'Commerce Module', 'GetSalesReceivableQuery → AccountsReceivableReadService', 'Phân bổ tiền thu thật vào Customer Invoice.', 'Dr Cash/Payment Clearing · Cr Accounts Receivable.', 'Customer Credit Note', $sales, null, $collections, 'Create from Customer Invoice'),
                $this->step('Customer Credit Note', 'Commerce Module', 'GetSalesReceivableQuery → CustomerCreditNoteService', 'Điều chỉnh invoice đã phát hành; không hoàn tiền và không thay đổi tồn kho.', 'Giảm AR; phần vượt AR tạo Customer Credit Liability.', 'Customer Credit Liability', $sales, null, $creditNotes, 'Create from Customer Invoice'),
                $this->step('Customer Credit Liability', 'Commerce Module', 'GetCustomerRefundWorkspaceQuery → CustomerRefundReadService', 'Khoản phải trả lại khách được dẫn xuất từ posted Credit Note.', 'Liability giữ nguyên cho tới khi Customer Refund được post.', 'Customer Refund', $sales, null, $liabilities > 0 ? 1 : 0, 'Create from posted Credit Note'),
                $this->step('Customer Refund', 'Commerce Module', 'GetCustomerRefundWorkspaceQuery → CustomerRefundReadService', 'Refund phân bổ bất biến vào Customer Credit Liability nguồn.', 'Dr Customer Credit Liability · Cr Cash/Bank khi post.', 'Treasury Outflow', $sales, null, $refunds, 'Create Refund from available liability'),
                $this->step('Treasury Outflow', 'Finance / Treasury boundary', 'GetTreasuryCashPositionQuery → TreasuryCashPositionReadService', 'Cash/bank movement canonical được tạo khi refund post.', 'Giảm Available Cash; payment_clearing không hợp lệ cho refund.', 'Accounting Journal', $treasury, null, $refundOutflows, 'Post Customer Refund upstream'),
                $this->step('Accounting Journal', 'Finance Module', 'Accounting journal query → SourceAccountingPostingService', 'Journal cân bằng và giữ policy/version/source evidence.', 'Dr Customer Credit Liability · Cr Cash/Bank.', 'Cash Flow', $journal, null, $refundJournals, 'Post Customer Refund upstream'),
                $this->step('Cash Flow', 'Finance Module', 'FinancialStatementReadService', 'Báo cáo dòng tiền đọc Treasury transaction đã ghi nhận.', 'Customer Refund được phân loại là operating outflow.', 'Financial reporting', $cashFlow, null, $refundOutflows, 'Post Customer Refund upstream'),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function step(string $title, string $owner, string $source, string $meaning, string $accounting, string $next, ?string $baseUrl, ?string $fragment, int $evidenceCount, string $emptyAction): array
    {
        return [
            'title' => $title, 'owner' => $owner, 'source' => $source, 'meaning' => $meaning, 'accounting' => $accounting, 'next' => $next,
            'url' => $baseUrl === null ? null : $baseUrl.($fragment ?? ''), 'link_label' => 'View '.$title,
            'has_operating_data' => $evidenceCount > 0, 'evidence_count' => $evidenceCount, 'empty_message' => 'No operating data available', 'empty_action' => $emptyAction,
        ];
    }

    /** @param array<string,int> $parameters */
    private function url(User $actor, string $permission, string $route, array $parameters): ?string
    {
        return Route::has($route) && $actor->can($permission) ? route($route, $parameters) : null;
    }

    /** @param array<string,string> $filters */
    private function count(string $table, int $companyId, ?int $branchId, array $filters = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $query = $this->scoped($table, $companyId, $branchId);
        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        return $query->count();
    }

    /** @param array<string,string> $filters */
    private function sum(string $table, string $column, int $companyId, ?int $branchId, array $filters = []): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }
        $query = $this->scoped($table, $companyId, $branchId);
        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        return (int) $query->sum($column);
    }

    private function sourceCount(string $table, string $sourceType, int $companyId, ?int $branchId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return $this->scoped($table, $companyId, $branchId)->where('source_type', $sourceType)->count();
    }

    private function scoped(string $table, int $companyId, ?int $branchId): Builder
    {
        return $this->db->table($table)->where('company_id', $companyId)
            ->when($branchId !== null && Schema::hasColumn($table, 'branch_id'), fn (Builder $query): Builder => $query->where('branch_id', $branchId));
    }
}

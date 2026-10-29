<?php

namespace Modules\Payroll\Application\Services;

use App\Models\User;
use App\Support\Erp\ProcessHelpReadService;

final readonly class PayrollProcessHelpReadService extends ProcessHelpReadService
{
    /** @return array<string, mixed> */
    public function read(int $companyId, ?int $branchId, User $actor): array
    {
        $s = ['company_id' => $companyId, 'branch_id' => $branchId];
        $inputs = $this->url($actor, 'payroll.input.view', 'admin.payroll.inputs.index', $s);
        $runs = $this->url($actor, 'payroll.run.view', 'admin.payroll.runs.index', $s);
        $payslips = $this->url($actor, 'payroll.payslip.view', 'admin.payroll.payslips.index', $s);
        $payments = $this->url($actor, 'payroll.payment.view', 'admin.payroll.payment-batches.index', $s);
        $treasury = $this->url($actor, 'treasury.view', 'admin.finance.treasury', $s);
        $journal = $this->url($actor, 'payroll.accounting.view', 'admin.payroll.accounting.journals.index', $s);

        return ['title' => 'Workforce-to-payroll workflow', 'description' => 'Lineage từ chấm công đến thanh toán và GL; mọi số liệu đọc từ payroll snapshot/version canonical.', 'owner' => 'HRM + Payroll + Finance', 'accounting_impact' => 'Finalized payroll tạo expense/liability; payment batch và Treasury giải phóng liability bằng cash outflow.', 'steps' => [
            $this->step('Attendance', 'HRM Attendance Module', 'AttendanceQuery → AttendanceReadService', 'Ngày công đã duyệt trong kỳ.', 'Chưa hạch toán; là payroll input evidence.', 'Leave / Overtime', $inputs, $this->count('attendance_records', $companyId, $branchId), 'Record attendance upstream'),
            $this->step('Leave / Overtime', 'HRM Workforce Module', 'WorkforceTimeQuery → PayrollInputReadService', 'Nghỉ phép và tăng ca đã duyệt, đúng kỳ hiệu lực.', 'Tạo earning/deduction input; chưa post GL.', 'Payroll Input', $inputs, $this->count('overtime_requests', $companyId, $branchId) + $this->count('leave_requests', $companyId, $branchId), 'Approve workforce request upstream'),
            $this->step('Payroll Input', 'Payroll Module', 'GetPayrollInputListQuery → PayrollInputReadService', 'Snapshot đầu vào bất biến theo employee/component.', 'Chưa hạch toán; khóa nguồn tính lương.', 'Payroll Run', $inputs, $this->count('payroll_inputs', $companyId, $branchId), 'Create from attendance and policies'),
            $this->step('Payroll Run', 'Payroll Module', 'GetPayrollRunListQuery → PayrollRunReadService', 'Calculation/version qua submit, approve, finalize.', 'Finalized run tạo expense và payroll liabilities.', 'Payslip', $runs, $this->count('payroll_runs', $companyId, $branchId), 'Create from Payroll Input'),
            $this->step('Payslip', 'Payroll Module', 'GetPayslipListQuery → PayslipReadService', 'Tài liệu nhân viên lấy exact finalized run item.', 'Không tạo bút toán mới.', 'Payment Batch', $payslips, $this->count('payroll_payslips', $companyId, $branchId), 'Generate from finalized run'),
            $this->step('Payment Batch', 'Payroll Module', 'GetPaymentBatchListQuery → PaymentBatchReadService', 'Chỉ thị thanh toán từ finalized payroll run.', 'Chuẩn bị giải phóng payroll payable.', 'Treasury', $payments, $this->count('payroll_payment_batches', $companyId, $branchId), 'Create from finalized run'),
            $this->step('Treasury', 'Finance / Treasury', 'GetTreasuryCashPositionQuery → TreasuryCashPositionReadService', 'Cash outflow canonical theo released payment batch.', 'Dr Payroll Payable · Cr Cash/Bank.', 'Accounting Journal', $treasury, $this->count('treasury_transactions', $companyId, $branchId), 'Release Payment Batch upstream'),
            $this->step('Accounting Journal', 'Finance Module', 'AccountingJournalQuery → PayrollAccountingReadService', 'Journal cân bằng khóa calculation/policy version.', 'Ghi payroll expense, statutory liabilities và settlement.', 'Financial reporting', $journal, $this->count('payroll_accounting_journals', $companyId, $branchId), 'Finalize Payroll Run upstream'),
        ]];
    }
}

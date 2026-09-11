<?php

namespace Modules\Document\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'document_type' => ['required', 'string', 'in:EMPLOYEE_CONTRACT,IDENTITY_DOCUMENT,CERTIFICATE,RESIGNATION,PURCHASE_ORDER,GOODS_RECEIPT,VENDOR_INVOICE,EXPENSE_RECEIPT,ASSET_EVIDENCE,CUSTOMER_INVOICE,ACCOUNTING_SUPPORT,PROJECT_DOCUMENT,MILESTONE_EVIDENCE,TASK_EVIDENCE,MAINTENANCE_EVIDENCE,REPAIR_PHOTO,SERVICE_REPORT'], 'document_number' => ['nullable', 'string', 'max:100'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'classification' => ['required', 'string', 'in:PUBLIC_INTERNAL,INTERNAL,CONFIDENTIAL,RESTRICTED'], 'owner_module' => ['required', 'string', 'in:Employee,Procurement,Finance,Commerce,Project,Maintenance'], 'subject_type' => ['required', 'string', 'in:EMPLOYEE,EMPLOYEE_CONTRACT,PURCHASE_ORDER,GOODS_RECEIPT,VENDOR_INVOICE,EXPENSE,ASSET,SALES_ORDER,CUSTOMER_INVOICE,ACCOUNTING_ADJUSTMENT,PROJECT,PROJECT_MILESTONE,PROJECT_TASK,MAINTENANCE_REQUEST,MAINTENANCE_WORK_ORDER,MAINTENANCE_PLAN'], 'subject_id' => ['required', 'integer', 'min:1'], 'retention_until' => ['nullable', 'date'], 'document' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp']];
    }
}

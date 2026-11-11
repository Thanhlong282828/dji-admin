@extends('layouts.admin')
@section('title','HR Data Governance')
@section('content')
<x-erp.dashboard eyebrow="HR DATA GOVERNANCE" title="Chất lượng và tuân thủ dữ liệu nhân sự" description="Read model chỉ hiển thị metadata quản trị; không đọc lương, thuế, ngân hàng hoặc storage path.">
    <x-slot:actions>@can('hr.quality.run')<form method="POST" action="{{ route('admin.hr.governance.runs.store') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="idempotency_key" value="quality-{{ $companyId }}-{{ now()->format('YmdHi') }}"><button class="button" type="submit">Chạy kiểm tra dữ liệu</button></form>@endcan</x-slot:actions>
    @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
    <x-erp.dashboard-section title="Governance signals" description="Snapshot hiện tại từ HR Data Quality read model.">
        <section class="erp-kpi-grid" aria-label="Chỉ số quản trị dữ liệu nhân sự">
            <x-erp.kpi-card label="Data Quality Score" :value="$viewModel->qualityScore.'%'" meta="QUALITY RULES" :href="route('admin.hr.governance.issues',['company_id'=>$companyId])" />
            <x-erp.kpi-card label="Open Issues" :value="number_format($viewModel->openIssues)" meta="UNRESOLVED ISSUES" :href="route('admin.hr.governance.issues',['company_id'=>$companyId])" />
            <x-erp.kpi-card label="Blocking" :value="number_format($viewModel->blockingIssues)" meta="BLOCKING ISSUES" :href="route('admin.hr.governance.issues',['company_id'=>$companyId])" />
            <x-erp.kpi-card label="Consent Status" :value="$viewModel->consentCompliance.'%'" meta="CONSENT GOVERNANCE" />
            <x-erp.kpi-card label="Document Compliance" :value="$viewModel->documentCompliance.'%'" meta="DOCUMENT GOVERNANCE" />
        </section>
    </x-erp.dashboard-section>
    <x-erp.dashboard-section title="Governance workspace" description="Đi tới evidence và timeline theo đúng quyền truy cập.">
        <x-erp.widget>
            <div class="toolbar"><a class="button secondary" href="{{ route('admin.hr.governance.issues',['company_id'=>$companyId]) }}">Quality issues</a>@can('hr.audit.view')<a class="button secondary" href="{{ route('admin.hr.governance.audit',['company_id'=>$companyId]) }}">Audit timeline</a>@endcan</div>
        </x-erp.widget>
    </x-erp.dashboard-section>
</x-erp.dashboard>
@endsection

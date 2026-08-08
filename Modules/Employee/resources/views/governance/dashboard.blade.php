@extends('layouts.admin')
@section('title','HR Data Governance')
@section('content')
<style>.gov-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.gov-card,.gov-panel{border:1px solid rgba(212,175,55,.28);background:linear-gradient(145deg,#151515,#090909);border-radius:16px;padding:18px}.gov-card strong{display:block;color:#d4af37;font-size:28px}.gov-actions{display:flex;gap:10px;flex-wrap:wrap}.gov-btn{border:1px solid #d4af37;color:#f5d76e;padding:10px 14px;border-radius:10px;background:#17130a}.gov-table{overflow-x:auto}.gov-table table{width:100%;min-width:720px;border-collapse:collapse}.gov-table th,.gov-table td{padding:11px;border-bottom:1px solid #292929;text-align:left}@media(max-width:800px){.gov-grid{grid-template-columns:1fr 1fr}.gov-card:last-child{grid-column:1/-1}}@media(max-width:430px){.gov-grid{grid-template-columns:1fr}.gov-card:last-child{grid-column:auto}}</style>
<div class="gov-actions"><h1 style="margin-right:auto">HR Data Governance</h1>@can('hr.quality.run')<form method="POST" action="{{ route('admin.hr.governance.runs.store') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="idempotency_key" value="quality-{{ $companyId }}-{{ now()->format('YmdHi') }}"><button class="gov-btn">Chạy kiểm tra dữ liệu</button></form>@endcan</div>
@if(session('success'))<div class="gov-panel">{{ session('success') }}</div>@endif
<div class="gov-grid">
 <div class="gov-card"><span>Data Quality Score</span><strong>{{ $viewModel->qualityScore }}%</strong></div>
 <div class="gov-card"><span>Open Issues</span><strong>{{ $viewModel->openIssues }}</strong></div>
 <div class="gov-card"><span>Blocking</span><strong>{{ $viewModel->blockingIssues }}</strong></div>
 <div class="gov-card"><span>Consent Status</span><strong>{{ $viewModel->consentCompliance }}%</strong></div>
 <div class="gov-card"><span>Document Compliance</span><strong>{{ $viewModel->documentCompliance }}%</strong></div>
</div>
<div class="gov-panel" style="margin-top:18px"><div class="gov-actions"><h2 style="margin-right:auto">Governance workspace</h2><a class="gov-btn" href="{{ route('admin.hr.governance.issues',['company_id'=>$companyId]) }}">Quality issues</a>@can('hr.audit.view')<a class="gov-btn" href="{{ route('admin.hr.governance.audit',['company_id'=>$companyId]) }}">Audit timeline</a>@endcan</div><p>Read model chỉ hiển thị metadata quản trị; không đọc lương, thuế, ngân hàng hoặc storage path.</p></div>
@endsection

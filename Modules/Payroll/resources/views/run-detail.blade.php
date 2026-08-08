@extends('layouts.admin')
@section('title', 'Payroll Run')
@section('content')
@if($viewModel->summary['status']==='finalized')<section class="panel"><h2>Payslips</h2><p>Generate missing official Payslips asynchronously from immutable Payroll Items.</p>@can('payroll.payslip.generate')<form method="POST" action="{{ route('admin.payroll.payslips.generate-run',['run'=>$viewModel->summary['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><button class="button">Generate Missing Payslips</button></form>@endcan<a href="{{ route('admin.payroll.payslips.index',['company_id'=>$companyId,'run'=>$viewModel->summary['id']]) }}">View Payslips</a></section>@endif
<div class="space-y-7" data-workforce-page data-company-id="{{ $companyId }}" data-entity-id="{{ $viewModel->summary['id'] }}">
    <a class="text-amber-500" href="{{ route('admin.payroll.delivery.index', ['company_id' => $companyId]) }}">← Payroll Delivery</a>
    @include('payroll::partials.run-summary')
    @include('payroll::partials.run-readiness')
    @include('payroll::partials.run-items')
    @include('payroll::partials.run-validation')
    @include('payroll::partials.run-history')
    @include('payroll::partials.run-actions')
</div>
@endsection

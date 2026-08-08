@extends('layouts.admin')
@section('title', 'Tạo kỳ lương')
@section('header_action')
<a class="button" href="{{ route('admin.payroll.periods.index',['company_id'=>$companyId]) }}">← Danh sách</a>
@endsection
@section('content')<section class="panel max-w-4xl"><span class="eyebrow">PAYROLL PERIOD</span><h1>Tạo kỳ lương</h1><form method="POST" action="{{ route('admin.payroll.periods.store') }}">@include('payroll::periods.partials-form')</form></section>@endsection

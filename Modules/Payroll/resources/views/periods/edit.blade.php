@extends('layouts.admin')
@section('title', 'Sửa kỳ lương')
@section('header_action')
<a class="button" href="{{ route('admin.payroll.periods.show',['period'=>$viewModel->detail['id'],'company_id'=>$companyId]) }}">← Chi tiết</a>
@endsection
@section('content')<section class="panel max-w-4xl"><h1>Sửa {{ $viewModel->detail['code'] }}</h1><form method="POST" action="{{ route('admin.payroll.periods.update',['period'=>$viewModel->detail['id']]) }}">@include('payroll::periods.partials-form',['method'=>'PUT','period'=>$viewModel->detail])</form></section>@endsection

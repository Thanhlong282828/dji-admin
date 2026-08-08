@extends('layouts.admin')
@section('title','Tạo Candidate Offer')
@section('content')
<section class="panel"><h1>Tạo Candidate Offer</h1>@if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admin.candidate-offers.store') }}" class="form-grid">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}">
<label>Application ID<input type="number" name="application_id" value="{{ old('application_id',request('application_id')) }}" required></label>
<label>Loại làm việc<input name="employment_type" value="{{ old('employment_type','full_time') }}" required></label>
<label>Lương cơ bản<input name="base_salary" inputmode="decimal" value="{{ old('base_salary') }}" required></label>
<label>Tiền tệ<select name="currency"><option>VND</option><option>USD</option><option>EUR</option><option>SGD</option><option>JPY</option></select></label>
<label>Ngày bắt đầu<input type="date" name="start_date" value="{{ old('start_date') }}" required></label>
<label>Kết thúc thử việc<input type="date" name="probation_end_date" value="{{ old('probation_end_date') }}"></label>
<label>Hết hạn lúc<input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"></label><button>Tạo draft</button></form></section>
@endsection

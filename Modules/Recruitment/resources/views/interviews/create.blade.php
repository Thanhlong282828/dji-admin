@extends('layouts.admin')
@section('title', 'Lên lịch phỏng vấn')
@section('content')
<section class="panel"><h2>Lên lịch phỏng vấn</h2>
@if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admin.interviews.store') }}" class="form-grid">@csrf
<input type="hidden" name="company_id" value="{{ $companyId }}">
<label>Application ID<input type="number" name="application_id" value="{{ old('application_id', request('application_id')) }}" required></label>
<label>Tiêu đề<input name="title" value="{{ old('title') }}" required></label>
<label>Hình thức<select name="interview_type"><option value="online">Online</option><option value="offline">Offline</option><option value="phone">Điện thoại</option></select></label>
<label>Bắt đầu<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required></label>
<label>Kết thúc<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required></label>
<label>Timezone<input name="timezone" value="{{ old('timezone', 'Asia/Ho_Chi_Minh') }}" required></label>
<label>Meeting URL<input type="url" name="meeting_url" value="{{ old('meeting_url') }}"></label>
<label>Địa điểm<input name="location" value="{{ old('location') }}"></label>
<label>Round<input type="number" name="round_number" min="1" value="{{ old('round_number', 1) }}" required></label>
<label>Interviewer user IDs<input name="interviewer_user_ids[]" type="number" required></label>
<label class="full">Hướng dẫn<textarea name="instructions">{{ old('instructions') }}</textarea></label>
<button>Lưu lịch phỏng vấn</button></form></section>
@endsection

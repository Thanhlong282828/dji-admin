@extends('layouts.admin')
@section('title', 'Hire Candidate')
@section('content')
<section class="panel">
    <span class="eyebrow">ATOMIC HIRE WORKFLOW</span>
    <h1>Hire {{ $preview->candidateName }}</h1>
    <p>{{ $preview->candidateEmail }} · {{ $preview->jobPositionTitle }} · {{ $preview->organizationUnitName }}</p>
    <p>Reservation: {{ $preview->reservationStatus }}@if($preview->baseSalary !== null) · Lương đã duyệt: {{ $preview->baseSalary }} {{ $preview->currency }}@endif</p>
    <p><strong>Thao tác này tạo Employee, Assignment, Contract và Onboarding Case trong một transaction.</strong></p>
    @if($preview->alreadyHired)
        <div class="flash success">Offer này đã tạo nhân viên #{{ $preview->employeeId }}.</div>
    @elseif($preview->offerStatus !== 'accepted' || $preview->reservationStatus !== 'reserved')
        <div class="flash error">Offer phải accepted và đang reserved trước khi hire.</div>
    @else
        @if($errors->any())<div class="flash error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('admin.candidate-offers.hire', ['candidate_offer' => $preview->offerId]) }}">
            @csrf
            <input type="hidden" name="company_id" value="{{ $companyId }}">
            <input type="hidden" name="expected_offer_version" value="{{ $preview->offerVersion }}">
            <input type="hidden" name="expected_application_version" value="{{ $preview->applicationVersion }}">
            <input type="hidden" name="organization_unit_id" value="{{ $preview->organizationUnitId }}">
            <input type="hidden" name="job_position_id" value="{{ $preview->jobPositionId }}">
            <input type="hidden" name="employment_type" value="{{ $preview->employmentType }}">
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <label>Mã nhân viên (để trống để hệ thống sinh)<input name="employee_number" value="{{ old('employee_number') }}" maxlength="50"></label>
            <label>Ngày bắt đầu<input type="date" name="start_date" value="{{ old('start_date', $preview->startDate->toDateString()) }}" required></label>
            <label>Địa điểm làm việc<input name="work_location" value="{{ old('work_location') }}" maxlength="255"></label>
            <label>User ID quản lý<input type="number" name="manager_user_id" value="{{ old('manager_user_id') }}" min="1"></label>
            <label>Loại hợp đồng<select name="contract_type" required><option value="indefinite">Không xác định thời hạn</option><option value="probation">Thử việc</option><option value="fixed_term">Có thời hạn</option><option value="contractor">Cộng tác viên</option></select></label>
            <label>Ngày kết thúc hợp đồng<input type="date" name="contract_end_date" value="{{ old('contract_end_date') }}"></label>
            <label>Template onboarding<input name="onboarding_template_code" value="{{ old('onboarding_template_code', 'default') }}" maxlength="100"></label>
            <label>Lý do / quyết định hire<textarea name="reason" required minlength="5" maxlength="2000">{{ old('reason', 'Offer đã được ứng viên chấp nhận và đủ điều kiện tiếp nhận.') }}</textarea></label>
            <button type="submit">Xác nhận hire và khởi tạo onboarding</button>
        </form>
    @endif
</section>
@endsection

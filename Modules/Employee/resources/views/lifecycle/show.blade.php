@extends('layouts.admin')
@section('title','Employee 360')
@section('content')
@php($employee=$viewModel->employee)
<section class="panel page-hero"><span class="eyebrow">EMPLOYEE 360 · IMMUTABLE HISTORY</span><h1>{{ $employee['full_name'] }}</h1><p>{{ $employee['employee_code'] }} · {{ $employee['work_email'] ?? '—' }}</p><span class="status-pill">{{ strtoupper($viewModel->currentState) }}</span></section>
@if(session('success'))<div class="notice success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice danger">{{ $errors->first() }}</div>@endif
<section class="panel"><h2>Lifecycle actions</h2><div class="action-wrap">
@php($base=['company_id'=>$companyId,'effective_at'=>now()->format('Y-m-d\TH:i'),'idempotency_key'=>(string)Illuminate\Support\Str::uuid()])
@php($actions=match($viewModel->currentState){'not_started'=>[['hr.lifecycle.create','admin.employee-lifecycle.create','Khởi tạo']],'hired'=>[['hr.lifecycle.onboarding','admin.employee-lifecycle.start-onboarding','Bắt đầu onboarding']],'onboarding'=>[['hr.lifecycle.onboarding','admin.employee-lifecycle.complete-onboarding','Hoàn tất onboarding']],'active','probation','confirmed'=>[['hr.lifecycle.terminate','admin.employee-lifecycle.terminate','Chấm dứt']],default=>[]})
@forelse($actions as [$ability,$route,$label]) @can($ability)<form method="POST" action="{{ route($route,['employee'=>$employee['id']]) }}">@csrf @foreach($base as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach @if($label==='Chấm dứt')<input name="reason" required placeholder="Lý do">@endif<button class="button">{{ $label }}</button></form>@endcan @empty<span class="muted">Không có thao tác lifecycle khả dụng ở trạng thái này.</span>@endforelse
</div><p class="muted">Điều chuyển/thăng chức tiếp tục dùng assignment form đã kiểm soát position, đơn vị, manager và chống vòng lặp.</p></section>
<section class="panel"><h2>Compensation hiện tại</h2>
@if(!$canViewCompensation)<p aria-label="Compensation bị giới hạn">•••••••• ₫ — yêu cầu quyền xem salary assignment.</p>
@elseif($currentCompensation)<div class="compensation-grid"><div><small>Chức vụ</small><strong>{{ $currentAssignment?->jobPosition?->title ?? '—' }}</strong></div><div><small>Level</small><strong>{{ $currentAssignment?->jobPosition?->career_level ?? '—' }}</strong></div><div><small>Lương cứng</small><strong>{{ number_format($currentCompensation->baseSalaryMinor,0,',','.') }} ₫</strong></div><div><small>Phụ cấp</small><strong>{{ number_format($currentCompensation->fixedAllowanceMinor,0,',','.') }} ₫</strong></div><div><small>KPI mục tiêu</small><strong>{{ number_format($currentCompensation->kpiTargetMinor,0,',','.') }} ₫</strong></div><div><small>Target Gross</small><strong>{{ number_format($currentCompensation->targetGrossMinor(),0,',','.') }} ₫</strong></div><div><small>Currency</small><strong>{{ $currentCompensation->currency }}</strong></div><div><small>Ngày hiệu lực</small><strong>{{ $currentCompensation->effectiveFrom ?? '—' }}</strong></div></div>
@else<p class="empty-state">Chưa thiết lập lương</p>@endif
<p class="muted">Target Gross không phải Net Pay; payroll thực tế còn attendance, overtime, insurance, tax, adjustment và deduction.</p></section>
@if($currentAssignment && $currentAssignment->jobPosition?->career_level)
<div class="dashboard-grid">
@foreach([['promotion','Thăng chức','admin.employee-lifecycle.promote','>'],['demotion','Giáng chức','admin.employee-lifecycle.demote','<']] as [$type,$label,$route,$operator])
@php($eligiblePositions=$positionOptions->filter(fn($position)=>$operator==='>' ? $position->career_level>$currentAssignment->jobPosition->career_level : $position->career_level<$currentAssignment->jobPosition->career_level))
@can('hr.lifecycle.promote')
<section class="panel">
    <h2>{{ $label }}</h2>
    <p>Hiện tại: {{ $currentAssignment->jobPosition->title }} · Level {{ $currentAssignment->jobPosition->career_level }}</p>
    @if($canViewCompensation && $currentCompensation)<div class="compensation-compare" data-current-compensation><strong>Hiện tại</strong><span>Base {{ number_format($currentCompensation->baseSalaryMinor,0,',','.') }} ₫</span><span>KPI {{ number_format($currentCompensation->kpiTargetMinor,0,',','.') }} ₫</span><span>Phụ cấp {{ number_format($currentCompensation->fixedAllowanceMinor,0,',','.') }} ₫</span><span>Target {{ number_format($currentCompensation->targetGrossMinor(),0,',','.') }} ₫</span></div>@endif
    @if($eligiblePositions->isEmpty())<p class="empty-state">{{ $type === 'promotion' ? 'Không còn chức vụ cấp cao hơn.' : 'Không còn chức vụ cấp thấp hơn.' }}</p>@endif
    <form method="POST" action="{{ route($route,['employee'=>$employee['id']]) }}" data-career-form>
        @csrf
        <input type="hidden" name="company_id" value="{{ $companyId }}">
        <input type="hidden" name="current_assignment_id" value="{{ $currentAssignment->id }}">
        <input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}">
        <label>Đơn vị / Phòng ban đích
            <select name="organization_unit_id" data-career-unit required>
                <option value="">Chọn đơn vị</option>
                @foreach($eligiblePositions->pluck('organizationUnit')->filter()->unique('id')->sortBy('name') as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Vị trí đích
            <select name="position_id" data-career-position required>
                <option value="">Chọn vị trí</option>
                @foreach($eligiblePositions as $position)
                    @php($reference=$positionReferences->get($position->id))
                    <option value="{{ $position->id }}" data-unit="{{ $position->organization_unit_id }}" @if($reference) data-compensation='@json($reference)' @endif hidden disabled>{{ $position->title }} — {{ $position->organizationUnit?->name }} — Level {{ $position->career_level }}</option>
                @endforeach
            </select>
        </label>
        @if($canViewCompensation)<div class="compensation-compare" data-projected-compensation hidden><strong>Projected Target Compensation</strong><span data-projected-base></span><span data-projected-kpi></span><span data-projected-allowance></span><span data-projected-target></span><small>Projection theo vị trí; chỉ trở thành salary assignment khi đi qua Payroll configuration hợp lệ.</small></div>@endif
        <label>Ngày hiệu lực<input type="datetime-local" name="effective_at" value="{{ now()->addDay()->format('Y-m-d\TH:i') }}" required></label>
        <label>Lý do<input name="reason" required maxlength="1000"></label>
        <button type="submit" @disabled($eligiblePositions->isEmpty())>{{ $label }}</button>
    </form>
</section>
@endcan
@endforeach
</div>
@endif
<div class="dashboard-grid"><section class="panel"><h2>Lifecycle timeline</h2><div class="timeline">@forelse($viewModel->timeline as $event)<article><strong>{{ $event['event_type'] }}</strong><span>{{ $event['from_state'] ?? '—' }} → {{ $event['to_state'] }}</span><small>{{ $event['effective_at'] }}</small></article>@empty<p class="empty-state">Lifecycle chưa được khởi tạo.</p>@endforelse</div></section><section class="panel"><h2>Employment history</h2>@forelse($viewModel->employmentHistory as $row)<p><strong>{{ $row['transition_type'] }}</strong><br>{{ $row['from_state'] ?? '—' }} → {{ $row['to_state'] }} · {{ $row['effective_at'] }}</p>@empty<p class="empty-state">Chưa có lịch sử.</p>@endforelse</section></div>
<div class="dashboard-grid"><section class="panel"><h2>Position history</h2>@forelse($viewModel->positionHistory as $row)<p>Position #{{ $row['position_id'] ?? '—' }} · Unit #{{ $row['organization_unit_id'] ?? '—' }}<br><small>{{ $row['effective_from'] }} → {{ $row['effective_to'] ?? 'hiện tại' }}</small></p>@empty<p class="empty-state">Chưa có lịch sử vị trí.</p>@endforelse</section><section class="panel"><h2>Manager history</h2>@forelse($viewModel->managerHistory as $row)<p>Manager #{{ $row['manager_id'] ?? '—' }}<br><small>{{ $row['effective_from'] }} → {{ $row['effective_to'] ?? 'hiện tại' }}</small></p>@empty<p class="empty-state">Chưa có lịch sử quản lý.</p>@endforelse</section></div>
@endsection

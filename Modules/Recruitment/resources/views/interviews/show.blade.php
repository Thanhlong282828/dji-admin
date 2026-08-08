@extends('layouts.admin')
@section('title', 'Chi tiết phỏng vấn')
@section('content')
<section class="panel" data-recruitment-page data-company-id="{{ $companyId }}" data-interview-id="{{ $interview->id }}" data-realtime-version="{{ $interview->version }}" data-realtime-entity="interview" data-realtime-entity-id="{{ $interview->id }}" data-refresh-url="{{ request()->fullUrl() }}"><span class="eyebrow">INTERVIEW ROUND {{ $interview->round_number }}</span><h1>{{ $interview->title }}</h1><span data-realtime-state>Đã đồng bộ</span><button type="button" data-realtime-refresh>Làm mới</button>
<dl><dt>Ứng viên</dt><dd>{{ $interview->application->candidate->full_name }}</dd><dt>Vị trí</dt><dd>{{ $interview->application->requisition->jobPosition->title }}</dd>
<dt>Thời gian</dt><dd>{{ $interview->starts_at->timezone($interview->timezone)->format('d/m/Y H:i') }} – {{ $interview->ends_at->timezone($interview->timezone)->format('H:i') }}</dd>
<dt>Trạng thái</dt><dd>{{ $interview->status->value }}</dd><dt>Panel</dt><dd>{{ $interview->interviewers->pluck('user.name')->join(', ') }}</dd></dl>
@can('interview.evaluate')<a class="button" href="{{ route('admin.interviews.evaluation.edit',['interview'=>$interview->id,'company_id'=>$companyId]) }}">Đánh giá</a>@endcan
@can('interview.view-panel-summary')<a class="button" href="{{ route('admin.interviews.panel-summary',['interview'=>$interview->id,'company_id'=>$companyId]) }}">Panel Summary</a>@endcan
@can('interview.reschedule')<form method="POST" action="{{ route('admin.interviews.reschedule',['interview'=>$interview->id]) }}">@csrf @method('PATCH')
<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $interview->version }}"><input type="datetime-local" name="starts_at" required><input type="datetime-local" name="ends_at" required><input name="timezone" value="{{ $interview->timezone }}" required><input name="reason" placeholder="Lý do đổi lịch" required><button>Đổi lịch</button></form>@endcan
@can('interview.assign-interviewer')<form method="POST" action="{{ route('admin.interviews.interviewers.store',['interview'=>$interview->id]) }}">@csrf
<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="number" name="interviewer_user_ids[]" placeholder="User ID" required><button>Thêm interviewer</button></form>
@foreach($interview->interviewers as $member)<form method="POST" action="{{ route('admin.interviews.interviewers.destroy',['interview'=>$interview->id,'user'=>$member->user_id]) }}">@csrf @method('DELETE')<input type="hidden" name="company_id" value="{{ $companyId }}"><button>Gỡ {{ $member->user->name }}</button></form>@endforeach @endcan
@foreach(['confirmed'=>'Xác nhận','completed'=>'Hoàn thành','no_show'=>'No-show','cancelled'=>'Hủy'] as $target=>$label)
@can($target === 'cancelled' ? 'interview.cancel' : ($target === 'confirmed' ? 'interview.reschedule' : 'interview.complete'))
<form method="POST" action="{{ route('admin.interviews.transition', ['interview'=>$interview->id,'target'=>$target]) }}">@csrf @method('PATCH')
<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $interview->version }}">
@if($target==='cancelled')<input name="reason" placeholder="Lý do hủy" required>@endif<button>{{ $label }}</button></form>
@endcan @endforeach
</section>
@endsection

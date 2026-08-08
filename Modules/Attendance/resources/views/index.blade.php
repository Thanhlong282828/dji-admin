@extends('layouts.admin')
@section('title','Workforce Attendance')
@section('content')
<section class="panel" data-company-id="{{ $companyId }}" data-workforce-page data-refresh-scope="attendance-list" data-refresh-url="{{request()->fullUrl()}}">
<span class="eyebrow">WORKFORCE OPERATIONS</span><h1>Chấm công</h1>
<div class="stats"><article><span>Events hôm nay</span><strong>{{ $dashboard->eventsToday }}</strong></article><article><span>Phiên mở</span><strong>{{ $dashboard->openSessions }}</strong></article><article><span>Điều chỉnh chờ duyệt</span><strong>{{ $dashboard->pendingAdjustments }}</strong></article><article><span>Anomaly</span><strong>{{ $dashboard->anomalies }}</strong></article></div>
@can('attendance.check-in')<form method="POST" action="{{ route('admin.attendance.check-in') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="source" value="web"><input type="hidden" name="deduplication_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><button>Check In</button></form>@endcan
@can('attendance.check-out')<form method="POST" action="{{ route('admin.attendance.check-out') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="source" value="web"><input type="hidden" name="deduplication_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><button>Check Out</button></form>@endcan
</section>
@endsection

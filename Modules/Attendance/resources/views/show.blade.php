@extends('layouts.admin')
@section('title', 'Chi tiết chấm công')
@section('content')
<section class="panel" data-attendance-record-id="{{ $detail->id }}">
    <span class="eyebrow">ATTENDANCE CONTROL</span>
    <h1>{{ $detail->employee }} — {{ $detail->workDate }}</h1>
    @if (session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert error">{{ implode(' ', $errors->all()) }}</div>@endif

    <div class="stats">
        <article><span>Trạng thái tính</span><strong>{{ $detail->calculationStatus }}</strong></article>
        <article><span>Phê duyệt</span><strong>{{ $detail->approvalStatus }}</strong></article>
        <article><span>Phút làm</span><strong>{{ $detail->workedMinutes }}</strong></article>
        <article><span>Khóa</span><strong>{{ $detail->locked ? 'Đã khóa' : 'Chưa khóa' }}</strong></article>
    </div>

    <h2>Session đã tổng hợp</h2>
    <p>Check-in: {{ $detail->checkIn ?? '—' }} · Check-out: {{ $detail->checkOut ?? '—' }} · Break: {{ $detail->breakMinutes }} phút</p>
    <h2>Raw events bất biến</h2>
    <table><thead><tr><th>Thời điểm</th><th>Loại</th><th>Nguồn</th></tr></thead><tbody>
    @forelse ($detail->events as $event)
        <tr><td>{{ $event['occurred_at'] }}</td><td>{{ $event['event_type'] }}</td><td>{{ $event['source'] }}</td></tr>
    @empty
        <tr><td colspan="3">Chưa có event.</td></tr>
    @endforelse
    </tbody></table>

    @unless ($detail->locked)
        @can('attendance.adjust')
            <details><summary>Yêu cầu điều chỉnh</summary>
                <form method="POST" action="{{ route('admin.attendance-adjustments.store', $detail->id) }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <input type="hidden" name="version" value="{{ $detail->version }}">
                    <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                    <label>Check-in<input type="datetime-local" name="check_in_at"></label>
                    <label>Check-out<input type="datetime-local" name="check_out_at"></label>
                    <label>Break<input type="number" name="break_minutes" min="0" value="0"></label>
                    <textarea name="reason" required placeholder="Lý do"></textarea>
                    <button>Tạo bản nháp</button>
                </form>
            </details>
        @endcan
    @endunless

    <h2>Adjustments</h2>
    @forelse ($detail->adjustments as $adjustment)
        @php
            $adjustmentActions = match ($adjustment['status']) {
                'draft' => ['submit' => 'Gửi duyệt', 'cancel' => 'Hủy'],
                'submitted' => ['approve' => 'Duyệt', 'reject' => 'Từ chối', 'cancel' => 'Hủy'],
                default => [],
            };
        @endphp
        <article>
            <strong>#{{ $adjustment['id'] }} — {{ $adjustment['status'] }}</strong>
            <p>{{ $adjustment['requested_check_in_at'] ?? '—' }} → {{ $adjustment['requested_check_out_at'] ?? '—' }}</p>
            @unless ($detail->locked)
                <div class="actions">
                    @foreach ($adjustmentActions as $action => $label)
                        @can(in_array($action, ['approve', 'reject'], true) ? 'attendance.approve' : 'attendance.adjust')
                            <form method="POST" action="{{ route('admin.attendance-adjustments.transition', [$adjustment['id'], $action]) }}">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $companyId }}">
                                <input type="hidden" name="version" value="{{ $adjustment['version'] }}">
                                @if (in_array($action, ['reject', 'cancel'], true))<input name="comment" required placeholder="Lý do">@endif
                                <button>{{ $label }}</button>
                            </form>
                        @endcan
                    @endforeach
                </div>
            @endunless
        </article>
    @empty
        <p>Chưa có adjustment.</p>
    @endforelse

    @php
        $attendanceActions = match ($detail->approvalStatus) {
            'draft', 'rejected' => ['submit' => 'Gửi duyệt attendance'],
            'submitted' => ['approve' => 'Duyệt attendance', 'reject' => 'Từ chối attendance'],
            default => [],
        };
    @endphp
    @unless ($detail->locked)
        <div class="actions">
            @foreach ($attendanceActions as $action => $label)
                @can(in_array($action, ['approve', 'reject'], true) ? 'attendance.approve' : 'attendance.adjust')
                    <form method="POST" action="{{ route('admin.attendance.transition', [$detail->id, $action]) }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $companyId }}">
                        <input type="hidden" name="version" value="{{ $detail->version }}">
                        @if ($action === 'reject')<input name="comment" required placeholder="Lý do">@endif
                        <button>{{ $label }}</button>
                    </form>
                @endcan
            @endforeach
        </div>
    @endunless
</section>
@endsection

@extends('layouts.admin')
@section('title', 'Lịch phỏng vấn')
@section('content')
<section class="panel" data-recruitment-page data-company-id="{{ $companyId }}" data-realtime-entity="interview" data-refresh-url="{{ request()->fullUrl() }}">
    <div class="page-heading"><div><span class="eyebrow">RECRUITMENT</span><h1>Lịch phỏng vấn</h1></div><span data-realtime-state>Đã đồng bộ</span><button type="button" data-realtime-refresh>Làm mới</button>
        @can('interview.schedule')<a class="button" href="{{ route('admin.interviews.create', ['company_id' => $companyId]) }}">Lên lịch</a>@endcan
        @can('interview.view')<a class="button" href="{{ route('admin.interviews.export', ['company_id' => $companyId]) }}">Export CSV</a>@endcan
    </div>
    <table><thead><tr><th>Thời gian</th><th>Ứng viên</th><th>Tiêu đề</th><th>Hình thức</th><th>Trạng thái</th><th>Panel</th></tr></thead>
    <tbody>@forelse($items as $item)<tr>
        <td>{{ $item->starts_at->timezone($item->timezone)->format('d/m/Y H:i') }}</td>
        <td>{{ $item->application->candidate->full_name }}</td>
        <td><a href="{{ route('admin.interviews.show', ['interview' => $item->id, 'company_id' => $companyId]) }}">{{ $item->title }}</a></td>
        <td>{{ $item->interview_type->value }}</td><td>{{ $item->status->value }}</td>
        <td>{{ $item->interviewers->pluck('user.name')->join(', ') }}</td>
    </tr>@empty<tr><td colspan="6" class="empty-state">Chưa có lịch phỏng vấn.</td></tr>@endforelse</tbody></table>
    {{ $items->withQueryString()->links() }}
</section>
@endsection

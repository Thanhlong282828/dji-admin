@extends('layouts.admin')
@section('title', 'Hồ sơ onboarding')

@section('content')
<x-erp.data-table title="Hồ sơ onboarding" description="Case, checklist và mốc thử việc từ owner-domain Onboarding." label="Danh sách hồ sơ onboarding">
    <x-slot:filters>
        <x-erp.filter-toolbar>
            <form method="GET" class="erp-filter-form">
                <input type="hidden" name="company_id" value="{{ $companyId }}">
                <label for="onboarding-status">Trạng thái</label>
                <select id="onboarding-status" name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach(['preboarding','in_progress','probation_review','completed','cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', strtoupper($status)) }}</option>
                    @endforeach
                </select>
                <button class="erp-button" type="submit">Lọc hồ sơ</button>
            </form>
        </x-erp.filter-toolbar>
    </x-slot:filters>

    <table class="erp-table">
        <thead><tr><th scope="col">Nhân viên</th><th scope="col">Buddy</th><th scope="col">Ngày bắt đầu</th><th scope="col">Probation review</th><th scope="col">Checklist</th><th scope="col">Status</th></tr></thead>
        <tbody>
        @forelse($items as $item)
            @php($completedChecklist = collect($item->checklist)->where('status', 'completed')->count())
            <tr>
                <td><strong>{{ $item->employee->full_name }}</strong><small class="erp-table__meta">{{ $item->employee->employee_code }}</small></td>
                <td>{{ $item->buddy?->full_name ?? 'Chưa phân công' }}</td>
                <td>{{ $item->start_date->format('d/m/Y') }}</td>
                <td>{{ $item->probation_review_date?->format('d/m/Y') ?? '—' }}</td>
                <td><span class="erp-progress-text" role="progressbar" aria-label="Tiến độ checklist onboarding của {{ $item->employee->full_name }}" aria-valuemin="0" aria-valuemax="{{ count($item->checklist) }}" aria-valuenow="{{ $completedChecklist }}">{{ $completedChecklist }}/{{ count($item->checklist) }} hoàn tất</span></td>
                <td><span class="erp-status-badge">{{ str_replace('_', ' ', strtoupper($item->status->value)) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6"><x-erp.empty-state title="Chưa có onboarding case" description="Case chỉ được tạo qua quy trình tuyển dụng và hire hợp lệ; hệ thống không tạo dữ liệu mẫu." /></td></tr>
        @endforelse
        </tbody>
    </table>

    <x-slot:pagination>{{ $items->withQueryString()->links() }}</x-slot:pagination>
</x-erp.data-table>
@endsection

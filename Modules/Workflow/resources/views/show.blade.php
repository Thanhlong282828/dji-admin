@extends('layouts.admin')
@section('title', 'Workflow Instance')
@section('content')
@include('workflow::partials.styles')
@php($instance = $vm->instances[0])
<div class="erp-board-workspace">
    <header class="erp-board-workspace__header">
        <div>
            <p class="erp-dashboard__eyebrow">WORKFLOW · IMMUTABLE EXECUTION</p>
            <h2>Instance #{{ $instance['id'] }} · {{ $instance['workflow_type'] }}</h2>
            <p>{{ $instance['subject_type'] }} #{{ $instance['subject_id'] }} · Mọi bước và quyết định lấy từ workflow read model hiện hành.</p>
        </div>
        <div class="erp-board-workspace__sync">
            <x-erp.status-badge :status="$instance['engine_status']" />
            @can('workflow.audit.view')
                <x-erp.button variant="outline" :href="route('admin.workflows.instances.audit', ['instance' => $instance['id'], 'company_id' => $companyId])">Xem audit trail</x-erp.button>
            @endcan
        </div>
    </header>

    <div class="erp-workflow-summary" aria-label="Tóm tắt workflow instance">
        <x-erp.detail-panel title="Trạng thái hiện tại" description="Canonical engine status"><strong class="erp-detail-value">{{ strtoupper($instance['engine_status']) }}</strong></x-erp.detail-panel>
        <x-erp.detail-panel title="Bước hiện tại" description="Bước pending/claimed đầu tiên theo sequence">
            @php($currentTask = collect($vm->tasks)->first(fn ($task) => in_array($task['status'], ['pending', 'claimed'], true)))
            <strong class="erp-detail-value">{{ $currentTask ? $currentTask['sequence'].'. '.$currentTask['step_name'] : 'Không còn bước đang mở' }}</strong>
        </x-erp.detail-panel>
        <x-erp.detail-panel title="Nguồn nghiệp vụ" description="Subject reference được workflow pin khi khởi tạo"><strong class="erp-detail-value">{{ $instance['subject_type'] }} #{{ $instance['subject_id'] }}</strong></x-erp.detail-panel>
    </div>

    <x-erp.data-table title="Các bước và quyết định" description="Actor, trạng thái, lock version và action permission-aware của từng task.">
        <table>
            <thead><tr><th scope="col">Bước</th><th scope="col">Actor</th><th scope="col">Trạng thái</th><th scope="col">Version</th><th scope="col">Quyết định</th></tr></thead>
            <tbody>
            @forelse($vm->tasks as $row)
                <tr>
                    <td><strong>{{ $row['sequence'] }}. {{ $row['step_name'] }}</strong><small class="erp-table__meta">Task #{{ $row['id'] }}</small></td>
                    <td>{{ $row['assigned_user_id'] ? '#'.$row['assigned_user_id'] : 'Chưa phân công' }}</td>
                    <td><x-erp.status-badge :status="$row['status']" /></td>
                    <td class="numeric">{{ $row['lock_version'] }}</td>
                    <td>
                        @if(in_array($row['status'], ['pending', 'claimed'], true) && ((int) ($row['assigned_user_id'] ?? 0) === (int) auth()->id() || (int) ($row['claimed_by'] ?? 0) === (int) auth()->id()))
                            @can('workflow.task.decide')
                                <form class="erp-decision-form" method="POST" action="{{ route('admin.workflows.tasks.decide', ['task' => $row['id']]) }}">
                                    @csrf
                                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                                    <input type="hidden" name="idempotency_key" value="decision-{{ $row['id'] }}-{{ auth()->id() }}">
                                    <label><span>Lý do quyết định</span><input name="reason" placeholder="Nhập lý do khi từ chối"></label>
                                    <div class="erp-row-actions"><x-erp.button type="submit" name="decision" value="approve">Phê duyệt</x-erp.button><x-erp.button type="submit" variant="danger" name="decision" value="reject">Từ chối</x-erp.button></div>
                                </form>
                            @endcan
                        @else
                            <span class="erp-table__meta">Không có action khả dụng</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-erp.empty-state title="Chưa có workflow task" description="Task chỉ xuất hiện sau khi workflow instance được khởi tạo theo definition đã publish." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </x-erp.data-table>

    <x-erp.detail-panel title="Audit timeline" description="Lịch sử append-only; không tạo sự kiện trình diễn hoặc suy diễn actor.">
        <div class="erp-timeline">
            @forelse($vm->history as $event)
                <article class="erp-timeline__item">
                    <span class="erp-timeline__marker" aria-hidden="true"></span>
                    <div class="erp-timeline__content"><header><strong>{{ $event['event'] }}</strong><time>{{ $event['created_at'] }}</time></header><p>{{ $event['from_status'] ?? 'START' }} → {{ $event['to_status'] ?? '—' }}</p><small>Actor: {{ $event['actor_id'] ? '#'.$event['actor_id'] : 'System' }}@if($event['workflow_task_id']) · Task #{{ $event['workflow_task_id'] }}@endif</small></div>
                </article>
            @empty
                <x-erp.empty-state title="Chưa có lịch sử workflow" description="Timeline sẽ hiển thị khi engine ghi sự kiện append-only đầu tiên." />
            @endforelse
        </div>
    </x-erp.detail-panel>
</div>
@endsection

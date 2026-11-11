@extends('layouts.admin')
@section('title', 'Workflow audit trail')
@section('content')
@include('workflow::partials.styles')
@php($instance = $vm->instances[0])
<div class="erp-board-workspace">
    <header class="erp-board-workspace__header">
        <div><p class="erp-dashboard__eyebrow">GOVERNANCE · IMMUTABLE HISTORY</p><h2>Workflow audit trail</h2><p>Instance #{{ $instance['id'] }} · {{ $instance['workflow_type'] }} · {{ count($vm->history) }} sự kiện canonical.</p></div>
        <x-erp.button variant="outline" :href="route('admin.workflows.instances.show', ['instance' => $instance['id'], 'company_id' => $companyId])">Quay lại instance</x-erp.button>
    </header>
    <x-erp.data-table title="Sự kiện append-only" description="Actor, state transition và timestamp lấy trực tiếp từ workflow history.">
        <table><thead><tr><th scope="col">Sự kiện</th><th scope="col">Actor</th><th scope="col">Transition</th><th scope="col">Recorded at</th></tr></thead><tbody>
        @forelse($vm->history as $event)
            <tr><td><strong>{{ $event['event'] }}</strong>@if($event['workflow_task_id'])<small class="erp-table__meta">Task #{{ $event['workflow_task_id'] }}</small>@endif</td><td>{{ $event['actor_id'] ? '#'.$event['actor_id'] : 'System' }}</td><td>{{ $event['from_status'] ?? 'START' }} → {{ $event['to_status'] ?? '—' }}</td><td>{{ $event['created_at'] }}</td></tr>
        @empty
            <tr><td colspan="4"><x-erp.empty-state title="Chưa có audit event" description="Không có sự kiện append-only nào được ghi cho instance này." /></td></tr>
        @endforelse
        </tbody></table>
    </x-erp.data-table>
</div>
@endsection

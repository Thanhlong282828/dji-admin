@extends('layouts.admin')
@section('title','My Approval Tasks')
@section('content')
@include('workflow::partials.styles')
<x-erp.data-table title="Decision inbox" description="Approval tasks từ workflow engine; hành động chỉ hiển thị khi đúng trạng thái và quyền hiện tại." label="Danh sách approval tasks">
<x-slot:actions><span class="wf-status">{{ count($vm->tasks) }} tasks</span></x-slot:actions>
<table class="erp-table"><thead><tr><th scope="col">Step</th><th scope="col">Instance</th><th scope="col">Status</th><th scope="col">Due</th><th scope="col">Actions</th></tr></thead><tbody>
@forelse($vm->tasks as $row)
<tr><td><strong>{{ $row['step_name'] }}</strong></td><td><a class="erp-source-link" href="{{ route('admin.workflows.instances.show',['instance'=>$row['workflow_instance_id'],'company_id'=>$companyId]) }}">#{{ $row['workflow_instance_id'] }} <small>Mở workflow →</small></a></td><td><span class="wf-status">{{ $row['status'] }}</span></td><td>{{ $row['due_at'] ?? '—' }}</td><td><div class="erp-row-actions">
    @if($row['status']==='pending')
        @can('workflow.task.claim')
            <form method="POST" action="{{ route('admin.workflows.tasks.claim',['task'=>$row['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $row['lock_version'] }}"><input type="hidden" name="idempotency_key" value="claim-{{ $row['id'] }}-{{ auth()->id() }}"><button class="wf-btn" type="submit">Claim</button></form>
        @endcan
    @endif
</div></td></tr>
@empty
<tr><td colspan="5"><x-erp.empty-state title="Không có approval task đang chờ" description="Task sẽ xuất hiện khi domain owner khởi chạy workflow cần phê duyệt." /></td></tr>
@endforelse
</tbody></table>
</x-erp.data-table>
@endsection

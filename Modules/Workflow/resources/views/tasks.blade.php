@extends('layouts.admin')
@section('title','My Approval Tasks')
@section('content')
@include('workflow::partials.styles')
<section class="wf-panel"><div class="wf-heading"><h2>Decision inbox</h2><span class="wf-status">{{ count($vm->tasks) }} tasks</span></div><div class="wf-table"><table><thead><tr><th>Step</th><th>Instance</th><th>Status</th><th>Due</th><th>Actions</th></tr></thead><tbody>
@forelse($vm->tasks as $row)
<tr><td>{{ $row['step_name'] }}</td><td><a href="{{ route('admin.workflows.instances.show',['instance'=>$row['workflow_instance_id'],'company_id'=>$companyId]) }}">#{{ $row['workflow_instance_id'] }}</a></td><td>{{ $row['status'] }}</td><td>{{ $row['due_at'] ?? '—' }}</td><td><div class="wf-actions">
    @if($row['status']==='pending')
        @can('workflow.task.claim')
            <form method="POST" action="{{ route('admin.workflows.tasks.claim',['task'=>$row['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $row['lock_version'] }}"><input type="hidden" name="idempotency_key" value="claim-{{ $row['id'] }}-{{ auth()->id() }}"><button class="wf-btn">Claim</button></form>
        @endcan
    @endif
</div></td></tr>
@empty
<tr><td colspan="5" class="wf-empty">Không có approval task đang chờ.</td></tr>
@endforelse
</tbody></table></div></section>
@endsection

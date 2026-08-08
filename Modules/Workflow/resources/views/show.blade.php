@extends('layouts.admin')
@section('title','Workflow Instance')
@section('content')
@include('workflow::partials.styles')
@php($instance=$vm->instances[0])
<div class="wf-grid"><section class="wf-panel"><div class="wf-heading"><div><small>IMMUTABLE EXECUTION</small><h2>#{{ $instance['id'] }} · {{ $instance['workflow_type'] }}</h2></div><span class="wf-status">{{ $instance['engine_status'] }}</span></div><p>{{ $instance['subject_type'] }} · {{ $instance['subject_id'] }}</p><div class="wf-table"><table><thead><tr><th>Step</th><th>Assignee</th><th>Status</th><th>Version</th><th>Decision</th></tr></thead><tbody>
@foreach($vm->tasks as $row)
<tr><td>{{ $row['sequence'] }}. {{ $row['step_name'] }}</td><td>#{{ $row['assigned_user_id'] ?? 'unassigned' }}</td><td>{{ $row['status'] }}</td><td>{{ $row['lock_version'] }}</td><td>
    @if(in_array($row['status'],['pending','claimed'],true) && ((int) ($row['assigned_user_id'] ?? 0) === (int) auth()->id() || (int) ($row['claimed_by'] ?? 0) === (int) auth()->id()))
        @can('workflow.task.decide')
            <form class="wf-actions" method="POST" action="{{ route('admin.workflows.tasks.decide',['task'=>$row['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="idempotency_key" value="decision-{{ $row['id'] }}-{{ auth()->id() }}"><input name="reason" placeholder="Reason"><button class="wf-btn" name="decision" value="approve">Approve</button><button class="wf-btn" name="decision" value="reject">Reject</button></form>
        @endcan
    @endif
</td></tr>
@endforeach
</tbody></table></div></section><aside class="wf-panel"><h3>Append-only timeline</h3><div class="wf-timeline">@forelse($vm->history as $event)<div class="wf-event"><strong>{{ $event['event'] }}</strong><br><small>{{ $event['from_status'] ?? '—' }} → {{ $event['to_status'] ?? '—' }} · {{ $event['created_at'] }}</small></div>@empty<p class="wf-empty">No history.</p>@endforelse</div></aside></div>
@endsection

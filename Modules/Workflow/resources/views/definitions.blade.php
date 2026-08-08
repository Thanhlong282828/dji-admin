@extends('layouts.admin')
@section('title','Workflow Definitions')
@section('header_action')
    @can('workflow.definition.manage')
        <a class="wf-btn" href="{{ route('admin.workflows.definitions.create',['company_id'=>$companyId]) }}">+ New definition</a>
    @endcan
@endsection
@section('content')
@include('workflow::partials.styles')
<section class="wf-panel"><div class="wf-heading"><div><small>VERSIONED BLUEPRINTS</small><h2>Approval definitions</h2></div><span class="wf-status">{{ $vm->definitionCount }} definitions</span></div><div class="wf-table"><table><thead><tr><th>Code</th><th>Name</th><th>Version</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($vm->definitions as $row)
<tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td>v{{ $row['latest_version'] }}</td><td><span class="wf-status">{{ $row['status'] }}</span></td><td>
    @if($row['status']==='draft')
        @can('workflow.definition.manage')
            <form method="POST" action="{{ route('admin.workflows.definitions.publish',['definition'=>$row['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><button class="wf-btn">Publish</button></form>
        @endcan
    @endif
</td></tr>
@empty
<tr><td colspan="5" class="wf-empty">No definitions yet.</td></tr>
@endforelse
</tbody></table></div></section>
@endsection

@extends('layouts.admin')
@section('title','Enterprise Workflow Center')
@section('header_action')
@can('workflow.definition.manage')<a class="wf-btn" href="{{ route('admin.workflows.definitions.create',['company_id'=>$companyId]) }}">+ Thiết kế workflow</a>@endcan
@endsection
@section('content')
@include('workflow::partials.styles')
<x-erp.dashboard eyebrow="Phase 3F · Orchestration" title="Approval orchestration, without domain coupling." description="Versioned definitions, immutable execution snapshots, maker-checker decisions and append-only history.">
<x-erp.dashboard-section title="Operations status" description="Số liệu từ workflow read model hiện tại." columns="4">
    <x-erp.kpi-card label="Definitions" :value="$vm->definitionCount" icon="◇" />
    <x-erp.kpi-card label="Running" :value="$vm->runningCount" icon="↻" status="LIVE ENGINE" />
    <x-erp.kpi-card label="Pending tasks" :value="$vm->pendingTaskCount" icon="◷" :href="route('admin.workflows.tasks',['company_id'=>$companyId])" action-label="Mở task inbox" />
    <x-erp.kpi-card label="Overdue" :value="$vm->overdueTaskCount" icon="!" />
</x-erp.dashboard-section>
<div class="wf-grid"><section class="wf-panel"><div class="wf-heading"><h3>Recent instances</h3><a href="{{ route('admin.workflows.tasks',['company_id'=>$companyId]) }}">Open task inbox →</a></div><div class="wf-table"><table><thead><tr><th>ID</th><th>Workflow</th><th>Subject</th><th>Status</th><th></th></tr></thead><tbody>@forelse($vm->instances as $row)<tr><td>#{{ $row['id'] }}</td><td>{{ $row['workflow_type'] }}</td><td>{{ $row['subject_type'] }} · {{ $row['subject_id'] }}</td><td><span class="wf-status">{{ $row['engine_status'] }}</span></td><td><a href="{{ route('admin.workflows.instances.show',['instance'=>$row['id'],'company_id'=>$companyId]) }}">Detail</a></td></tr>@empty<tr><td colspan="5" class="wf-empty">Chưa có workflow instance. Hãy publish definition và khởi chạy từ domain owner.</td></tr>@endforelse</tbody></table></div></section><aside class="wf-panel"><h3>Engine contract</h3><ul class="wf-list"><li>Domain remains source of truth</li><li>Definition version pinned at start</li><li>Callbacks emitted through outbox</li><li>No salary, tax or bank payload</li></ul></aside></div>
</x-erp.dashboard>
@endsection

@extends('layouts.admin')
@section('title', 'CRM Pipeline')
@section('content')
<div class="admin-page">
    <header class="admin-page-header"><div><p class="admin-eyebrow">CRM BOUNDED CONTEXT</p><div class="erp-title-with-help"><h2>CRM Pipeline</h2><x-erp.process-help :title="$processHelp['title']" :steps="$processHelp['steps']" :route="route('admin.crm.index',['company_id'=>$company,'branch_id'=>$branch])" :description="$processHelp['description']" :owner="$processHelp['owner']" :accounting-impact="$processHelp['accounting_impact']" /></div><p>Aggregate read model: Lead → Opportunity → Commerce/Sales handoff.</p></div></header>
    @if(! $data['schema_ready'])
        <section class="admin-panel"><div class="erp-empty-state"><span>?</span><div><strong>NO CANONICAL CRM SOURCE</strong><p>CRM schema chưa sẵn sàng.</p></div><span>SCHEMA_UPGRADE_REQUIRED</span></div></section>
    @else
        <section class="erp-kpi-grid">
            <x-erp.kpi-card label="Active Leads" :value="number_format($data['metrics']['active_leads'])" meta="Source: crm_leads · GetCrmPipelineQuery" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Leads ↗" />
            <x-erp.kpi-card label="Opportunities" :value="number_format($data['metrics']['opportunities'])" meta="Source: crm_opportunities" :href="route('admin.crm.opportunities',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Opportunities ↗" />
            <x-erp.kpi-card label="Conversion Rate" :value="number_format($data['metrics']['conversion_rate'],2).'% '" meta="Converted leads / canonical leads" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch])" action-label="View source ↗" />
            <x-erp.kpi-card label="Expected Revenue" :value="number_format($data['metrics']['expected_revenue'],0,',','.').' ₫'" meta="Expected value × probability" :href="route('admin.crm.opportunities',['company_id'=>$company,'branch_id'=>$branch])" action-label="View source ↗" />
        </section>
        @if($data['leads']->isEmpty() && $data['opportunities']->isEmpty())
            <section class="admin-panel"><div class="erp-empty-state"><span>↗</span><div><strong>NO CRM OPERATING DATA</strong><p>Schema đã sẵn sàng. Hãy tạo Lead từ workflow CRM; hệ thống không tạo dữ liệu giả.</p></div>@can('crm.manage')<a href="{{ route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch]) }}">Create Lead ↗</a>@endcan</div></section>
        @else
            <section class="admin-panel"><h2>Pipeline sources</h2><p><a class="erp-source-link" href="{{ route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch]) }}">{{ number_format($data['leads']->count()) }} canonical Leads <small>Open Lead workspace ↗</small></a></p><p><a class="erp-source-link" href="{{ route('admin.crm.opportunities',['company_id'=>$company,'branch_id'=>$branch]) }}">{{ number_format($data['opportunities']->count()) }} canonical Opportunities <small>Open Opportunity workspace ↗</small></a></p></section>
        @endif
    @endif
</div>
@endsection

@extends('layouts.admin')
@section('title', 'CRM Pipeline')
@section('content')
<div class="admin-page">
    <header class="admin-page-header"><div><p class="admin-eyebrow">CRM BOUNDED CONTEXT</p><div class="erp-title-with-help"><h1>Lead · Opportunity Pipeline</h1><x-erp.process-help :title="$processHelp['title']" :steps="$processHelp['steps']" :route="route('admin.crm.index',['company_id'=>$company,'branch_id'=>$branch])" :description="$processHelp['description']" :owner="$processHelp['owner']" :accounting-impact="$processHelp['accounting_impact']" /></div><p>Lead → Opportunity → Customer → Quotation → Sales Order → Cash Flow.</p></div></header>

    @if(! $data['schema_ready'])
        <section class="admin-panel"><div class="erp-empty-state"><span>?</span><div><strong>NO CANONICAL CRM SOURCE</strong><p>CRM migration source đã được thiết kế nhưng chưa được chạy. Không có record giả hoặc KPI giả được tạo.</p></div><span>SCHEMA_UPGRADE_REQUIRED</span></div></section>
    @else
        <section class="erp-kpi-grid">
            <x-erp.kpi-card label="Active Leads" :value="number_format($data['metrics']['active_leads'])" meta="Nguồn: crm_leads · GetCrmPipelineQuery" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Leads ↗" />
            <x-erp.kpi-card label="Opportunities" :value="number_format($data['metrics']['opportunities'])" meta="Nguồn: crm_opportunities" :href="route('admin.crm.opportunities',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Opportunities ↗" />
            <x-erp.kpi-card label="Conversion Rate" :value="number_format($data['metrics']['conversion_rate'],2).'% '" meta="Converted leads / canonical leads" :href="route('admin.crm.index',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Pipeline ↗" />
            <x-erp.kpi-card label="Expected Revenue" :value="number_format($data['metrics']['expected_revenue'],0,',','.').' ₫'" meta="Expected value × probability" :href="route('admin.crm.opportunities',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Source ↗" />
        </section>

        @can('crm.manage')
        <section class="admin-panel"><h2>Create Lead</h2><form class="admin-form-grid" method="POST" action="{{ route('admin.crm.mutate',['action'=>'lead-create']) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><input type="hidden" name="branch_id" value="{{ $branch }}"><label>Name<input name="name" required></label><label>Contact<input name="contact" required></label><label>Source<input name="source"></label><button class="admin-button">Create Lead</button></form></section>
        @endcan

        <section class="admin-panel" id="crm-leads"><h2>Leads</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Lead</th><th>Contact</th><th>Source</th><th>Status</th><th>Owner</th><th>Next</th></tr></thead><tbody>
        @forelse($data['leads'] as $lead)<tr id="lead-{{ $lead->id }}"><td><a class="erp-source-link" href="#lead-{{ $lead->id }}"><strong>{{ $lead->name }}</strong><small>Lead #{{ $lead->id }} · canonical source</small></a></td><td>{{ $lead->contact }}</td><td>{{ $lead->source ?: 'Direct' }}</td><td>{{ $lead->status }}</td><td>User #{{ $lead->owner_id }}</td><td>
            @if($lead->status !== 'converted')
                @can('crm.manage')<form class="admin-inline-form" method="POST" action="{{ route('admin.crm.mutate',['action'=>'lead-convert']) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><input type="hidden" name="id" value="{{ $lead->id }}"><input type="number" min="0" name="expected_value_minor" placeholder="Expected value"><input type="number" min="0" max="100" name="probability" placeholder="Probability"><button>Convert to Opportunity</button></form>@endcan
            @else
                <a class="erp-source-link" href="#crm-opportunities">View Opportunity ↓</a>
            @endif
        </td></tr>
        @empty<tr><td colspan="6"><div class="erp-empty-state"><span>↗</span><div><strong>No operating data available</strong><p>Lead phải được tạo qua CRM command workflow.</p></div>@can('crm.manage')<a href="#">Create Lead ↑</a>@endcan</div></td></tr>@endforelse
        </tbody></table></div></section>

        <section class="admin-panel" id="crm-opportunities"><h2>Opportunities</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Opportunity</th><th>Created from Lead</th><th>Customer</th><th>Stage</th><th>Expected value</th><th>Probability</th><th>Next</th></tr></thead><tbody>
        @forelse($data['opportunities'] as $opportunity)<tr id="opportunity-{{ $opportunity->id }}"><td><a class="erp-source-link" href="#opportunity-{{ $opportunity->id }}"><strong>Opportunity #{{ $opportunity->id }}</strong><small>crm_opportunities canonical</small></a></td><td>@if($opportunity->lead_id)<a class="erp-source-link" href="#lead-{{ $opportunity->lead_id }}">Lead #{{ $opportunity->lead_id }}<small>View source ↑</small></a>@else No upstream Lead @endif</td><td>
            @if($opportunity->customer_id)
                @can('branch-commerce.view')<a class="erp-source-link" href="{{ route('admin.branch-commerce.customers',['company_id'=>$company,'branch_id'=>$branch]) }}">Customer #{{ $opportunity->customer_id }}<small>Open Customer Workspace ↗</small></a>@else Customer #{{ $opportunity->customer_id }} @endcan
            @else
                Not linked
            @endif
        </td><td>{{ $opportunity->stage }}</td><td>{{ number_format($opportunity->expected_value_minor,0,',','.') }} ₫</td><td>{{ $opportunity->probability }}%</td><td>@can('sales.view')<a class="erp-source-link" href="{{ route('admin.sales.index',['company_id'=>$company,'branch_id'=>$branch]) }}">Create Quotation<small>Open Sales Workspace ↗</small></a>@endcan</td></tr>
        @empty<tr><td colspan="7"><div class="erp-empty-state"><span>↗</span><div><strong>No operating data available</strong><p>Opportunity được tạo trực tiếp hoặc convert từ Lead thật.</p></div>@can('crm.manage')<a href="#crm-leads">Create from upstream workflow ↑</a>@endcan</div></td></tr>@endforelse
        </tbody></table></div></section>

        <section class="admin-panel"><h2>Owner workload</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Owner</th><th>Active lead source</th></tr></thead><tbody>@forelse($data['owners'] as $owner)<tr><td>{{ $owner->name }}</td><td><a class="erp-source-link" href="#crm-leads">{{ number_format($owner->lead_count) }} Lead<small>View scoped source ↑</small></a></td></tr>@empty<tr><td colspan="2">No operating data available</td></tr>@endforelse</tbody></table></div></section>
    @endif
</div>
@endsection

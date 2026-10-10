@extends('layouts.admin')
@section('title', 'CRM Leads')
@section('content')
<div class="admin-page">
    <header class="admin-page-header"><div><p class="admin-eyebrow">CRM · LEAD LIFECYCLE</p><div class="erp-title-with-help"><h2>Leads</h2><x-erp.process-help :title="$processHelp['title']" :steps="$processHelp['steps']" :route="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch])" :description="$processHelp['description']" :owner="$processHelp['owner']" :accounting-impact="$processHelp['accounting_impact']" /></div><p>Capture, qualification and conversion of canonical CRM leads.</p></div></header>
    @if(! $data['schema_ready'])
        <section class="admin-panel"><div class="erp-empty-state"><strong>SCHEMA_UPGRADE_REQUIRED</strong></div></section>
    @else
        <section class="erp-kpi-grid"><x-erp.kpi-card label="Total Leads" :value="number_format($data['metrics']['total'])" meta="Source: crm_leads" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch])" action-label="View Leads ↗" /><x-erp.kpi-card label="Open Leads" :value="number_format($data['metrics']['open'])" meta="new + contacted + qualified" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch])" action-label="View source ↗" /><x-erp.kpi-card label="Qualified" :value="number_format($data['metrics']['qualified'])" meta="Source status: qualified" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch,'status'=>'qualified'])" action-label="Filter ↗" /><x-erp.kpi-card label="Converted" :value="number_format($data['metrics']['converted'])" meta="Lead with conversion state" :href="route('admin.crm.leads',['company_id'=>$company,'branch_id'=>$branch,'status'=>'converted'])" action-label="View lineage ↗" /></section>
        @can('crm.manage')<section class="admin-panel"><h2>Create Lead</h2><form class="admin-form-grid" method="POST" action="{{ route('admin.crm.mutate',['action'=>'lead-create']) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><input type="hidden" name="branch_id" value="{{ $branch }}"><label>Name<input name="name" required></label><label>Contact<input name="contact" required></label><label>Source<input name="source"></label><button class="admin-button">Create Lead</button></form></section>@endcan
        <section class="admin-panel"><form class="admin-form-grid" method="GET"><input type="hidden" name="company_id" value="{{ $company }}"><input type="hidden" name="branch_id" value="{{ $branch }}"><label>Search<input name="search" value="{{ request('search') }}"></label><label>Status<select name="status"><option value="">All</option>@foreach(['new','contacted','qualified','converted','lost'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></label><button>Filter</button></form></section>
        <section class="admin-panel"><h2>Lead workspace</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Lead</th><th>Contact / Source</th><th>Status</th><th>Owner</th><th>Created</th><th>Lineage / Next</th></tr></thead><tbody>
        @forelse($data['leads'] as $lead)
            <tr id="lead-{{ $lead->id }}">
                <td><a class="erp-source-link" href="#lead-{{ $lead->id }}"><strong>{{ $lead->name }}</strong><small>Lead #{{ $lead->id }} · crm_leads</small></a></td>
                <td>{{ $lead->contact }}<small>{{ $lead->source ?: 'Direct' }}</small></td>
                <td>{{ $lead->status }}</td>
                <td>{{ $lead->owner_name ?: 'User #'.$lead->owner_id }}</td>
                <td>{{ $lead->created_at }}</td>
                <td>
                    @if($lead->opportunity_id)
                        <a class="erp-source-link" href="{{ route('admin.crm.opportunities',['company_id'=>$company,'branch_id'=>$branch,'search'=>$lead->name]) }}">Opportunity #{{ $lead->opportunity_id }}<small>View converted opportunity ↗</small></a>
                    @elseif($lead->status !== 'converted')
                        @can('crm.manage')
                            <form method="POST" action="{{ route('admin.crm.mutate',['action'=>'lead-convert']) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><input type="hidden" name="id" value="{{ $lead->id }}"><button>Convert to Opportunity</button></form>
                        @endcan
                    @else
                        Converted
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="erp-empty-state"><span>↗</span><div><strong>NO LEADS YET</strong><p>Create a Lead through the CRM command workflow.</p></div></div></td></tr>
        @endforelse
        </tbody></table></div></section>
    @endif
</div>
@endsection

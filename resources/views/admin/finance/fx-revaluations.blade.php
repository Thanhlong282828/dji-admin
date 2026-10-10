@extends('layouts.admin')
@section('title','FX Revaluation')
@section('content')
<div class="fx-workspace"><x-erp.functional-currency-status :company="$data['company']" /></div>
<div class="erp-workspace">
    <section class="page-hero panel">
        <div class="erp-title-with-help"><h2>AR / AP FX Revaluation</h2><x-erp.process-help :title="$data['process_help']['title']" :steps="$data['process_help']['steps']" :route="route('admin.finance.fx-revaluation',['company_id'=>$companyId])" :description="$data['process_help']['description']" :owner="$data['process_help']['owner']" :accounting-impact="$data['process_help']['accounting_impact']" /></div>
        <p>Định giá lại khoản phải thu và phải trả ngoại tệ còn mở theo closing rate cuối kỳ; recognition, settlement và unrealized adjustment giữ evidence riêng biệt.</p>
    </section>
    @if($data['permissions']['manage'])
        <section class="admin-panel">
            <h3>Tạo run từ AR / AP evidence</h3>
            <form method="POST" action="{{ route('admin.finance.fx-revaluation.create') }}" class="erp-filter-grid">
                @csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}">
                <input type="hidden" name="idempotency_key" value="{{ $data['create_idempotency_key'] }}">
                <label>Kỳ kế toán<select name="accounting_period_id" required><option value="">Chọn kỳ đang mở</option>@foreach($data['periods'] as $period)<option value="{{ $period->id }}">{{ $period->year }}-{{ str_pad((string) $period->month, 2, '0', STR_PAD_LEFT) }} · {{ $period->start_date }} → {{ $period->end_date }}</option>@endforeach</select></label>
                <label>Ngày định giá<input type="date" name="as_of_date" required></label>
                <label>Chi nhánh<select name="branch_id"><option value="">Toàn công ty</option>@foreach($data['branches'] as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} · {{ $branch->name }}</option>@endforeach</select></label>
                <button type="submit">Tạo AR / AP FX run</button>
            </form>
            @if($data['periods']->isEmpty())<p class="erp-empty-note">No operating data available — cần kỳ kế toán đang mở trước khi tạo run.</p>@endif
        </section>
    @endif
    <section class="admin-panel">
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Reference</th><th>As of / Period</th><th>Functional</th><th>Sources</th><th>FX difference</th><th>Status</th><th>Journal / Reversal</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($data['runs'] as $run)
                <tr>
                    <td>FXR-{{ $run->id }}</td><td>{{ $run->as_of_date }}<small>{{ $run->period_code ?? '#'.$run->accounting_period_id }}</small></td><td>{{ $run->functional_currency }}</td><td>{{ $run->source_count ?? 0 }}</td><td>{{ number_format((int) ($run->difference_minor ?? 0),0,',','.') }} {{ $run->functional_currency }}</td><td><span class="status-pill">{{ strtoupper($run->status) }}</span></td>
                    <td>@if($run->accounting_journal_id && $data['permissions']['journal'])<a class="erp-source-link" href="{{ route('admin.payroll.accounting.journals.show',['journal'=>$run->accounting_journal_id,'company_id'=>$companyId]) }}">{{ $run->journal_number }}</a>@elseif($run->journal_number){{ $run->journal_number }}@else — @endif @if($run->reversal_journal_id)<small>{{ $run->reversal_journal_number }}</small>@endif</td>
                    <td>@if($run->status === 'draft' && $data['permissions']['manage'])<form method="POST" action="{{ route('admin.finance.fx-revaluation.transition',['run'=>$run->id,'action'=>'submit','company_id'=>$companyId]) }}">@csrf<button type="submit">Submit</button></form>@elseif($run->status === 'submitted' && $data['permissions']['approve'])<form method="POST" action="{{ route('admin.finance.fx-revaluation.transition',['run'=>$run->id,'action'=>'approve','company_id'=>$companyId]) }}">@csrf<button type="submit">Approve</button></form>@elseif($run->status === 'approved' && $data['permissions']['post'])<form method="POST" action="{{ route('admin.finance.fx-revaluation.transition',['run'=>$run->id,'action'=>'post','company_id'=>$companyId]) }}">@csrf<button type="submit">Post</button></form>@elseif($run->status === 'posted' && $data['permissions']['post'])<form method="POST" action="{{ route('admin.finance.fx-revaluation.transition',['run'=>$run->id,'action'=>'reverse','company_id'=>$companyId]) }}">@csrf<button type="submit">Reverse</button></form>@else — @endif</td>
                </tr>
                @foreach($data['lines']->get($run->id, collect()) as $line)
                    <tr class="erp-evidence-row"><td>@if($line['source_url'])<a class="erp-source-link" href="{{ $line['source_url'] }}">{{ $line['reference'] }} ↗</a>@else{{ $line['reference'] }}@endif<small>{{ $line['source_type'] }} #{{ $line['source_id'] }}</small></td><td colspan="2">Outstanding {{ number_format($line['foreign_outstanding_minor'],0,',','.') }} {{ $line['transaction_currency'] }}</td><td colspan="2">Carrying {{ number_format($line['carrying_functional_minor'],0,',','.') }} → Closing {{ number_format($line['revalued_functional_minor'],0,',','.') }}</td><td colspan="3">Unrealized {{ number_format($line['fx_difference_minor'],0,',','.') }} {{ $run->functional_currency }}</td></tr>
                @endforeach
            @empty
                <tr><td colspan="8"><strong>NO FX REVALUATION RUNS</strong><br>No operating data available. Tạo run từ Customer/Supplier Invoice recognition + settlement evidence và closing rate hợp lệ.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $data['runs']->links() }}
    </section>
</div>
@endsection

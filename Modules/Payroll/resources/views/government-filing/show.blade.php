@extends('layouts.admin')
@section('title', 'Government Filing Detail')
@section('content')
@php($f = $vm->detail)
<nav class="breadcrumb"><a href="{{ route('admin.payroll.government-filings.index', ['company_id' => $c]) }}">Government Filings</a><i>/</i><span>{{ $f['annual_reference'] }} · v{{ $f['version'] }}</span></nav>
<section class="panel page-hero"><span class="eyebrow">{{ $f['jurisdiction'] }} · {{ $f['filing_type'] }}</span><h2>{{ $f['annual_reference'] }}</h2><p class="break-anywhere">Source checksum <code>{{ $f['source_checksum'] }}</code></p><div class="hero-actions">
@if ($f['status'] === 'draft')
    @can('government.filing.generate')
        <form method="POST" action="{{ route('admin.payroll.government-filings.mutate', ['filing' => $f['id'], 'action' => 'generate']) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Generate XML</button></form>
    @endcan
@endif
@if ($f['status'] === 'generated')
    @can('government.filing.validate')
        <form method="POST" action="{{ route('admin.payroll.government-filings.mutate', ['filing' => $f['id'], 'action' => 'validate']) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Validate</button></form>
    @endcan
@endif
@if ($f['status'] === 'validated')
    @can('government.filing.approve')
        <form method="POST" action="{{ route('admin.payroll.government-filings.mutate', ['filing' => $f['id'], 'action' => 'approve']) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Approve</button></form>
    @endcan
@endif
@if (in_array($f['status'], ['approved', 'rejected'], true))
    @can('government.filing.submit')
        <form method="POST" action="{{ route('admin.payroll.government-filings.mutate', ['filing' => $f['id'], 'action' => $f['status'] === 'rejected' ? 'retry' : 'submit']) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><button class="button">{{ $f['status'] === 'rejected' ? 'Retry local submission' : 'Submit locally' }}</button></form>
    @endcan
@endif
@if ($f['status'] === 'submitted')
    @can('government.filing.submit')
        <form method="POST" action="{{ route('admin.payroll.government-filings.mutate', ['filing' => $f['id'], 'action' => 'reject']) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><input name="reason" required maxlength="500" placeholder="Safe rejection reason"><button class="button secondary">Record rejection</button></form>
    @endcan
@endif
</div></section>
<section class="summary-grid"><article class="summary-card"><span>Status</span><strong>{{ $f['status'] }}</strong></article><article class="summary-card"><span>Tax year</span><strong>{{ $f['tax_year'] }}</strong></article><article class="summary-card"><span>Version</span><strong>v{{ $f['version'] }}</strong></article><article class="summary-card"><span>Annual result</span><strong>{{ $f['result_type'] ?? '—' }}</strong></article></section>
<section class="panel"><span class="eyebrow">Lifecycle</span><div class="state-track">@foreach(['draft','generated','validated','approved','submitted','accepted'] as $state)<div class="state-step {{ $state===$f['status']?'current':'' }}">{{ $state }}</div>@endforeach</div></section>
<section class="panel"><span class="eyebrow">Declaration artifact</span>@forelse($f['documents'] as $document)<div class="history-row"><div class="break-anywhere"><strong>{{ $document['file_name'] }}</strong><span>{{ $document['mime_type'] }} · {{ number_format((int)$document['file_size']) }} bytes · SHA-256 {{ $document['checksum'] }}</span></div>@can('government.filing.download')<a class="button secondary" href="{{ route('admin.payroll.government-filings.download',['filing'=>$f['id'],'document'=>$document['id'],'company_id'=>$c]) }}">Secure download</a>@endcan</div>@empty<p>Declaration has not been generated.</p>@endforelse</section>
<section class="operation-layout"><div class="operation-main"><section class="panel"><span class="eyebrow">Validation issues</span>@forelse($f['issues'] as $issue)<div class="issue-row"><strong>{{ $issue['issue_code'] }}</strong><span>{{ $issue['safe_message'] }}</span></div>@empty<p>No persisted validation issue.</p>@endforelse</section><section class="panel"><span class="eyebrow">Submission history</span>@forelse($f['attempts'] as $attempt)<div class="history-row"><strong>Attempt #{{ $attempt['attempt_number'] }} · {{ $attempt['status'] }}</strong><span>{{ $attempt['submitted_at'] }} · request {{ $attempt['request_hash'] }}</span></div>@empty<p>No submission attempt. No external request has been sent.</p>@endforelse</section></div><aside class="operation-side"><section class="panel"><span class="eyebrow">Lifecycle audit</span>@foreach($history->history as $log)<div class="history-row"><strong>{{ $log['action'] }}</strong><span>{{ $log['from_status'] ?? '∅' }} → {{ $log['to_status'] ?? '∅' }}</span></div>@endforeach</section><section class="panel"><span class="eyebrow">Access log</span>@forelse($f['access_logs'] as $access)<div class="history-row"><strong>{{ $access['action'] }}</strong><span>{{ $access['result'] }} · {{ $access['accessed_at'] }}</span></div>@empty<p>No document access.</p>@endforelse</section></aside></section>
@endsection
@push('styles')<style>.break-anywhere,.break-anywhere code{overflow-wrap:anywhere;word-break:break-word}.operation-main,.operation-side,.operation-main>*,.operation-side>*{min-width:0}.history-row{display:flex;justify-content:space-between;gap:1rem;min-width:0;padding:12px 0;border-bottom:1px solid var(--line)}.history-row>div,.history-row span{min-width:0;overflow-wrap:anywhere;word-break:break-word}.history-row span{display:block;color:#777;font-size:11px;margin-top:4px}@media(max-width:760px){.history-row{align-items:flex-start;flex-direction:column}}</style>@endpush

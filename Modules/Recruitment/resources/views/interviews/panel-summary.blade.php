@extends('layouts.admin')
@section('title','Interview Panel Summary')
@section('content')
<section class="panel" data-recruitment-page data-panel-summary data-company-id="{{ $companyId }}" data-interview-id="{{ $summary->interviewId }}" data-realtime-version="{{ $summary->interviewVersion }}" data-realtime-entity="interview" data-realtime-entity-id="{{ $summary->interviewId }}" data-refresh-url="{{ request()->fullUrl() }}">
<div class="page-heading"><div><span class="eyebrow">PANEL SUMMARY</span><h1>Interview #{{ $summary->interviewId }}</h1></div><span data-realtime-state>Đã đồng bộ</span><button type="button" data-realtime-refresh>Làm mới</button></div>
<div class="stats">
<article><span>Assigned</span><strong>{{ $summary->assignedCount }}</strong></article>
<article><span>Submitted</span><strong>{{ $summary->submittedCount }}</strong></article>
<article><span>Draft</span><strong>{{ $summary->draftCount }}</strong></article>
<article><span>Pending</span><strong>{{ $summary->pendingCount }}</strong></article>
<article><span>Completion</span><strong>{{ $summary->completionPercent }}%</strong></article>
<article><span>Average</span><strong>{{ $summary->averageScore ?? '—' }}</strong></article>
<article><span>Minimum</span><strong>{{ $summary->minimumScore ?? '—' }}</strong></article>
<article><span>Maximum</span><strong>{{ $summary->maximumScore ?? '—' }}</strong></article>
</div>
<h2>Recommendation distribution</h2>
<table><thead><tr><th>Recommendation</th><th>Count</th><th>Tỷ lệ</th></tr></thead><tbody>@forelse($summary->recommendations as $item)<tr><td>{{ $item->recommendation->value }}</td><td>{{ $item->count }}</td><td>{{ $item->percentage }}%</td></tr>@empty<tr><td colspan="3">Chưa có evaluation submitted.</td></tr>@endforelse</tbody></table>
<h2>Criterion averages</h2>
<table><thead><tr><th>Criterion</th><th>Normalized</th><th>Raw / Max</th><th>Weight</th><th>Responses</th></tr></thead><tbody>@foreach($summary->criteria as $criterion)<tr><td>{{ $criterion->label }}</td><td>{{ $criterion->averagePercent }}%</td><td>{{ $criterion->averageRawScore }} / {{ $criterion->maximumScore }}</td><td>{{ $criterion->weight }}%</td><td>{{ $criterion->responseCount }}</td></tr>@endforeach</tbody></table>
<div class="grid-two"><article><h2>Strengths</h2><ul>@forelse($summary->strengths as $value)<li>{{ $value }}</li>@empty<li>Chưa có dữ liệu.</li>@endforelse</ul></article>
<article><h2>Concerns</h2><ul>@forelse($summary->concerns as $value)<li>{{ $value }}</li>@empty<li>Chưa có dữ liệu.</li>@endforelse</ul></article></div>
<p>Last submitted: {{ $summary->lastSubmittedAt?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') ?? '—' }}</p>
</section>
@endsection

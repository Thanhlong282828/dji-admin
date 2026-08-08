@extends('layouts.admin')
@section('title','Payroll Employee Input Detail')
@section('content')
@php($d=$viewModel->detail)
<section class="panel"><span class="eyebrow">CAPTURED IDENTITY</span><h1>{{ $d['number'] }} · {{ $d['name'] }}</h1><p>{{ $d['period'] }} · Snapshot V{{ $d['snapshot_version'] }} · {{ $d['captured_at'] }}</p></section>
<section class="grid gap-3 md:grid-cols-4">@foreach(['scheduled'=>'Scheduled','worked'=>'Worked','paid_leave'=>'Paid Leave','unpaid_leave'=>'Unpaid Leave','weekday_ot'=>'Weekday OT','weekend_ot'=>'Rest-day OT','holiday_ot'=>'Holiday OT'] as $key=>$label)<article><small>{{$label}}</small><strong>{{$d[$key]}}</strong></article>@endforeach</section>
<section><h2>Source traceability</h2><table><thead><tr><th>Type</th><th>Reference</th><th>Date</th><th>Quantity</th><th>Classification</th><th>Status captured</th><th>Captured at</th></tr></thead><tbody>@forelse($viewModel->sources as $s)<tr><td>{{$s['type']}}</td><td>#{{$s['source_id']}}</td><td>{{$s['date']}}</td><td>{{$s['quantity']}}</td><td>{{$s['classification'] ?? '—'}}</td><td>{{$s['status']}}</td><td>{{$s['captured_at']}}</td></tr>@empty<tr><td colspan="7">Không có source record.</td></tr>@endforelse</tbody></table></section>
<section><h2>Validation issues</h2>@forelse($viewModel->issues as $i)<p>{{$i['code']}} — {{$i['message']}}</p>@empty<p>Không có issue.</p>@endforelse</section>
@endsection

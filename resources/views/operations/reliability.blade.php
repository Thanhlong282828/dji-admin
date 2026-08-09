@extends('layouts.admin')
@section('title', 'Enterprise Reliability')
@section('content')
<style>
.sre{--gold:#d4af37;color:#edf0f6;max-width:1480px;margin:auto}.sre *{box-sizing:border-box}.sre-hero,.sre-card,.sre-panel{background:linear-gradient(145deg,#151920,#090b0f);border:1px solid #2d3139;border-radius:20px;box-shadow:0 20px 55px #0005}.sre-hero{padding:28px}.sre-kicker,.sre-value{color:var(--gold)}.sre-kicker{text-transform:uppercase;letter-spacing:.16em;font-size:12px}.sre h1{font-size:clamp(30px,4vw,52px);margin:8px 0}.sre-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.sre-card{padding:20px;min-width:0}.sre-value{display:block;font-size:30px;margin-top:8px}.sre-panel{padding:22px;margin-top:18px;overflow:hidden}.sre-table{overflow-x:auto}.sre table{width:100%;min-width:680px;border-collapse:collapse}.sre th,.sre td{padding:12px;border-bottom:1px solid #292d34;text-align:left}.sre th{color:#d9bf67}.sre-badge{display:inline-flex;padding:6px 10px;border-radius:999px;background:#2a230e;color:#f4d978}.sre-safe{color:#9ad9b0}.sre-warn{color:#f3b59d}@media(max-width:760px){.sre-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sre-hero,.sre-panel{padding:16px}.sre-card{padding:15px}}@media(max-width:420px){.sre-grid{grid-template-columns:1fr}}
</style>
<main class="sre">
 <header class="sre-hero"><span class="sre-kicker">Reliability control plane</span><h1>Enterprise Reliability</h1><p>Safe operational signals only. No credentials, PII, payroll or business payloads are exposed.</p></header>
 <section class="sre-grid">
  <article class="sre-card"><span>System</span><strong class="sre-value">{{ strtoupper($snapshot['readiness']['status']) }}</strong></article>
  <article class="sre-card"><span>Queue backlog</span><strong class="sre-value">{{ $snapshot['queue']['pending'] }}</strong></article>
  <article class="sre-card"><span>Failed jobs</span><strong class="sre-value">{{ $snapshot['queue']['failed'] }}</strong></article>
  <article class="sre-card"><span>Error budget remaining</span><strong class="sre-value">{{ $snapshot['slo']['remaining_percent'] }}%</strong></article>
 </section>
 <section class="sre-panel"><h2>Dependencies</h2>@foreach($snapshot['readiness']['checks'] as $name=>$ready)<p>{{ ucfirst($name) }} <span class="{{ $ready?'sre-safe':'sre-warn' }}">{{ $ready?'READY':'UNAVAILABLE' }}</span></p>@endforeach<p>Scheduler <span class="sre-badge">{{ strtoupper($snapshot['scheduler']['status']) }}</span></p></section>
 <section class="sre-panel"><h2>Outbox backlog</h2><div class="sre-table"><table><thead><tr><th>Consumer</th><th>Pending</th><th>Failed</th><th>Oldest pending (seconds)</th></tr></thead><tbody>@foreach($snapshot['outboxes'] as $name=>$outbox)<tr><td>{{ ucfirst($name) }}</td><td>{{ $outbox['pending'] }}</td><td>{{ $outbox['failed'] }}</td><td>{{ $outbox['oldest_pending_seconds'] ?? '—' }}</td></tr>@endforeach</tbody></table></div></section>
 <section class="sre-panel"><h2>Integration resilience</h2><p>Failed messages: {{ $snapshot['integration']['failed'] }} · Open dead letters: {{ $snapshot['integration']['dead_letters'] }}</p><p>SLO window {{ $snapshot['slo']['window_minutes'] }} minutes · allowed failures {{ $snapshot['slo']['allowed_failures'] }} · consumed {{ $snapshot['slo']['consumed_percent'] }}%</p></section>
</main>
@endsection

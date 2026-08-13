@extends('layouts.admin')
@section('title', 'Enterprise Data Lifecycle')
@section('content')
<style>
.dl{--gold:#d4af37;color:#edf0f6;width:100%;max-width:1480px;min-width:0;margin:auto;overflow:hidden}.dl *{box-sizing:border-box}.dl-hero,.dl-card,.dl-panel{background:linear-gradient(145deg,#151920,#090b0f);border:1px solid #2d3139;border-radius:20px;box-shadow:0 20px 55px #0005}.dl-hero,.dl-panel{padding:24px}.dl-kicker,.dl-value,.dl th{color:var(--gold)}.dl-kicker{text-transform:uppercase;letter-spacing:.16em;font-size:12px}.dl h1{font-size:clamp(30px,4vw,52px);margin:8px 0}.dl p,.dl code{overflow-wrap:anywhere}.dl-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.dl-card{padding:18px;min-width:0}.dl-value{display:block;font-size:28px;margin-top:8px}.dl-panels{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:18px}.dl-table{width:100%;overflow-x:auto}.dl table{width:100%;min-width:620px;border-collapse:collapse}.dl th,.dl td{padding:12px;text-align:left;border-bottom:1px solid #292d34}.dl-safe{color:#9ad9b0}.dl-warn{color:#f3b59d}@media(max-width:900px){.dl-panels{grid-template-columns:1fr}}@media(max-width:760px){.dl-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dl-hero,.dl-panel{padding:16px}}@media(max-width:420px){.dl-grid{grid-template-columns:1fr}}
</style>
<main class="dl">
 <header class="dl-hero"><span class="dl-kicker">Read-only retention governance</span><h1>Enterprise Data Lifecycle</h1><p>Policy evaluation, legal-hold precedence, archive integrity and disposal eligibility without archive, purge, anonymization or business-row mutation.</p><code>Policy {{ $snapshot['policy_checksum'] }}</code></header>
 <h2>Policy summary</h2><section class="dl-grid">
  <article class="dl-card"><span>Policies / categories</span><strong class="dl-value">{{ $snapshot['policy_count'] }} / {{ count($snapshot['resource_categories']) }}</strong></article>
  <article class="dl-card"><span>Eligible / blocked</span><strong class="dl-value">{{ $snapshot['disposal_eligible'] }} / {{ $snapshot['blocked_disposal'] }}</strong></article>
  <article class="dl-card"><span>Legal holds</span><strong class="dl-value">{{ $snapshot['legal_holds'] }}</strong></article>
  <article class="dl-card"><span>Destructive switch</span><strong class="dl-value {{ $snapshot['destructive_disposal_enabled'] ? 'dl-warn' : 'dl-safe' }}">{{ $snapshot['destructive_disposal_enabled'] ? 'ENABLED' : 'DISABLED' }}</strong></article>
 </section>
 <section class="dl-panels">
  <article class="dl-panel"><h2>Lifecycle governance posture</h2><div class="dl-table"><table><tbody>
   <tr><th>Resource categories</th><td>{{ implode(', ', $snapshot['resource_categories']) }}</td></tr>
   <tr><th>Classification mapping</th><td>@foreach($snapshot['classification_mapping'] as $class => $count)<span>{{ strtoupper($class) }} {{ $count }}@if(! $loop->last) · @endif</span>@endforeach</td></tr>
   <tr><th>Approved retention periods</th><td>{{ $snapshot['configured_retention_periods'] }} (unset periods remain blocked)</td></tr>
   <tr><th>Archive integrity</th><td>{{ $snapshot['archive_integrity'] }} · artifacts {{ $snapshot['archive_artifact_count'] }}</td></tr>
   <tr><th>Orphan artifact findings</th><td>{{ $snapshot['orphan_artifact_findings'] }}</td></tr>
   <tr><th>Dry-run evaluator</th><td class="dl-safe">{{ $snapshot['dry_run_enabled'] ? 'ENABLED' : 'DISABLED' }} · read-only</td></tr>
  </tbody></table></div></article>
  <aside class="dl-panel"><h2>Open governance findings</h2>@foreach($snapshot['findings'] as $finding)<article class="dl-card"><strong class="{{ $finding['severity'] === 'warning' ? 'dl-warn' : 'dl-safe' }}">{{ strtoupper($finding['severity']) }} · {{ $finding['component'] }}</strong><p>{{ $finding['message'] }}</p></article>@endforeach</aside>
 </section>
</main>
@endsection

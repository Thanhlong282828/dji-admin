@extends('layouts.admin')
@section('title', 'Enterprise API Contracts')
@section('content')
<style>
.ac{--gold:#d4af37;color:#edf0f6;width:100%;max-width:1480px;min-width:0;margin:auto;overflow:hidden}.ac *{box-sizing:border-box}.ac-hero,.ac-card,.ac-panel{background:linear-gradient(145deg,#151920,#090b0f);border:1px solid #2d3139;border-radius:20px;box-shadow:0 20px 55px #0005;max-width:100%}.ac-hero{padding:28px}.ac-kicker,.ac-value{color:var(--gold)}.ac-kicker{text-transform:uppercase;letter-spacing:.16em;font-size:12px}.ac h1{font-size:clamp(30px,4vw,52px);margin:8px 0}.ac p{overflow-wrap:anywhere}.ac-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.ac-card{padding:20px;min-width:0}.ac-value{display:block;font-size:28px;margin-top:8px}.ac-panel{padding:22px;margin-top:18px;overflow:hidden}.ac-table{width:100%;overflow-x:auto}.ac table{width:100%;min-width:680px;border-collapse:collapse}.ac th,.ac td{padding:12px;border-bottom:1px solid #292d34;text-align:left;vertical-align:top}.ac th{color:#d9bf67}.ac-ok{color:#9ad9b0}.ac-warn{color:#f3b59d}@media(max-width:760px){.ac-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ac-hero,.ac-panel{padding:16px}.ac-card{padding:15px}}@media(max-width:420px){.ac-grid{grid-template-columns:1fr}}
</style>
<main class="ac">
 <header class="ac-hero"><span class="ac-kicker">Read-only compatibility governance</span><h1>Enterprise API Contracts</h1><p>Actual routes, OpenAPI coverage, stable operation identifiers, compatibility, tenant and security contracts. No request is executed from this page.</p></header>
 <section class="ac-grid">
  <article class="ac-card"><span>Actual operations</span><strong class="ac-value">{{ $snapshot['actual_operations'] }}</strong></article>
  <article class="ac-card"><span>Documented operations</span><strong class="ac-value">{{ $snapshot['documented_operations'] }}</strong></article>
  <article class="ac-card"><span>Undocumented / orphan</span><strong class="ac-value">{{ count($snapshot['undocumented_operations']) }} / {{ count($snapshot['orphan_operations']) }}</strong></article>
  <article class="ac-card"><span>Breaking findings</span><strong class="ac-value">{{ count($snapshot['breaking_findings']) }}</strong></article>
 </section>
 <section class="ac-panel"><h2>Contract posture</h2><div class="ac-table"><table><tbody>
  <tr><th>Status</th><td class="{{ $snapshot['status']==='pass'?'ac-ok':'ac-warn' }}">{{ strtoupper($snapshot['status']) }}</td></tr>
  <tr><th>Security</th><td>{{ $snapshot['security_scheme'] }}</td></tr>
  <tr><th>Versioning</th><td>{{ $snapshot['versioning_policy'] }}</td></tr>
  <tr><th>Deprecated operations</th><td>{{ $snapshot['deprecations'] }}</td></tr>
  <tr><th>Validation mismatches</th><td>{{ count($snapshot['validation_mismatches']) }}</td></tr>
  <tr><th>Tenant / pagination findings</th><td>{{ count($snapshot['tenant_findings']) }} / {{ count($snapshot['pagination_findings']) }}</td></tr>
  <tr><th>Sanitized checksum</th><td>{{ $snapshot['checksum'] }}</td></tr>
 </tbody></table></div></section>
 <section class="ac-panel"><h2>Open findings</h2>@forelse($snapshot['findings'] as $finding)<p class="ac-warn">{{ $finding['key'] }} · {{ $finding['category'] }} · {{ $finding['description'] }} — {{ $finding['evidence'] }}</p>@empty<p class="ac-ok">No governed route, compatibility, security, tenant, validation, pagination or privacy gap detected.</p>@endforelse</section>
</main>
@endsection

@extends('layouts.admin')
@section('title','HR Governance Audit')
@section('content')
<style>.audit{display:grid;gap:12px}.event{border-left:3px solid #d4af37;background:#101010;border-radius:0 12px 12px 0;padding:14px;overflow-wrap:anywhere}.event strong{color:#e7c65b}</style>
<h1>HR Governance Audit Timeline</h1><div class="audit">@forelse($viewModel->audits as $event)<article class="event"><strong>{{ $event['action'] }}</strong><div>{{ $event['entity_type'] }} #{{ $event['entity_id'] }} · actor #{{ $event['actor_id'] }}</div><small>{{ $event['created_at'] }} · checksum {{ substr((string)$event['checksum'],0,12) }}…</small></article>@empty<p>Chưa có audit event.</p>@endforelse</div>@endsection

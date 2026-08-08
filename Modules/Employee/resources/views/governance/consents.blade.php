@extends('layouts.admin')
@section('title','Consent Governance')
@section('content')
<h1>Consent Governance</h1><div style="overflow-x:auto"><table style="width:100%;min-width:720px"><thead><tr><th>Employee</th><th>Purpose</th><th>Policy version</th><th>Granted</th><th>Decision</th><th>Expiry</th></tr></thead><tbody>@forelse($viewModel->consents as $item)<tr><td>#{{ $item['employee_id'] }}</td><td>{{ $item['purpose'] }}</td><td>{{ $item['policy_version'] }}</td><td>{{ $item['granted'] ? 'Yes' : 'Withdrawn' }}</td><td>{{ $item['decided_at'] }}</td><td>{{ $item['expires_at'] ?? '—' }}</td></tr>@empty<tr><td colspan="6">Chưa có consent history.</td></tr>@endforelse</tbody></table></div>
@endsection

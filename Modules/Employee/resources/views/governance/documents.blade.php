@extends('layouts.admin')
@section('title','Document Governance')
@section('content')
<h2>Document Governance</h2><div style="overflow-x:auto"><table style="width:100%;min-width:720px"><thead><tr><th>Employee</th><th>Type</th><th>Name</th><th>Version</th><th>Scan</th><th>Expiry</th><th>Archived</th></tr></thead><tbody>@forelse($viewModel->documents as $item)<tr><td>#{{ $item['employee_id'] }}</td><td>{{ $item['type'] }}</td><td>{{ $item['name'] }}</td><td>{{ $item['version'] }}</td><td>{{ $item['scan_status'] }}</td><td>{{ $item['expires_at'] ?? '—' }}</td><td>{{ $item['archived_at'] ?? '—' }}</td></tr>@empty<tr><td colspan="7">Chưa có document metadata.</td></tr>@endforelse</tbody></table></div>
@endsection

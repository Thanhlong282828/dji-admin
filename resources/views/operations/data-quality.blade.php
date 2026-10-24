@extends('layouts.admin')
@section('title', 'Data Quality')
@section('content')
<section class="panel">
    <span class="eyebrow">DATA RECONCILIATION · READ ONLY</span>
    <h1>Enterprise Data Quality</h1>
    <p>Đối soát tổng hợp, không hiển thị PII và không tự động sửa dữ liệu.</p>
    <p><strong>Environment:</strong> {{ $snapshot['environment'] }} · <strong>Configured database:</strong> {{ $snapshot['configured_database'] }} · <strong>Actual PDO database:</strong> {{ $snapshot['actual_database'] }} · <strong>Purpose:</strong> {{ $snapshot['database_purpose'] }}</p>
    <div class="metric-grid">
        <article><small>Quality score</small><strong>{{ $snapshot['score'] }}%</strong></article>
        <article><small>Rules</small><strong>{{ $snapshot['rules'] }}</strong></article>
        <article><small>Critical findings</small><strong>{{ $snapshot['failed'] }}</strong></article>
        <article><small>Warnings</small><strong>{{ $snapshot['warnings'] }}</strong></article>
        <article><small>Orphans</small><strong>{{ $snapshot['orphans'] }}</strong></article>
        <article><small>Cross-tenant</small><strong>{{ $snapshot['cross_tenant'] }}</strong></article>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Component</th><th>Rule</th><th>Severity</th><th>Finding</th><th>Count</th><th>Status</th></tr></thead><tbody>
    @foreach($snapshot['findings'] as $finding)<tr><td>{{ $finding['component'] }}</td><td><code>{{ $finding['key'] }}</code></td><td>{{ $finding['severity'] }}</td><td>{{ $finding['description'] }}</td><td>{{ $finding['count'] }}</td><td>{{ $finding['status'] }}</td></tr>@endforeach
    </tbody></table></div>
    <p><small>Snapshot checksum: <code>{{ $snapshot['checksum'] }}</code></small></p>
</section>
@endsection

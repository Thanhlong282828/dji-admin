@extends('layouts.admin')

@section('header_action')
    @if ($vm->detail['status'] === 'exported')
        <a class="button" href="{{ route('admin.payroll.erp.exports.download', ['export' => $vm->detail['id'], 'company_id' => $c]) }}">
            Download verified CSV
        </a>
    @endif
@endsection

@section('content')
    @php($e = $vm->detail)

    <nav class="breadcrumb">
        <a href="{{ route('admin.payroll.erp.exports.index', ['company_id' => $c]) }}">ERP Exports</a>
        <i>/</i>
        <span>{{ $e['journal_number'] }}</span>
    </nav>

    <section class="panel page-hero">
        <span class="eyebrow">{{ $e['provider'] }} · export v{{ $e['export_version'] }}</span>
        <h2>{{ $e['journal_number'] }}</h2>
        <p class="break-anywhere">Payload checksum <code>{{ $e['payload_checksum'] }}</code></p>
        <div class="hero-actions">
            @if ($e['status'] === 'draft')
                @can('erp.integration.export.validate')
                    <form method="POST" action="{{ route('admin.payroll.erp.exports.mutate', ['export' => $e['id'], 'action' => 'validate']) }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $c }}">
                        <button class="button">Validate</button>
                    </form>
                @endcan
            @endif
            @if (in_array($e['status'], ['validated', 'failed'], true))
                @can('erp.integration.export.execute')
                    <form method="POST" action="{{ route('admin.payroll.erp.exports.mutate', ['export' => $e['id'], 'action' => $e['status'] === 'failed' ? 'retry' : 'execute']) }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $c }}">
                        <button class="button">{{ $e['status'] === 'failed' ? 'Retry' : 'Execute generic export' }}</button>
                    </form>
                @endcan
            @endif
            @if ($e['status'] === 'exported')
                @can('erp.integration.export.reverse')
                    <form method="POST" action="{{ route('admin.payroll.erp.exports.mutate', ['export' => $e['id'], 'action' => 'reverse']) }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $c }}">
                        <button class="button secondary">Reverse intent</button>
                    </form>
                @endcan
            @endif
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card"><span>Status</span><strong>{{ $e['status'] }}</strong></article>
        <article class="metric-card"><span>Connection</span><strong>{{ $e['connection_name'] }}</strong></article>
        <article class="metric-card"><span>Artifact</span><strong>{{ $e['artifact_name'] ?? 'Not generated' }}</strong></article>
    </section>

    <section class="panel">
        <span class="eyebrow">Artifact integrity</span>
        <div class="responsive-detail-grid">
            <div><small>File</small><strong>{{ $e['artifact_name'] ?? 'Not generated' }}</strong></div>
            <div><small>MIME</small><strong>{{ $e['artifact_mime'] ?? '—' }}</strong></div>
            <div><small>Size</small><strong>{{ isset($e['artifact_size']) ? number_format((int) $e['artifact_size']).' bytes' : '—' }}</strong></div>
            <div class="break-anywhere"><small>SHA-256</small><code>{{ $e['artifact_checksum'] ?? '—' }}</code></div>
        </div>
    </section>

    <section class="table-shell">
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Line</th><th>Source account</th><th>External account</th><th>Description</th><th>Debit</th><th>Credit</th></tr></thead>
                <tbody>
                    @foreach ($e['items'] as $i)
                        <tr><td>{{ $i['line_number'] }}</td><td>{{ $i['source_account_code'] }}</td><td>{{ $i['external_account_code'] }}</td><td>{{ $i['description'] }}</td><td class="money">{{ number_format($i['debit_minor']) }}</td><td class="money">{{ number_format($i['credit_minor']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <span class="eyebrow">Immutable lifecycle history</span>
        @forelse ($logs->logs as $log)
            <div class="history-row"><strong>{{ $log['action'] }}</strong><span>{{ $log['from_status'] ?? '∅' }} → {{ $log['to_status'] ?? '∅' }} · {{ $log['created_at'] }}</span></div>
        @empty
            <p>Chưa có history.</p>
        @endforelse
    </section>

    <section class="panel">
        <span class="eyebrow">Secure artifact access log</span>
        @forelse ($e['access_logs'] as $access)
            <div class="history-row"><strong>{{ $access['action'] }}</strong><span>{{ $access['result'] }} · actor #{{ $access['actor_id'] }} · {{ $access['accessed_at'] }}</span></div>
        @empty
            <p>Chưa có lượt tải artifact.</p>
        @endforelse
    </section>
@endsection

@push('styles')
    <style>
        .responsive-detail-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-top:1rem }
        .responsive-detail-grid div { min-width:0; display:grid; gap:.35rem }
        .break-anywhere, .break-anywhere code { overflow-wrap:anywhere; word-break:break-word }
    </style>
@endpush

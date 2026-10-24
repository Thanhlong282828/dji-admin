@extends('layouts.admin')

@section('title', 'Workflow audit trail')

@section('content')
    @include('workflow::partials.styles')
    <div class="wf-shell">
        <div class="wf-heading">
            <div>
                <p class="wf-kicker">Governance / immutable history</p>
                <h2>Workflow audit trail</h2>
                <p>Instance #{{ $vm->instances[0]['id'] }} · {{ $vm->instances[0]['workflow_type'] }}</p>
            </div>
            <a class="wf-button wf-button--ghost" href="{{ route('admin.workflows.instances.show', ['instance' => $vm->instances[0]['id'], 'company_id' => $companyId]) }}">Back to instance</a>
        </div>

        <section class="wf-panel">
            <div class="wf-panel__title"><h2>Append-only events</h2><span>{{ count($vm->history) }} events</span></div>
            <div class="wf-table-wrap">
                <table class="wf-table">
                    <thead><tr><th>Event</th><th>Actor</th><th>Transition</th><th>Recorded at</th></tr></thead>
                    <tbody>
                    @forelse ($vm->history as $event)
                        <tr>
                            <td>{{ $event['event'] }}</td>
                            <td>{{ $event['actor_id'] ?? 'System' }}</td>
                            <td>{{ $event['from_status'] ?? '—' }} → {{ $event['to_status'] ?? '—' }}</td>
                            <td>{{ $event['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No audit events have been recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

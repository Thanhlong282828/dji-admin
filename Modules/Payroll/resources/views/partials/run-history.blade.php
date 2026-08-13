<section
    data-workforce-region
    data-refresh-scope="payroll-run-history"
    data-refresh-url="{{ route('admin.payroll.runs.fragments.show', [
        'run' => $viewModel->summary['id'],
        'fragment' => 'history',
        'company_id' => $companyId,
    ]) }}"
    data-company-id="{{ $companyId }}"
    data-entity-id="{{ $viewModel->summary['id'] }}"
    data-record-version="{{ $viewModel->summary['version'] }}"
    data-calculation-version="{{ $viewModel->summary['calculation_version'] }}"
>

    {{-- ================================================================
        PAYROLL RUN HISTORY
    ================================================================ --}}
    <h2 class="text-xl">
        History
    </h2>

    @forelse($viewModel->history as $entry)
        <p class="mt-2 text-sm text-zinc-400">
            {{ $entry['created_at'] }}
            ·
            {{ $entry['action'] }}
            ·
            {{ $entry['actor'] }}
            ·
            {{ $entry['from_status'] ?? '—' }}
            →
            {{ $entry['to_status'] }}
            ·
            record v{{ $entry['version_before'] }}
            →
            v{{ $entry['version_after'] }}
            ·
            calculation V{{ $entry['calculation_version'] }}

            @if($entry['reason'])
                · {{ $entry['reason'] }}
            @endif
        </p>
    @empty
        <p class="mt-2 text-zinc-500">
            Chưa có lịch sử.
        </p>
    @endforelse

</section>
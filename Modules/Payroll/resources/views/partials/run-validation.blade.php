<section
    data-workforce-region
    data-refresh-scope="payroll-validation-issues"
    data-refresh-url="{{ route('admin.payroll.runs.fragments.show', [
        'run' => $viewModel->summary['id'],
        'fragment' => 'validation',
        'company_id' => $companyId,
    ]) }}"
    data-company-id="{{ $companyId }}"
    data-entity-id="{{ $viewModel->summary['id'] }}"
    data-record-version="{{ $viewModel->summary['version'] }}"
    data-calculation-version="{{ $viewModel->summary['calculation_version'] }}"
>

    {{-- ================================================================
        VALIDATION ISSUES
    ================================================================ --}}
    <h2 class="text-xl">
        Validation Issues
    </h2>

    @forelse($viewModel->issues as $issue)
        <p class="mt-2 rounded bg-red-950 p-3">
            {{ $issue['code'] }}
            ·
            {{ $issue['message'] }}
        </p>
    @empty
        <p class="mt-2 text-zinc-500">
            Không có lỗi validation.
        </p>
    @endforelse

</section>
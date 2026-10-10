@extends('layouts.admin')

@section('title', 'Payroll Runs')

@section('header_action')
    <a
        class="button"
        href="{{ route('admin.payroll.inputs.index', [
            'company_id' => $companyId,
        ]) }}"
    >
        Payroll Inputs
    </a>
@endsection

@section('content')
    <div class="erp-title-with-help"><h2>Payroll Runs</h2><x-erp.process-help :title="$processHelp['title']" :steps="$processHelp['steps']" :route="route('admin.payroll.runs.index',['company_id'=>$companyId])" :description="$processHelp['description']" :owner="$processHelp['owner']" :accounting-impact="$processHelp['accounting_impact']" /></div>
    <div
        class="space-y-6"
        data-workforce-page
        data-refresh-scope="payroll-run-list"
    >

        {{-- ============================================================
            PAGE HEADER
        ============================================================ --}}
        <section class="panel">
            <span class="eyebrow">
                PAYROLL EXECUTION
            </span>

            <h2>
                Payroll Runs
            </h2>

            <p>
                Foundation component totals only.
                Tax, statutory insurance, payslip and payment are not calculated.
            </p>
                <i>/</i>

         <span>
            Payroll/resources/views/runs/index
        </span>

        </section>


        {{-- ============================================================
            SUCCESS MESSAGE
        ============================================================ --}}
        @if(session('success'))
            <div class="notice">
                {{ session('success') }}
            </div>
        @endif


        {{-- ============================================================
            FILTERS
        ============================================================ --}}
        <form
            class="panel grid gap-3 md:grid-cols-5"
            method="GET"
        >
            <input
                type="hidden"
                name="company_id"
                value="{{ $companyId }}"
            >

            <input
                name="search"
                placeholder="Run number"
                value="{{ $filters['search'] }}"
            >

            <select name="status">
                <option value="">
                    All statuses
                </option>

                @foreach([
                    'draft',
                    'calculating',
                    'calculated',
                    'validation_failed',
                    'submitted',
                    'approved',
                    'rejected',
                    'finalized',
                    'failed',
                    'cancelled',
                ] as $status)
                    <option
                        value="{{ $status }}"
                        @selected($filters['status'] === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>

            <input
                type="number"
                name="period_id"
                placeholder="Period ID"
                value="{{ $filters['period_id'] ?: '' }}"
            >

            <input
                type="date"
                name="date_from"
                value="{{ $filters['date_from'] }}"
            >

            <button class="button">
                Filter
            </button>
        </form>


        {{-- ============================================================
            PAYROLL RUN TABLE
        ============================================================ --}}
        <section class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Period</th>
                        <th>Snapshot</th>
                        <th>Status</th>
                        <th>Employees</th>
                        <th>Foundation Gross</th>
                        <th>Foundation Deductions</th>
                        <th>Foundation Net</th>
                        <th>Calc V</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($viewModel->runs as $run)
                        <tr>
                            <td>
                                {{ $run['run_number'] }}
                            </td>

                            <td>
                                {{ $run['period'] }}
                            </td>

                            <td>
                                V{{ $run['snapshot_version'] }}
                            </td>

                            <td>
                                <strong>
                                    {{ strtoupper($run['status']) }}
                                </strong>
                            </td>

                            <td>
                                {{ $run['employee_count'] }}
                            </td>

                            <td>
                                {{ number_format($run['gross_minor']) }}
                                {{ $run['currency'] }}
                            </td>

                            <td>
                                {{ number_format($run['deduction_minor']) }}
                            </td>

                            <td>
                                {{ number_format($run['net_minor']) }}
                            </td>

                            <td>
                                {{ $run['calculation_version'] }}
                            </td>

                            <td>
                                {{ $run['created_at'] }}
                            </td>

                            <td>
                                <a
                                    class="text-amber-500"
                                    href="{{ route('admin.payroll.runs.show', [
                                        'run' => $run['id'],
                                        'company_id' => $companyId,
                                    ]) }}"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="11"
                                class="p-10 text-center"
                            >
                                <strong>
                                    Chưa có Payroll Run.
                                </strong>

                                <p>
                                    Hãy tạo Run từ một Payroll Input Snapshot READY.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>


        {{-- ============================================================
            PAGINATION
        ============================================================ --}}
        <nav class="flex justify-between">
            @if($viewModel->page > 1)
                <a
                    class="button"
                    href="{{ request()->fullUrlWithQuery([
                        'page' => $viewModel->page - 1,
                    ]) }}"
                >
                    ← Previous
                </a>
            @else
                <span></span>
            @endif

            @if($viewModel->hasMore)
                <a
                    class="button"
                    href="{{ request()->fullUrlWithQuery([
                        'page' => $viewModel->page + 1,
                    ]) }}"
                >
                    Next →
                </a>
            @endif
        </nav>

    </div>
@endsection

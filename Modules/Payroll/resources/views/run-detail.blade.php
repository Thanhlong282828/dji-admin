@extends('layouts.admin')

@section('title', 'Payroll Run | Runs')

@section('content')
    <div class="space-y-7">

        {{-- ============================================================
            PAYSLIPS
        ============================================================ --}}
        @if($viewModel->summary['status'] === 'finalized')
            <section class="panel">
                <h2>
                    Payslips
                </h2>

                <p>
                    Generate missing official Payslips asynchronously
                    from immutable Payroll Items.
                </p>

                @can('payroll.payslip.generate')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.payslips.generate-run', [
                            'run' => $viewModel->summary['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <input
                            type="hidden"
                            name="idempotency_key"
                            value="{{ (string) Illuminate\Support\Str::uuid() }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Generate Missing Payslips
                        </button>
                    </form>
                @endcan

                <a
                    class="text-amber-500"
                    href="{{ route('admin.payroll.payslips.index', [
                        'company_id' => $companyId,
                        'run' => $viewModel->summary['id'],
                    ]) }}"
                >
                    View Payslips
                </a>
            </section>
        @endif


        {{-- ============================================================
            PAYROLL RUN DETAIL
        ============================================================ --}}
        <div
            class="space-y-7"
            data-workforce-page
            data-company-id="{{ $companyId }}"
            data-entity-id="{{ $viewModel->summary['id'] }}"
        >

            {{-- Back --}}
            <a
                class="text-amber-500"
                href="{{ route('admin.payroll.delivery.index', [
                    'company_id' => $companyId,
                ]) }}"
            >
                ← Payroll Delivery
            </a>


            {{-- ========================================================
                RUN SUMMARY
            ======================================================== --}}
            @include('payroll::partials.run-summary')


            {{-- ========================================================
                RUN READINESS
            ======================================================== --}}
            @include('payroll::partials.run-readiness')


            {{-- ========================================================
                RUN ITEMS
            ======================================================== --}}
            @include('payroll::partials.run-items')


            {{-- ========================================================
                VALIDATION
            ======================================================== --}}
            @include('payroll::partials.run-validation')


            {{-- ========================================================
                HISTORY
            ======================================================== --}}
            @include('payroll::partials.run-history')


            {{-- ========================================================
                ACTIONS
            ======================================================== --}}
            @include('payroll::partials.run-actions')

        </div>

    </div>
@endsection
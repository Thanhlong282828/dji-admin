@extends('layouts.admin')

@section('title', 'Annual PIT Reconciliation')

@section('header_action')
    @can('payroll.annual_tax.create')
        <a
            class="button"
            href="{{ route('admin.payroll.annual-tax.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Annual reconciliation
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            EMPLOYER-SCOPED ANNUAL PIT
        </span>

        <h1>
            Annual Tax Reconciliation
        </h1>

        <p>
            Internal employer reconciliation only.
            External employer income and government filing are not included.
        </p>
               <i>/</i>

         <span>
            Payroll/resources/views/annual-tax/index
        </span>
    </section>


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="GET"
    >
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- Tax Year --}}
        <label>
            Tax year

            <input
                name="tax_year"
                value="{{ request('tax_year') }}"
            >
        </label>


        {{-- Status --}}
        <label>
            Status

            <input
                name="status"
                value="{{ request('status') }}"
            >
        </label>


        {{-- Result --}}
        <label>
            Result

            <input
                name="result_type"
                value="{{ request('result_type') }}"
            >
        </label>


        {{-- Submit --}}
        <button
            class="button"
            type="submit"
        >
            Filter
        </button>
    </form>


    {{-- ================================================================
        ANNUAL TAX RECONCILIATIONS TABLE
    ================================================================ --}}
    <section class="table-shell">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Employee</th>
                    <th>Year</th>
                    <th>Status</th>
                    <th>Result</th>
                    <th>Liability</th>
                    <th>Withheld</th>
                    <th>Settlement</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($data['rows'] as $a)
                    <tr>

                        {{-- Reference --}}
                        <td>
                            {{ $a['reference'] }}
                            ·
                            V{{ $a['version'] }}
                        </td>


                        {{-- Employee --}}
                        <td>
                            {{ $a['employee_code'] }}
                            ·
                            {{ $a['full_name'] }}
                        </td>


                        {{-- Year / Jurisdiction --}}
                        <td>
                            {{ $a['tax_year'] }}
                            /
                            {{ $a['jurisdiction'] }}
                        </td>


                        {{-- Status --}}
                        <td>
                            <span class="status-pill">
                                {{ $a['status'] }}
                            </span>
                        </td>


                        {{-- Result --}}
                        <td>
                            {{ $a['result_type'] ?? '—' }}
                        </td>


                        {{-- Final Tax Liability --}}
                        <td>
                            {{ number_format($a['final_tax_liability_minor']) }}
                            {{ $a['currency'] }}
                        </td>


                        {{-- PIT Withheld --}}
                        <td>
                            {{ number_format($a['pit_withheld_minor']) }}
                        </td>


                        {{-- Settlement --}}
                        <td>
                            {{
                                number_format(
                                    $a['additional_tax_payable_minor']
                                        ?: -$a['tax_refund_minor']
                                )
                            }}
                        </td>


                        {{-- Actions --}}
                        <td>
                            <a
                                href="{{ route('admin.payroll.annual-tax.show', [
                                    'reconciliation' => $a['id'],
                                    'company_id' => $c,
                                ]) }}"
                            >
                                Detail →
                            </a>
                        </td>

                    </tr>

                @empty
                    <tr>
                        <td
                            colspan="9"
                            class="p-8 text-center"
                        >
                            No Annual Tax reconciliation exists.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

@endsection
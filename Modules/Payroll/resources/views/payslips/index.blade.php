@extends('layouts.admin')

@section('title', 'Payslips')

@section('content')

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            SECURE PAYROLL DOCUMENTS
        </span>

        <h1>
            Payslips
        </h1>
<i>/</i>

         <span>
            Payroll/resources/views/payslips/index
        </span>
        <p>
            Official Payslips are generated only from finalized Payroll Runs
            and stored on private storage.
        </p>
    </section>


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form
        class="grid gap-3 md:grid-cols-4"
        method="GET"
    >
        {{-- Company Context --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- Search --}}
        <input
            name="search"
            value="{{ $f['search'] }}"
            placeholder="Employee number/name"
        >


        {{-- Status --}}
        <select name="status">
            <option value="">
                All statuses
            </option>

            @foreach([
                'pending',
                'generating',
                'generated',
                'published',
                'revoked',
            ] as $s)
                <option
                    value="{{ $s }}"
                    @selected($f['status'] === $s)
                >
                    {{ $s }}
                </option>
            @endforeach
        </select>


        {{-- Payroll Run --}}
        <input
            type="number"
            name="run"
            value="{{ $f['run'] ?: '' }}"
            placeholder="Run ID"
        >


        {{-- Submit --}}
        <button
            class="button"
            type="submit"
        >
            Filter
        </button>
    </form>


    {{-- ================================================================
        PAYSLIPS TABLE
    ================================================================ --}}
    <section class="overflow-x-auto rounded-xl border border-zinc-800">
        <table class="w-full text-left">

            <thead>
                <tr>
                    <th>Payslip</th>
                    <th>Employee</th>
                    <th>Period</th>
                    <th>Run</th>
                    <th>Versions</th>
                    <th>Status</th>
                    <th>Generated</th>
                    <th>Delivery</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($vm->items as $p)
                    <tr>

                        {{-- Payslip UUID --}}
                        <td>
                            {{ substr($p['uuid'], 0, 8) }}
                        </td>


                        {{-- Employee --}}
                        <td>
                            {{ $p['employee_number'] }}
                            ·
                            {{ $p['employee_name'] }}
                        </td>


                        {{-- Payroll Period --}}
                        <td>
                            {{ $p['period'] }}
                        </td>


                        {{-- Payroll Run --}}
                        <td>
                            {{ $p['run_number'] }}
                        </td>


                        {{-- Versions --}}
                        <td>
                            Calc {{ $p['calculation_version'] }}
                            /
                            Doc {{ $p['payslip_version'] }}
                        </td>


                        {{-- Status --}}
                        <td>
                            <span class="status-pill is-{{ $p['status'] }}">
                                {{ $p['status'] }}
                            </span>
                        </td>


                        {{-- Generated At --}}
                        <td>
                            {{ $p['generated_at'] ?? '—' }}
                        </td>


                        {{-- Delivery Status --}}
                        <td>
                            <span class="status-pill is-{{ $p['delivery_status'] }}">
                                {{ $p['delivery_status'] }}
                            </span>
                        </td>


                        {{-- Actions --}}
                        <td>
                            <a
                                class="text-amber-500"
                                href="{{ route('admin.payroll.payslips.show', [
                                    'payslip' => $p['id'],
                                    'company_id' => $c,
                                ]) }}"
                            >
                                View
                            </a>
                        </td>

                    </tr>

                @empty
                    <tr>
                        <td
                            colspan="9"
                            class="p-8 text-center"
                        >
                            Chưa có Payslip.
                            Generate từ finalized Payroll Run.
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>
    </section>

@endsection
@extends('layouts.admin')

@section('title', 'Employee Tax Profiles')

@section('content')

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            TAX PROFILE
        </span>

        <h1>
            Employee Tax Profiles
        </h1>

        <p>
            Tax ID luôn được mask;
            profile resolve theo effective date.
        </p>
          <i>/</i>

         <span>
            Payroll/resources/views/tax/employees/index
        </span>
    </section>


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form
        class="grid gap-3 md:grid-cols-4"
        method="GET"
    >
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >

        <input
            name="search"
            value="{{ $f['search'] }}"
            placeholder="Employee"
        >

        <select name="status">
            <option value="">
                All statuses
            </option>

            @foreach([
                'taxable',
                'exempt',
                'not_subject',
                'suspended',
            ] as $s)
                <option
                    value="{{ $s }}"
                    @selected($f['status'] === $s)
                >
                    {{ $s }}
                </option>
            @endforeach
        </select>

        <select name="residency">
            <option value="">
                All residency
            </option>

            @foreach([
                'resident',
                'non_resident',
                'unknown',
            ] as $s)
                <option
                    value="{{ $s }}"
                    @selected($f['residency'] === $s)
                >
                    {{ $s }}
                </option>
            @endforeach
        </select>

        <button
            class="button"
            type="submit"
        >
            Filter
        </button>
    </form>


    {{-- ================================================================
        EMPLOYEE TAX PROFILES TABLE
    ================================================================ --}}
    <section class="overflow-x-auto rounded-xl border border-zinc-800">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Status</th>
                    <th>Residency</th>
                    <th>Tax number</th>
                    <th>Policy</th>
                    <th>Effective</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($vm->items as $e)
                    <tr>

                        {{-- Employee --}}
                        <td>
                            {{ $e['number'] }}
                            ·
                            {{ $e['name'] }}
                        </td>


                        {{-- Status --}}
                        <td>
                            {{ $e['status'] ?? 'Not configured' }}
                        </td>


                        {{-- Residency --}}
                        <td>
                            {{ $e['residency'] ?? '—' }}
                        </td>


                        {{-- Tax Number --}}
                        <td>
                            {{ $e['tax_number'] ?? '—' }}
                        </td>


                        {{-- Policy --}}
                        <td>
                            {{ $e['policy'] ?? '—' }}
                        </td>


                        {{-- Effective Period --}}
                        <td>
                            {{ $e['effective_from'] ?? '—' }}
                            →
                            {{ $e['effective_to'] ?? '∞' }}
                        </td>


                        {{-- Actions --}}
                        <td>
                            <a
                                class="text-amber-500"
                                href="{{ route('admin.payroll.tax.employees.show', [
                                    'employee' => $e['id'],
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
                            colspan="7"
                            class="p-8 text-center"
                        >
                            Chưa có Employee.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

@endsection
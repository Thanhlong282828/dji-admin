@extends('layouts.admin')

@section('title', 'Employee Insurance Profiles')

@section('content')

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            EMPLOYEE INSURANCE
        </span>

        <h1>
            Employee Insurance Profiles
        </h1>
    </section>


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form method="GET">
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
                All
            </option>

            @foreach([
                'participating',
                'not_participating',
                'exempt',
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

        <button type="submit">
            Filter
        </button>
    </form>


    {{-- ================================================================
        EMPLOYEE INSURANCE PROFILES
    ================================================================ --}}
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Status</th>
                <th>Insurance No.</th>
                <th>Policy</th>
                <th>Effective</th>
                <th>Override</th>
            </tr>
        </thead>

        <tbody>
            @foreach($vm->items as $e)
                <tr>

                    {{-- Employee --}}
                    <td>
                        <a
                            href="{{ route('admin.payroll.insurance.employees.show', [
                                'employee' => $e['id'],
                                'company_id' => $c,
                            ]) }}"
                        >
                            {{ $e['number'] }}
                            ·
                            {{ $e['name'] }}
                        </a>
                    </td>


                    {{-- Status --}}
                    <td>
                        {{ $e['status'] ?? 'missing' }}
                    </td>


                    {{-- Insurance Number --}}
                    <td>
                        {{ $e['insurance_number'] ?? '—' }}
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


                    {{-- Override --}}
                    <td>
                        {{ $e['override'] ?? '—' }}
                    </td>

                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
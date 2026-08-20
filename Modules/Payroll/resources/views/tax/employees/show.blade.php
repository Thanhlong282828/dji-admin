@extends('layouts.admin')

@section('title', 'Employee Tax Profile')

@section('content')
    @php
        $e = $vm->detail;
    @endphp

    {{-- ================================================================
        EMPLOYEE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            EMPLOYEE TAX PROFILE
        </span>
     <i>/</i>

         <span>
            Payroll/resources/views/tax/employees/show
        </span>

        <h1>
            {{ $e['number'] }}
            ·
            {{ $e['name'] }}
        </h1>

        <p>
            Eligible dependents:
            {{ $e['dependent_count'] }}
        </p>
    </section>


    {{-- ================================================================
        TAX PROFILES
    ================================================================ --}}
    <section class="overflow-x-auto rounded-xl border border-zinc-800">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Residency</th>
                    <th>Policy</th>
                    <th>Effective</th>
                    <th>Tax Number</th>
                    <th>Personal deduction</th>
                </tr>
            </thead>

            <tbody>
                @forelse($e['profiles'] as $p)
                    <tr>

                        {{-- Status --}}
                        <td>
                            {{ $p['status'] }}
                        </td>


                        {{-- Residency --}}
                        <td>
                            {{ $p['residency'] }}
                        </td>


                        {{-- Policy --}}
                        <td>
                            {{ $p['policy'] ?? '—' }}
                        </td>


                        {{-- Effective Period --}}
                        <td>
                            {{ $p['effective_from'] }}
                            →
                            {{ $p['effective_to'] ?? '∞' }}
                        </td>


                        {{-- Tax Number --}}
                        <td>
                            {{ $p['tax_number'] ?? '—' }}
                        </td>


                        {{-- Personal Deduction --}}
                        <td>
                            {{ $p['personal_deduction'] ? 'Yes' : 'No' }}
                        </td>

                    </tr>

                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="p-8 text-center"
                        >
                            Chưa có Tax Profile.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>


    {{-- ================================================================
        CREATE TAX PROFILE
    ================================================================ --}}
    @can('payroll.tax.profile.manage')
        <section class="panel">
            <span class="eyebrow">
                PROFILE MANAGEMENT
            </span>

            <h2>
                Add Tax Profile
            </h2>

            <form
                method="POST"
                action="{{ route('admin.payroll.tax.profiles.store', [
                    'employee' => $employee,
                ]) }}"
                class="grid gap-3 md:grid-cols-2"
            >
                @csrf

                {{-- Company Context --}}
                <input
                    type="hidden"
                    name="company_id"
                    value="{{ $c }}"
                >


                {{-- ====================================================
                    TAX STATUS
                ==================================================== --}}
                <label>
                    Tax status

                    <select name="tax_status">
                        @foreach([
                            'taxable',
                            'exempt',
                            'not_subject',
                            'suspended',
                        ] as $s)
                            <option
                                value="{{ $s }}"
                                @selected(old('tax_status', 'taxable') === $s)
                            >
                                {{ $s }}
                            </option>
                        @endforeach
                    </select>
                </label>


                {{-- ====================================================
                    RESIDENCY STATUS
                ==================================================== --}}
                <label>
                    Residency status

                    <select name="residency_status">
                        @foreach([
                            'resident',
                            'non_resident',
                            'unknown',
                        ] as $s)
                            <option
                                value="{{ $s }}"
                                @selected(old('residency_status', 'resident') === $s)
                            >
                                {{ $s }}
                            </option>
                        @endforeach
                    </select>
                </label>


                {{-- ====================================================
                    TAX POLICY
                ==================================================== --}}
                <label>
                    Tax policy

                    <select name="tax_policy_id">
                        <option value="">
                            No policy
                        </option>

                        @foreach($vm->policies as $p)
                            <option
                                value="{{ $p['id'] }}"
                                @selected(
                                    (string) old('tax_policy_id') === (string) $p['id']
                                )
                            >
                                {{ $p['code'] }}
                                ·
                                {{ $p['name'] }}
                            </option>
                        @endforeach
                    </select>
                </label>


                {{-- ====================================================
                    EFFECTIVE FROM
                ==================================================== --}}
                <label>
                    Effective from

                    <input
                        type="date"
                        name="effective_from"
                        required
                        value="{{ old('effective_from') }}"
                    >
                </label>


                {{-- ====================================================
                    EFFECTIVE TO
                ==================================================== --}}
                <label>
                    Effective to

                    <input
                        type="date"
                        name="effective_to"
                        value="{{ old('effective_to') }}"
                    >
                </label>


                {{-- ====================================================
                    TAX IDENTIFICATION NUMBER
                ==================================================== --}}
                <label>
                    Tax identification number

                    <input
                        name="tax_identification_number"
                        value="{{ old('tax_identification_number') }}"
                        placeholder="Tax identification number"
                    >
                </label>


                {{-- ====================================================
                    PERSONAL DEDUCTION
                ==================================================== --}}
                <input
                    type="hidden"
                    name="personal_deduction_eligible"
                    value="0"
                >

                <label class="check-label">
                    <input
                        type="checkbox"
                        name="personal_deduction_eligible"
                        value="1"
                        @checked(
                            old('personal_deduction_eligible', true)
                        )
                    >

                    Personal deduction eligible
                </label>


                {{-- ====================================================
                    WITHHOLDING METHOD
                ==================================================== --}}
                <label>
                    Withholding method

                    <select name="withholding_method">
                        @foreach([
                            'policy',
                            'flat',
                            'exempt',
                        ] as $method)
                            <option
                                value="{{ $method }}"
                                @selected(
                                    old('withholding_method', 'policy') === $method
                                )
                            >
                                {{ ucfirst($method) }}
                            </option>
                        @endforeach
                    </select>
                </label>


                {{-- ====================================================
                    EXEMPTION REASON
                ==================================================== --}}
                <label class="md:col-span-2">
                    Exemption reason

                    <textarea
                        name="exemption_reason"
                        placeholder="Exemption reason"
                    >{{ old('exemption_reason') }}</textarea>
                </label>


                {{-- ====================================================
                    SUBMIT
                ==================================================== --}}
                <div class="md:col-span-2">
                    <button
                        class="button"
                        type="submit"
                    >
                        Add profile
                    </button>
                </div>

            </form>
        </section>
    @endcan

@endsection
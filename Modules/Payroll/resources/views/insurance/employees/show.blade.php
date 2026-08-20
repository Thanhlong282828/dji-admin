@extends('layouts.admin')

@section('title', 'Employee Insurance')

@section('content')
    @php($e = $vm->detail)

    {{-- ================================================================
        EMPLOYEE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            EMPLOYEE INSURANCE
        </span>

        <h1>
            {{ $e['number'] }}
            ·
            {{ $e['name'] }}
        </h1>
    </section>


    {{-- ================================================================
        INSURANCE PROFILES
    ================================================================ --}}
    <section class="overflow-x-auto rounded-xl border border-zinc-800">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Policy</th>
                    <th>Effective</th>
                    <th>Number</th>
                    <th>Override</th>
                </tr>
            </thead>

            <tbody>
                @forelse($e['profiles'] as $p)
                    <tr>

                        {{-- Status --}}
                        <td>
                            {{ $p['status'] }}
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


                        {{-- Insurance Number --}}
                        <td>
                            {{
                                $p['insurance_number']
                                    ? '***' . substr($p['insurance_number'], -4)
                                    : '—'
                            }}
                        </td>


                        {{-- Contribution Base Override --}}
                        <td>
                            {{ $p['override'] ?? '—' }}
                        </td>

                    </tr>

                @empty
                    <tr>
                        <td
                            colspan="5"
                            class="p-8 text-center"
                        >
                            Chưa có Insurance Profile.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>


    {{-- ================================================================
        CREATE INSURANCE PROFILE
    ================================================================ --}}
    @can('payroll.insurance.profile.manage')
        <section class="panel">
            <span class="eyebrow">
                PROFILE MANAGEMENT
            </span>

            <h2>
                Add Insurance Profile
            </h2>

            <form
                method="POST"
                action="{{ route('admin.payroll.insurance.profiles.store', [
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
                    PARTICIPATION STATUS
                ==================================================== --}}
                <label>
                    Participation status

                    <select name="participation_status">
                        @foreach([
                            'participating',
                            'not_participating',
                            'exempt',
                            'suspended',
                        ] as $s)
                            <option
                                value="{{ $s }}"
                                @selected(old('participation_status') === $s)
                            >
                                {{ $s }}
                            </option>
                        @endforeach
                    </select>
                </label>


                {{-- ====================================================
                    INSURANCE POLICY
                ==================================================== --}}
                <label>
                    Insurance policy

                    <select name="insurance_policy_id">
                        <option value="">
                            No policy
                        </option>

                        @foreach($vm->policies as $p)
                            <option
                                value="{{ $p['id'] }}"
                                @selected(
                                    (string) old('insurance_policy_id') === (string) $p['id']
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
                    INSURANCE NUMBER
                ==================================================== --}}
                <label>
                    Insurance number

                    <input
                        name="insurance_number"
                        value="{{ old('insurance_number') }}"
                        placeholder="Insurance number"
                    >
                </label>


                {{-- ====================================================
                    CONTRIBUTION BASE OVERRIDE
                ==================================================== --}}
                <label>
                    Contribution base override

                    <input
                        type="number"
                        min="0"
                        name="contribution_base_override_minor"
                        value="{{ old('contribution_base_override_minor') }}"
                        placeholder="Base override minor"
                    >
                </label>


                {{-- ====================================================
                    CURRENCY
                ==================================================== --}}
                <label>
                    Currency

                    <input
                        name="currency_code"
                        maxlength="3"
                        value="{{ old('currency_code', 'VND') }}"
                    >
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
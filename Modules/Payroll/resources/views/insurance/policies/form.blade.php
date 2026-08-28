@extends('layouts.admin')

@section('title', $policy ? 'Edit Insurance Policy' : 'Create Insurance Policy')

@section('content')
    @php($d = $vm->detail ?? [])

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            INSURANCE CONFIGURATION
        </span>

        <h1>
            {{ $policy ? 'Edit' : 'Create' }} Insurance Policy
        </h1>

        <p>
            Dữ liệu cấu hình nội bộ;
            không tuyên bố là tỷ lệ pháp luật hiện hành.
        </p>
    </section>


    {{-- ================================================================
        VALIDATION ERRORS
    ================================================================ --}}
    @if($errors->any())
        <div class="notice danger">
            {{ implode(' · ', $errors->all()) }}
        </div>
    @endif


    {{-- ================================================================
        INSURANCE POLICY FORM
    ================================================================ --}}
    <form
        method="POST"
        action="{{ $policy
            ? route('admin.payroll.insurance.policies.update', [
                'policy' => $policy,
            ])
            : route('admin.payroll.insurance.policies.store')
        }}"
        class="grid gap-3"
    >
        @csrf

        @if($policy)
            @method('PUT')
        @endif


        {{-- ============================================================
            COMPANY CONTEXT
        ============================================================ --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- ============================================================
            POLICY CODE
        ============================================================ --}}
        <label>
            Code

            <input
                name="code"
                required
                value="{{ old(
                    'code',
                    $d['code'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            POLICY NAME
        ============================================================ --}}
        <label>
            Name

            <input
                name="name"
                required
                value="{{ old(
                    'name',
                    $d['name'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            VERSION
        ============================================================ --}}
        <input
            type="hidden"
            name="version"
            value="{{ old(
                'version',
                $d['version'] ?? 1
            ) }}"
        >


        {{-- ============================================================
            EFFECTIVE FROM
        ============================================================ --}}
        <label>
            Effective From

            <input
                type="date"
                name="effective_from"
                required
                value="{{ old(
                    'effective_from',
                    $d['effective_from'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            EFFECTIVE TO
        ============================================================ --}}
        <label>
            Effective To

            <input
                type="date"
                name="effective_to"
                value="{{ old(
                    'effective_to',
                    $d['effective_to'] ?? ''
                ) }}"
            >
        </label>

        <input type="hidden" name="jurisdiction" value="VN">

        <label>
            Nguồn pháp lý chính thức
            <input type="url" name="source_reference" placeholder="https://vanban.chinhphu.vn/..." value="{{ old('source_reference', $d['source_reference'] ?? '') }}">
        </label>

        <label>
            Ngày kiểm chứng nguồn
            <input type="date" name="source_verified_at" value="{{ old('source_verified_at', isset($d['source_verified_at']) ? substr((string) $d['source_verified_at'], 0, 10) : '') }}">
        </label>


        {{-- ============================================================
            STATUS
        ============================================================ --}}
        <label>
            Status

            <select name="status">
                @foreach([
                    'draft',
                    'active',
                    'inactive',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'status',
                                $d['status'] ?? 'draft'
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            CURRENCY
        ============================================================ --}}
        <label>
            Currency

            <input
                name="currency_code"
                maxlength="3"
                value="{{ old(
                    'currency_code',
                    $d['currency_code'] ?? 'VND'
                ) }}"
            >
        </label>


        {{-- ============================================================
            ROUNDING RULE
        ============================================================ --}}
        <label>
            Rounding

            <select name="rounding_rule">
                <option
                    value="half_up"
                    @selected(
                        old(
                            'rounding_rule',
                            $d['rounding_rule'] ?? 'half_up'
                        ) === 'half_up'
                    )
                >
                    Half up
                </option>

                <option
                    value="down"
                    @selected(
                        old(
                            'rounding_rule',
                            $d['rounding_rule'] ?? 'half_up'
                        ) === 'down'
                    )
                >
                    Down
                </option>
            </select>
        </label>


        {{-- ============================================================
            INSURANCE RATES
        ============================================================ --}}
        <section class="grid gap-3">
            <h2>
                Rates (basis points)
            </h2>

            @foreach(
                ($d['rates'] ?? [
                    ['insurance_type' => 'social'],
                    ['insurance_type' => 'health'],
                    ['insurance_type' => 'unemployment'],
                ])
                as $i => $rate
            )
                <fieldset class="grid gap-2 md:grid-cols-5">

                    {{-- Insurance Type --}}
                    <select name="rates[{{ $i }}][insurance_type]">
                        @foreach([
                            'social',
                            'health',
                            'unemployment',
                            'occupational_accident',
                        ] as $t)
                            <option
                                value="{{ $t }}"
                                @selected(
                                    ($rate['insurance_type'] ?? '') === $t
                                )
                            >
                                {{ $t }}
                            </option>
                        @endforeach
                    </select>


                    {{-- Employee Rate --}}
                    <input
                        type="number"
                        min="0"
                        max="10000"
                        name="rates[{{ $i }}][employee_rate_basis_points]"
                        value="{{ $rate['employee_rate_basis_points'] ?? 0 }}"
                        placeholder="Employee bp"
                    >


                    {{-- Employer Rate --}}
                    <input
                        type="number"
                        min="0"
                        max="10000"
                        name="rates[{{ $i }}][employer_rate_basis_points]"
                        value="{{ $rate['employer_rate_basis_points'] ?? 0 }}"
                        placeholder="Employer bp"
                    >


                    {{-- Minimum Base --}}
                    <input
                        type="number"
                        min="0"
                        name="rates[{{ $i }}][minimum_base_minor]"
                        value="{{ $rate['minimum_base_minor'] ?? '' }}"
                        placeholder="Minimum minor"
                    >


                    {{-- Maximum Base --}}
                    <input
                        type="number"
                        min="0"
                        name="rates[{{ $i }}][maximum_base_minor]"
                        value="{{ $rate['maximum_base_minor'] ?? '' }}"
                        placeholder="Maximum minor"
                    >

                </fieldset>
            @endforeach
        </section>


        {{-- ============================================================
            NOTE
        ============================================================ --}}
        <label>
            Note

            <textarea name="note">{{ old(
                'note',
                $d['note'] ?? ''
            ) }}</textarea>
        </label>


        {{-- ============================================================
            SUBMIT
        ============================================================ --}}
        <div class="form-actions">
            <button
                class="button"
                type="submit"
            >
                Save policy
            </button>
        </div>

    </form>

@endsection

@extends('layouts.admin')

@section('title', $policy ? 'Edit Tax Policy' : 'Create Tax Policy')

@section('content')
    @php
        $d = $vm->detail ?? [];
    @endphp

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            TAX CONFIGURATION
        </span>

        <h1>
            {{ $policy ? 'Edit' : 'Create' }} Tax Policy
        </h1>

        <p>
            Tỷ lệ, bracket và deduction đều là cấu hình;
            không hard-code quy định pháp lý.
        </p>
             <i>/</i>

         <span>
            Payroll/resources/views/tax/policies/form
        </span>
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
        TAX POLICY FORM
    ================================================================ --}}
    <form
        method="POST"
        action="{{ $policy
            ? route('admin.payroll.tax.policies.update', [
                'policy' => $policy,
            ])
            : route('admin.payroll.tax.policies.store')
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
            CODE
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
            NAME
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
            TAX METHOD
        ============================================================ --}}
        <label>
            Method

            <select name="tax_method">
                @foreach([
                    'progressive',
                    'flat',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'tax_method',
                                $d['tax_method'] ?? 'progressive'
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            RESIDENCY GROUP
        ============================================================ --}}
        <label>
            Residency

            <select name="residency_group">
                @foreach([
                    'resident',
                    'non_resident',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'residency_group',
                                $d['residency_group'] ?? 'resident'
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
            FLAT RATE
        ============================================================ --}}
        <label>
            Flat rate (basis points)

            <input
                type="number"
                min="0"
                max="10000"
                name="flat_rate_basis_points"
                value="{{ old(
                    'flat_rate_basis_points',
                    $d['flat_rate_basis_points'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            FLOOR TAXABLE INCOME
        ============================================================ --}}
        <label class="check-label">
            <input
                type="checkbox"
                name="floor_taxable_income_at_zero"
                value="1"
                @checked(
                    old(
                        'floor_taxable_income_at_zero',
                        $d['floor_taxable_income_at_zero'] ?? true
                    )
                )
            >

            Floor taxable income at zero
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
            PROGRESSIVE BRACKETS
        ============================================================ --}}
        <section class="grid gap-3">
            <h2>
                Progressive brackets
            </h2>

            @foreach(
                ($d['brackets'] ?? [
                    [
                        'lower_bound_minor' => 0,
                        'upper_bound_minor' => null,
                        'rate_basis_points' => 0,
                    ],
                ])
                as $i => $b
            )
                <fieldset class="grid gap-2 md:grid-cols-3">

                    {{-- Lower Bound --}}
                    <input
                        type="number"
                        min="0"
                        name="brackets[{{ $i }}][lower_bound_minor]"
                        value="{{ $b['lower_bound_minor'] ?? 0 }}"
                        placeholder="Lower"
                    >


                    {{-- Upper Bound --}}
                    <input
                        type="number"
                        min="1"
                        name="brackets[{{ $i }}][upper_bound_minor]"
                        value="{{ $b['upper_bound_minor'] ?? '' }}"
                        placeholder="Upper (empty for last)"
                    >


                    {{-- Rate --}}
                    <input
                        type="number"
                        min="0"
                        max="10000"
                        name="brackets[{{ $i }}][rate_basis_points]"
                        value="{{ $b['rate_basis_points'] ?? 0 }}"
                        placeholder="Rate bp"
                    >

                </fieldset>
            @endforeach
        </section>


        {{-- ============================================================
            DEDUCTIONS
        ============================================================ --}}
        <section class="grid gap-3">
            <h2>
                Deductions
            </h2>

            @foreach(
                ($d['deductions'] ?? [
                    [
                        'code' => 'PERSONAL',
                        'name' => 'Personal deduction',
                        'deduction_type' => 'personal',
                    ],
                    [
                        'code' => 'DEPENDENT',
                        'name' => 'Dependent deduction',
                        'deduction_type' => 'dependent',
                    ],
                    [
                        'code' => 'INSURANCE',
                        'name' => 'Employee insurance',
                        'deduction_type' => 'employee_insurance',
                    ],
                ])
                as $i => $x
            )
                <fieldset class="grid gap-2 md:grid-cols-4">

                    {{-- Code --}}
                    <input
                        name="deductions[{{ $i }}][code]"
                        value="{{ $x['code'] ?? '' }}"
                        placeholder="Code"
                    >


                    {{-- Name --}}
                    <input
                        name="deductions[{{ $i }}][name]"
                        value="{{ $x['name'] ?? '' }}"
                        placeholder="Name"
                    >


                    {{-- Deduction Type --}}
                    <select name="deductions[{{ $i }}][deduction_type]">
                        @foreach([
                            'personal',
                            'dependent',
                            'employee_insurance',
                            'configured',
                        ] as $t)
                            <option
                                value="{{ $t }}"
                                @selected(
                                    ($x['deduction_type'] ?? '') === $t
                                )
                            >
                                {{ $t }}
                            </option>
                        @endforeach
                    </select>


                    {{-- Amount --}}
                    <input
                        type="number"
                        min="0"
                        name="deductions[{{ $i }}][amount_minor]"
                        value="{{ $x['amount_minor'] ?? '' }}"
                        placeholder="Amount minor"
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
                Save Tax Policy
            </button>
        </div>

    </form>

@endsection

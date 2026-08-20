@extends('layouts.admin')

@section('title', 'Create Annual PIT')

@section('content')

    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            IMMUTABLE SOURCE FOUNDATION
        </span>

        <h1>
            Create Annual Tax Reconciliation
        </h1>

        <p>
            Employer-scoped only.
            External employer income is not included.
        </p>
          <i>/</i>

         <span>
            Payroll/resources/views/annual-tax/create
        </span>
    </section>


    {{-- ================================================================
        VALIDATION ERRORS
    ================================================================ --}}
    @if($errors->any())
        <div class="notice danger">
            {{ $errors->first() }}
        </div>
    @endif


    {{-- ================================================================
        CREATE ANNUAL TAX RECONCILIATION FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ route('admin.payroll.annual-tax.store') }}"
    >
        @csrf


        {{-- ============================================================
            COMPANY CONTEXT
        ============================================================ --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- ============================================================
            IDEMPOTENCY KEY
        ============================================================ --}}
        <input
            type="hidden"
            name="idempotency_key"
            value="{{ (string) Illuminate\Support\Str::uuid() }}"
        >


        {{-- ============================================================
            EMPLOYEE
        ============================================================ --}}
        <label>
            Employee

            <select
                name="employee_id"
                required
            >
                @foreach($data['employees'] as $e)
                    <option
                        value="{{ $e['id'] }}"
                        @selected(
                            (string) old('employee_id') === (string) $e['id']
                        )
                    >
                        {{ $e['employee_code'] }}
                        ·
                        {{ $e['full_name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            TAX YEAR
        ============================================================ --}}
        <label>
            Tax year

            <input
                type="number"
                name="tax_year"
                value="{{ old('tax_year', now()->year) }}"
                required
            >
        </label>


        {{-- ============================================================
            JURISDICTION
        ============================================================ --}}
        <label>
            Jurisdiction

            <select name="jurisdiction">
                <option
                    value="VN"
                    @selected(old('jurisdiction', 'VN') === 'VN')
                >
                    Vietnam
                </option>
            </select>
        </label>


        {{-- ============================================================
            CURRENCY
        ============================================================ --}}
        <label>
            Currency

            <input
                name="currency"
                value="{{ old('currency', 'VND') }}"
                maxlength="3"
            >
        </label>


        {{-- ============================================================
            SUBMIT
        ============================================================ --}}
        <button
            class="button"
            type="submit"
        >
            Create immutable draft
        </button>

    </form>

@endsection
@extends('layouts.admin')

@section('title', 'Create Payroll Adjustment')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.adjustments.index', [
                'company_id' => $c,
            ]) }}"
        >
            Adjustments
        </a>

        <i>/</i>

        <span>
            Create
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Historical source lock
        </span>

        <h2>
            Create adjustment request
        </h2>

        <p>
            Chỉ finalized Payroll Item được chọn.
            Historical snapshot và policy references được capture ngay khi tạo.
        </p>
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
        CREATE PAYROLL ADJUSTMENT FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ route('admin.payroll.adjustments.store') }}"
    >
        @csrf


        {{-- Company Context --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- Idempotency --}}
        <input
            type="hidden"
            name="idempotency_key"
            value="{{ (string) Illuminate\Support\Str::uuid() }}"
        >


        {{-- ============================================================
            SOURCE PAYROLL ITEM
        ============================================================ --}}
        <label class="full">
            Finalized Payroll Item

            <select
                name="source_payroll_item_id"
                required
            >
                <option value="">
                    Choose exact source
                </option>

                @foreach($vm->data['items'] as $i)
                    <option
                        value="{{ $i['id'] }}"
                        @selected(
                            (string) old('source_payroll_item_id') === (string) $i['id']
                        )
                    >
                        {{ $i['employee_number_snapshot'] }}
                        ·
                        {{ $i['employee_name_snapshot'] }}
                        ·
                        {{ $i['period_code'] }}
                        ·
                        calc v{{ $i['calculation_version'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ADJUSTMENT REASON
        ============================================================ --}}
        <label>
            Reason

            <select
                name="adjustment_reason_id"
                required
            >
                <option value="">
                    Choose reason
                </option>

                @foreach($vm->data['reasons'] as $r)
                    <option
                        value="{{ $r['id'] }}"
                        @selected(
                            (string) old('adjustment_reason_id') === (string) $r['id']
                        )
                    >
                        {{ $r['code'] }}
                        ·
                        {{ $r['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ADJUSTMENT TYPE
        ============================================================ --}}
        <label>
            Type

            <select
                name="adjustment_type"
                required
            >
                @foreach(
                    Modules\Payroll\Domain\Enums\PayrollAdjustmentType::cases()
                    as $t
                )
                    <option
                        value="{{ $t->value }}"
                        @selected(old('adjustment_type') === $t->value)
                    >
                        {{ str_replace('_', ' ', $t->value) }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            EFFECTIVE DATE
        ============================================================ --}}
        <label>
            Effective date

            <input
                type="date"
                name="requested_effective_date"
                required
                value="{{ old(
                    'requested_effective_date',
                    now()->toDateString()
                ) }}"
            >
        </label>


        {{-- ============================================================
            TARGET PAYMENT PERIOD
        ============================================================ --}}
        <label>
            Target payment period

            <select name="target_payment_period_id">
                <option value="">
                    Not assigned
                </option>

                @foreach($vm->data['periods'] as $p)
                    <option
                        value="{{ $p['id'] }}"
                        @selected(
                            (string) old('target_payment_period_id') === (string) $p['id']
                        )
                    >
                        {{ $p['code'] }}
                        ·
                        {{ $p['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            DESCRIPTION
        ============================================================ --}}
        <label class="full">
            Description

            <textarea
                name="description"
                required
                maxlength="2000"
            >{{ old('description') }}</textarea>
        </label>


        {{-- ============================================================
            EVIDENCE DOCUMENT
        ============================================================ --}}
        <label>
            Evidence document ID

            <input
                type="number"
                min="1"
                name="evidence_document_id"
                value="{{ old('evidence_document_id') }}"
            >
        </label>


        {{-- ============================================================
            INTEGRITY NOTICE
        ============================================================ --}}
        <div class="full integrity-callout">
            <strong>
                No historical mutation
            </strong>

            <span>
                Request creates immutable snapshots;
                it never updates original Payroll source.
            </span>
        </div>


        {{-- ============================================================
            ACTIONS
        ============================================================ --}}
        <div class="full form-actions">
            <button
                class="button"
                type="submit"
            >
                Create Request
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.adjustments.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Cancel
            </a>
        </div>

    </form>

@endsection
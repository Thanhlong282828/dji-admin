@extends('layouts.admin')

@php($m = $vm->detail)

@section('title', $m ? 'Edit Accounting Mapping' : 'Create Accounting Mapping')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.accounting.mappings.index', [
                'company_id' => $c,
            ]) }}"
        >
            Mappings
        </a>

        <i>/</i>

        <span>
            {{ $m ? 'Edit' : 'Create' }}
        </span>
                <i>/</i>

         <span>
            Payroll/resources/views/mappings/form - create - edit
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Mapping resolver
        </span>

        <h2>
            {{ $m ? 'Chỉnh sửa mapping' : 'Khai báo mapping' }}
        </h2>

        <p>
            Debit hoặc Credit account là bắt buộc.
            Tất cả reference phải active, postable và thuộc cùng Company.
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
        ACCOUNTING MAPPING FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ $m
            ? route('admin.payroll.accounting.mappings.update', [
                'mapping' => $m['id'],
            ])
            : route('admin.payroll.accounting.mappings.store')
        }}"
    >
        @csrf

        @if($m)
            @method('PUT')
        @endif


        {{-- Company Context --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- ============================================================
            POLICY
        ============================================================ --}}
        <label>
            Policy

            <select
                name="accounting_policy_id"
                required
            >
                @foreach($vm->options['policies'] as $p)
                    <option
                        value="{{ $p['id'] }}"
                        @selected(
                            old(
                                'accounting_policy_id',
                                $m['accounting_policy_id'] ?? null
                            ) == $p['id']
                        )
                    >
                        {{ $p['code'] }}
                        ·
                        v{{ $p['version'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            SOURCE TYPE
        ============================================================ --}}
        <label>
            Source type

            <select name="source_type">
                @foreach([
                    'earning_component',
                    'other_deduction',
                    'employee_insurance',
                    'employer_insurance_expense',
                    'employer_insurance_liability',
                    'personal_income_tax',
                    'net_pay',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'source_type',
                                $m['source_type'] ?? ''
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            SOURCE CODE
        ============================================================ --}}
        <label>
            Source code

            <input
                name="source_code"
                required
                value="{{ old(
                    'source_code',
                    $m['source_code'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            DEBIT ACCOUNT
        ============================================================ --}}
        <label>
            Debit account

            <select name="debit_account_id">
                <option value="">
                    —
                </option>

                @foreach($vm->options['accounts'] as $a)
                    <option
                        value="{{ $a['id'] }}"
                        @selected(
                            old(
                                'debit_account_id',
                                $m['debit_account_id'] ?? null
                            ) == $a['id']
                        )
                    >
                        {{ $a['code'] }}
                        ·
                        {{ $a['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            CREDIT ACCOUNT
        ============================================================ --}}
        <label>
            Credit account

            <select name="credit_account_id">
                <option value="">
                    —
                </option>

                @foreach($vm->options['accounts'] as $a)
                    <option
                        value="{{ $a['id'] }}"
                        @selected(
                            old(
                                'credit_account_id',
                                $m['credit_account_id'] ?? null
                            ) == $a['id']
                        )
                    >
                        {{ $a['code'] }}
                        ·
                        {{ $a['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            COST CENTER
        ============================================================ --}}
        <label>
            Cost center

            <select name="cost_center_id">
                <option value="">
                    Global
                </option>

                @foreach($vm->options['cost_centers'] as $o)
                    <option
                        value="{{ $o['id'] }}"
                        @selected(
                            old(
                                'cost_center_id',
                                $m['cost_center_id'] ?? null
                            ) == $o['id']
                        )
                    >
                        {{ $o['code'] }}
                        ·
                        {{ $o['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ORGANIZATION UNIT
        ============================================================ --}}
        <label>
            Organization unit

            <select name="organization_unit_id">
                <option value="">
                    Global
                </option>

                @foreach($vm->options['organization_units'] as $o)
                    <option
                        value="{{ $o['id'] }}"
                        @selected(
                            old(
                                'organization_unit_id',
                                $m['organization_unit_id'] ?? null
                            ) == $o['id']
                        )
                    >
                        {{ $o['code'] }}
                        ·
                        {{ $o['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            PRIORITY
        ============================================================ --}}
        <label>
            Priority

            <input
                type="number"
                name="priority"
                value="{{ old(
                    'priority',
                    $m['priority'] ?? 100
                ) }}"
            >
        </label>


        {{-- ============================================================
            EFFECTIVE FROM
        ============================================================ --}}
        <label>
            Effective from

            <input
                type="date"
                name="effective_from"
                required
                value="{{ old(
                    'effective_from',
                    $m['effective_from'] ?? now()->toDateString()
                ) }}"
            >
        </label>


        {{-- ============================================================
            EFFECTIVE TO
        ============================================================ --}}
        <label>
            Effective to

            <input
                type="date"
                name="effective_to"
                value="{{ old(
                    'effective_to',
                    $m['effective_to'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            STATUS
        ============================================================ --}}
        <label>
            Status

            <select name="status">
                @foreach([
                    'active',
                    'inactive',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'status',
                                $m['status'] ?? 'active'
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ACTIONS
        ============================================================ --}}
        <div class="full form-actions">
            <button
                class="button"
                type="submit"
            >
                Lưu mapping
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.mappings.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
@extends('layouts.admin')

@php($a = $vm->detail)

@section('title', $a ? 'Edit Accounting Account' : 'Create Accounting Account')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.accounting.accounts.index', [
                'company_id' => $c,
            ]) }}"
        >
            Chart of Accounts
        </a>

        <i>/</i>

        <span>
            {{ $a ? 'Edit' : 'Create' }}
        </span>
                <i>/</i>

         <span>
            Payroll/resources/views/accounts/form
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Ledger account
        </span>

        <h2>
            {{ $a ? 'Chỉnh sửa account' : 'Tạo account mới' }}
        </h2>

        <p>
            Account được scope theo Company và effective date.
            Account không postable không thể dùng trong Payroll mapping.
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
        ACCOUNT FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ $a
            ? route('admin.payroll.accounting.accounts.update', [
                'account' => $a['id'],
            ])
            : route('admin.payroll.accounting.accounts.store')
        }}"
    >
        @csrf

        @if($a)
            @method('PUT')
        @endif


        {{-- Company Context --}}
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
                    $a['code'] ?? ''
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
                    $a['name'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            ACCOUNT TYPE
        ============================================================ --}}
        <label>
            Account type

            <select name="account_type">
                @foreach([
                    'asset',
                    'liability',
                    'equity',
                    'revenue',
                    'expense',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'account_type',
                                $a['account_type'] ?? ''
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            NORMAL BALANCE
        ============================================================ --}}
        <label>
            Normal balance

            <select name="normal_balance">
                @foreach([
                    'debit',
                    'credit',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'normal_balance',
                                $a['normal_balance'] ?? ''
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
            Currency (optional)

            <input
                name="currency"
                maxlength="3"
                value="{{ old(
                    'currency',
                    $a['currency'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            PARENT ACCOUNT
        ============================================================ --}}
        <label>
            Parent account

            <select name="parent_id">
                <option value="">
                    Root
                </option>

                @foreach($vm->options['accounts'] as $p)
                    <option
                        value="{{ $p['id'] }}"
                        @selected(
                            old(
                                'parent_id',
                                $a['parent_id'] ?? null
                            ) == $p['id']
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
                    $a['effective_from'] ?? now()->toDateString()
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
                    $a['effective_to'] ?? ''
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
                                $a['status'] ?? 'active'
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ALLOW POSTING
        ============================================================ --}}
        <label class="check-label">
            <input
                type="checkbox"
                name="allow_posting"
                value="1"
                @checked(
                    old(
                        'allow_posting',
                        $a['allow_posting'] ?? true
                    )
                )
            >

            Cho phép posting
        </label>


        {{-- ============================================================
            ACTIONS
        ============================================================ --}}
        <div class="full form-actions">
            <button
                class="button"
                type="submit"
            >
                Lưu account
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.accounts.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
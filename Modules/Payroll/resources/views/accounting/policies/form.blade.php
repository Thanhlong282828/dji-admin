@extends('layouts.admin')

@php($p = $vm->detail)

@section('title', $p ? 'Edit Accounting Policy' : 'Create Accounting Policy')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.accounting.policies.index', [
                'company_id' => $c,
            ]) }}"
        >
            Accounting Policies
        </a>

        <i>/</i>

        <span>
            {{ $p ? 'Edit' : 'Create' }}
        </span>
             <i>/</i>

         <span>
            Payroll/resources/views/policies/from - create - edit
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Versioned configuration
        </span>

        <h2>
            {{ $p
                ? 'Điều chỉnh policy chưa khóa'
                : 'Tạo chính sách hạch toán'
            }}
        </h2>

        <p>
            Policy đã được Journal approved sử dụng sẽ bất biến;
            hãy tạo version mới thay vì sửa lịch sử.
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
        ACCOUNTING POLICY FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ $p
            ? route('admin.payroll.accounting.policies.update', [
                'policy' => $p['id'],
            ])
            : route('admin.payroll.accounting.policies.store')
        }}"
    >
        @csrf

        @if($p)
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
                    $p['code'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            POLICY NAME
        ============================================================ --}}
        <label>
            Tên policy

            <input
                name="name"
                required
                value="{{ old(
                    'name',
                    $p['name'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            VERSION
        ============================================================ --}}
        <label>
            Version

            <input
                type="number"
                min="1"
                name="version"
                required
                value="{{ old(
                    'version',
                    $p['version'] ?? 1
                ) }}"
            >
        </label>


        {{-- ============================================================
            CURRENCY
        ============================================================ --}}
        <label>
            Currency

            <input
                name="currency"
                maxlength="3"
                required
                value="{{ old(
                    'currency',
                    $p['currency'] ?? 'VND'
                ) }}"
            >
        </label>


        {{-- ============================================================
            AGGREGATION STRATEGY
        ============================================================ --}}
        <label>
            Aggregation

            <select name="aggregation_strategy">
                <option
                    value="per_account_and_cost_center"
                    @selected(
                        old(
                            'aggregation_strategy',
                            $p['aggregation_strategy']
                                ?? 'per_account_and_cost_center'
                        ) === 'per_account_and_cost_center'
                    )
                >
                    Per account & cost center
                </option>
            </select>
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
                            $p['rounding_rule'] ?? 'half_up'
                        ) === 'half_up'
                    )
                >
                    Half up
                </option>
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
                    $p['effective_from'] ?? now()->toDateString()
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
                    $p['effective_to'] ?? ''
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
                    'draft',
                    'active',
                    'inactive',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'status',
                                $p['status'] ?? 'draft'
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            NOTE
        ============================================================ --}}
        <label class="full">
            Note

            <textarea
                name="note"
            >{{ old('note', $p['note'] ?? '') }}</textarea>
        </label>


        {{-- ============================================================
            ACTIONS
        ============================================================ --}}
        <div class="full form-actions">
            <button
                class="button"
                type="submit"
            >
                Lưu policy
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.policies.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
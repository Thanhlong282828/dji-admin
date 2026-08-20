@extends('layouts.admin')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.erp.mappings.index', [
                'company_id' => $c,
            ]) }}"
        >
            ERP Mappings
        </a>

        <i>/</i>

        <span>
            {{ $mapping ? 'New version' : 'Create' }}
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Versioned mapping
        </span>

        <h2>
            {{ $mapping
                ? 'Tạo phiên bản mapping mới'
                : 'Tạo mapping'
            }}
        </h2>

        <p>
            Không sửa snapshot trong export cũ.
            Update sẽ supersede version hiện tại.
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
        ERP MAPPING FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ $mapping
            ? route('admin.payroll.erp.mappings.update', [
                'mapping' => $mapping['id'],
            ])
            : route('admin.payroll.erp.mappings.store')
        }}"
    >
        @csrf

        @if($mapping)
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
            ERP CONNECTION
        ============================================================ --}}
        <label>
            ERP Connection

            <select
                name="erp_connection_id"
                required
            >
                @foreach($vm->options['connections'] as $x)
                    <option
                        value="{{ $x['id'] }}"
                        @selected(
                            old(
                                'erp_connection_id',
                                $mapping['erp_connection_id'] ?? null
                            ) == $x['id']
                        )
                    >
                        {{ $x['name'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ACCOUNTING ACCOUNT
        ============================================================ --}}
        <label>
            Accounting Account

            <select
                name="source_account_code"
                required
            >
                @foreach($vm->options['accounts'] as $a)
                    <option
                        value="{{ $a['code'] }}"
                        @selected(
                            old(
                                'source_account_code',
                                $mapping['source_account_code'] ?? null
                            ) === $a['code']
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
            ERP EXTERNAL ACCOUNT
        ============================================================ --}}
        <label>
            ERP External Account

            <input
                name="external_account_code"
                maxlength="100"
                required
                value="{{ old(
                    'external_account_code',
                    $mapping['external_account_code'] ?? ''
                ) }}"
            >
        </label>


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
                    now()->toDateString()
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
                    ''
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
                            old('status', 'active') === $s
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
                Lưu mapping version
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.erp.mappings.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
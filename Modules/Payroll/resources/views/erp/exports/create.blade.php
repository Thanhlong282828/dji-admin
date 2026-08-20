@extends('layouts.admin')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.erp.exports.index', [
                'company_id' => $c,
            ]) }}"
        >
            ERP Exports
        </a>

        <i>/</i>

        <span>
            Create
        </span>
          <i>/</i>
         <span>Payroll/resources/views/erp/exports/create</span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Immutable accounting snapshot
        </span>

        <h2>
            Tạo ERP export intent
        </h2>

        <p>
            Amounts được copy đúng từ persisted accounting journal lines.
            Không tính lại Payroll.
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
        CREATE ERP EXPORT FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ route('admin.payroll.erp.exports.store') }}"
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
            ERP CONNECTION
        ============================================================ --}}
        <label>
            ERP Connection

            <select
                name="connection_id"
                required
            >
                <option value="">
                    Chọn connection
                </option>

                @foreach($vm->options['connections'] as $x)
                    <option
                        value="{{ $x['id'] }}"
                        @selected(
                            (string) old('connection_id') === (string) $x['id']
                        )
                    >
                        {{ $x['name'] }}
                        ·
                        {{ $x['provider'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ACCOUNTING JOURNAL
        ============================================================ --}}
        <label>
            Accounting Journal

            <select
                name="journal_id"
                required
            >
                <option value="">
                    Chọn journal
                </option>

                @foreach($vm->options['journals'] as $j)
                    <option
                        value="{{ $j['id'] }}"
                        @selected(
                            (string) old('journal_id') === (string) $j['id']
                        )
                    >
                        {{ $j['journal_number'] }}
                        ·
                        {{ $j['status'] }}
                        ·
                        {{ $j['currency'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            SECURITY BOUNDARY
        ============================================================ --}}
        <div class="full integrity-callout">
            <strong>
                Security boundary
            </strong>

            <span>
                Connection chỉ chứa credential reference;
                không lưu token/password plaintext.
                Generic CSV chỉ tạo private artifact.
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
                Tạo snapshot
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.erp.exports.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
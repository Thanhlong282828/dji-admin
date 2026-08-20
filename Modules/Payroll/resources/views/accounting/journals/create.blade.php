@extends('layouts.admin')

@section('title', 'Create Payroll Journal')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.accounting.journals.index', [
                'company_id' => $c,
            ]) }}"
        >
            Payroll Journals
        </a>

        <i>/</i>

        <span>
            Create
        </span>
                        <i>/</i>

         <span>
            Payroll/resources/views/journals/create
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Exact persisted source
        </span>

        <h2>
            Tạo journal từ finalized run
        </h2>

        <p>
            Hệ thống khóa Payroll Run, calculation version,
            policy version và dimension snapshot.
            Không đọc lại Attendance/Leave/Overtime live.
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
        CREATE PAYROLL JOURNAL FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ route('admin.payroll.accounting.journals.store') }}"
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
            FINALIZED PAYROLL RUN
        ============================================================ --}}
        <label>
            Finalized Payroll Run

            <select
                name="run_id"
                required
            >
                <option value="">
                    Chọn run
                </option>

                @foreach($vm->options['runs'] as $r)
                    <option
                        value="{{ $r['id'] }}"
                        @selected(
                            (string) old('run_id') === (string) $r['id']
                        )
                    >
                        {{ $r['run_number'] }}
                        ·
                        {{ $r['period_code'] }}
                        ·
                        calc v{{ $r['calculation_version'] }}
                        ·
                        {{ $r['currency_code'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            POSTING DATE
        ============================================================ --}}
        <label>
            Posting date

            <input
                type="date"
                name="posting_date"
                required
                value="{{ old(
                    'posting_date',
                    now()->toDateString()
                ) }}"
            >
        </label>


        {{-- ============================================================
            ACCOUNTING BOUNDARY
        ============================================================ --}}
        <div class="full integrity-callout">
            <strong>
                Accounting boundary
            </strong>

            <span>
                Chưa có external ERP adapter.
                Journal được tạo là internal accounting foundation.
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
                Tạo journal
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.journals.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
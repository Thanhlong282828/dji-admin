@extends('layouts.admin')

@section('title', 'Payroll Adjustments')

@section('header_action')
    @can('payroll.adjustment.create')
        <a
            class="button"
            href="{{ route('admin.payroll.adjustments.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Create Adjustment
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.runs.index', [
                'company_id' => $c,
            ]) }}"
        >
            Payroll
        </a>

        <i>/</i>

        <span>
            Retroactive Adjustments
        </span>
        <i>/</i>

         <span>
            Payroll/resources/views/adjustments/index
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Immutable historical correction
        </span>

        <h2>
            Retroactive Payroll Adjustments
        </h2>

        <p>
            Điều chỉnh tạo delta mới và source trace chính xác.
            Finalized Payroll Run, Payslip, Payment Batch và Accounting Journal
            lịch sử không bị sửa.
        </p>

        <div class="integrity-callout">
            <strong>
                Control boundary
            </strong>

            <span>
                Negative adjustment không phải negative bank transfer.
                Applied adjustment là immutable.
            </span>
        </div>
    </section>


    {{-- ================================================================
        SUCCESS MESSAGE
    ================================================================ --}}
    @if(session('success'))
        <div class="notice success">
            {{ session('success') }}
        </div>
    @endif


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form
        class="toolbar"
        method="GET"
    >
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- Search --}}
        <label>
            Tìm kiếm

            <input
                name="search"
                value="{{ $f['search'] }}"
                placeholder="Adjustment, employee…"
            >
        </label>


        {{-- Status --}}
        <label>
            Status

            <select name="status">
                <option value="">
                    All
                </option>

                @foreach([
                    'draft',
                    'calculated',
                    'validated',
                    'submitted',
                    'reviewed',
                    'approved',
                    'rejected',
                    'applied',
                    'superseded',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected($f['status'] === $s)
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- Submit --}}
        <button
            class="button"
            type="submit"
        >
            Filter
        </button>
    </form>


    {{-- ================================================================
        ADJUSTMENTS
    ================================================================ --}}
    <section class="table-shell">

        @if($vm->data['rows'] === [])

            {{-- ========================================================
                EMPTY STATE
            ======================================================== --}}
            <div class="empty-block">
                <div class="empty-icon">
                    Δ
                </div>

                <h3>
                    Chưa có adjustment
                </h3>

                <p>
                    Production không được seed dữ liệu giả.
                    Hãy chọn finalized Payroll Item thật khi có.
                </p>
            </div>

        @else

            {{-- ========================================================
                ADJUSTMENTS TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Adjustment</th>
                            <th>Employee</th>
                            <th>Source</th>
                            <th>Reason / Type</th>
                            <th>Delta gross</th>
                            <th>Delta tax</th>
                            <th>Delta net</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->data['rows'] as $a)
                            <tr>

                                {{-- Adjustment --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $a['adjustment_number'] }}
                                    </strong>

                                    <span>
                                        {{ $a['created_at'] }}
                                    </span>
                                </td>


                                {{-- Employee --}}
                                <td>
                                    {{ $a['employee_code'] }}
                                    ·
                                    {{ $a['full_name'] }}
                                </td>


                                {{-- Source --}}
                                <td>
                                    {{ $a['period_code'] }}
                                    ·
                                    calc v{{ $a['source_calculation_version'] }}
                                </td>


                                {{-- Reason / Type --}}
                                <td>
                                    {{ $a['reason_name'] }}

                                    <br>

                                    <small>
                                        {{ $a['adjustment_type'] }}
                                    </small>
                                </td>


                                {{-- Delta Gross --}}
                                <td class="money">
                                    {{ number_format($a['delta_gross_minor'] ?? 0) }}
                                </td>


                                {{-- Delta Tax --}}
                                <td class="money">
                                    {{ number_format($a['delta_personal_income_tax_minor'] ?? 0) }}
                                </td>


                                {{-- Delta Net --}}
                                <td class="money">
                                    {{ number_format($a['delta_net_minor'] ?? 0) }}
                                    {{ $a['currency'] ?? '' }}
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $a['status'] }}">
                                        {{ $a['status'] }}
                                    </span>
                                </td>


                                {{-- Actions --}}
                                <td>
                                    <a
                                        class="row-action"
                                        href="{{ route('admin.payroll.adjustments.show', [
                                            'adjustment' => $a['id'],
                                            'company_id' => $c,
                                        ]) }}"
                                    >
                                        Detail →
                                    </a>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @endif

    </section>

@endsection
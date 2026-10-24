@extends('layouts.admin')

@section('title', 'Payroll Journals')

@section('header_action')
    @can('payroll.accounting.journal.create')
        <a
            class="admin-button admin-button--primary button"
            href="{{ route('admin.payroll.accounting.journals.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Tạo journal
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
            Accounting Journals
        </span>
                <i>/</i>

         <span>
            Payroll/resources/views/journals/index
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Payroll accounting control
        </span>

        <h2>
            Nhật ký hạch toán lương
        </h2>

        <p>
            Tạo bút toán cân bằng từ exact persisted finalized Payroll Run.
            “Internally Posted” chỉ là trạng thái kiểm soát nội bộ,
            không đồng nghĩa đã post sang ERP bên ngoài.
        </p>

        <div class="hero-actions">
            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.policies.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Policies
            </a>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.mappings.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Mappings
            </a>
        </div>
    </section>


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

        <label>
            Tìm journal

            <input
                name="search"
                value="{{ $f['search'] }}"
                placeholder="Journal hoặc run number…"
            >
        </label>

        <label>
            Status

            <select name="status">
                <option value="">
                    Tất cả
                </option>

                @foreach([
                    'draft',
                    'validated',
                    'reviewed',
                    'approved',
                    'internally_posted',
                    'exported',
                    'rejected',
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

        <button
            class="button"
            type="submit"
        >
            Lọc
        </button>
    </form>


    {{-- ================================================================
        PAYROLL JOURNALS
    ================================================================ --}}
    <section class="table-shell">

        @if($vm->items === [])

            {{-- ========================================================
                EMPTY STATE
            ======================================================== --}}
            <div class="empty-block">
                <div class="empty-icon">
                    ∑
                </div>

                <h3>
                    Chưa có Payroll Journal
                </h3>

                <p>
                    Chỉ finalized Payroll Run có policy và mappings hợp lệ
                    mới có thể tạo journal.
                </p>
            </div>

        @else

            {{-- ========================================================
                JOURNALS TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Journal</th>
                            <th>Payroll source</th>
                            <th>Posting date</th>
                            <th>Lines</th>
                            <th>Debit / Credit</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->items as $j)
                            <tr>

                                {{-- Journal --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $j['journal_number'] }}
                                    </strong>

                                    <span>
                                        Calculation v{{ $j['calculation_version'] }}
                                    </span>
                                </td>


                                {{-- Payroll Source --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $j['run_number'] }}
                                    </strong>

                                    <span>
                                        {{ $j['period_code'] }}
                                    </span>
                                </td>


                                {{-- Posting Date --}}
                                <td>
                                    {{ $j['posting_date'] }}
                                </td>


                                {{-- Lines --}}
                                <td>
                                    {{ $j['line_count'] }}
                                </td>


                                {{-- Debit / Credit --}}
                                <td class="money">
                                    {{ number_format($j['total_debit_minor']) }}
                                    /
                                    {{ number_format($j['total_credit_minor']) }}

                                    <small>
                                        {{ $j['currency'] }}
                                        ·
                                        minor units
                                    </small>
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $j['status'] }}">
                                        {{ $j['status'] }}
                                    </span>
                                </td>


                                {{-- Actions --}}
                                <td>
                                    <a
                                        class="row-action"
                                        href="{{ route('admin.payroll.accounting.journals.show', [
                                            'journal' => $j['id'],
                                            'company_id' => $c,
                                        ]) }}"
                                    >
                                        Chi tiết →
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

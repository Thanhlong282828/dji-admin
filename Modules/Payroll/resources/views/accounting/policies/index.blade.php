@extends('layouts.admin')

@section('title', 'Accounting Policies')

@section('header_action')
    @can('payroll.accounting.policy.manage')
        <a
            class="button"
            href="{{ route('admin.payroll.accounting.policies.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Tạo policy
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
            Accounting Policies
        </span>
                 <i>/</i>

         <span>
            Payroll/resources/views/policies/index
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Accounting control foundation
        </span>

        <h2>
            Chính sách hạch toán theo phiên bản
        </h2>

        <p>
            Quản trị currency, ngày hiệu lực và chiến lược tổng hợp.
            Journal luôn khóa chính xác policy version đã resolve
            tại ngày kết thúc kỳ lương.
        </p>

        <div class="hero-actions">
            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.mappings.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Accounting mappings
            </a>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.accounting.journals.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Payroll journals
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
            Tìm policy

            <input
                name="search"
                value="{{ $f['search'] }}"
                placeholder="Code hoặc tên…"
            >
        </label>

        <label>
            Trạng thái

            <select name="status">
                <option value="">
                    Tất cả
                </option>

                @foreach([
                    'draft',
                    'active',
                    'inactive',
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
        ACCOUNTING POLICIES
    ================================================================ --}}
    <section class="table-shell">

        @if($vm->items === [])

            {{-- ========================================================
                EMPTY STATE
            ======================================================== --}}
            <div class="empty-block">
                <div class="empty-icon">
                    ◆
                </div>

                <h3>
                    Chưa có Accounting Policy
                </h3>

                <p>
                    Production không được seed policy.
                    Finance cần tạo policy và mapping có hiệu lực
                    trước khi lập journal.
                </p>
            </div>

        @else

            {{-- ========================================================
                POLICIES TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Policy</th>
                            <th>Version</th>
                            <th>Currency</th>
                            <th>Hiệu lực</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->items as $p)
                            <tr>

                                {{-- Policy --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $p['code'] }}
                                    </strong>

                                    <span>
                                        {{ $p['name'] }}
                                    </span>
                                </td>


                                {{-- Version --}}
                                <td>
                                    v{{ $p['version'] }}
                                </td>


                                {{-- Currency --}}
                                <td>
                                    {{ $p['currency'] }}
                                </td>


                                {{-- Effective Period --}}
                                <td>
                                    {{ $p['effective_from'] }}
                                    →
                                    {{ $p['effective_to'] ?? 'Open' }}
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $p['status'] }}">
                                        {{ $p['status'] }}
                                    </span>
                                </td>


                                {{-- Actions --}}
                                <td>
                                    <a
                                        class="row-action"
                                        href="{{ route('admin.payroll.accounting.policies.show', [
                                            'policy' => $p['id'],
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
@extends('layouts.admin')

@section('title', 'Accounting Mappings')

@section('header_action')
    @can('payroll.accounting.mapping.manage')
        <a
            class="button"
            href="{{ route('admin.payroll.accounting.mappings.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Tạo mapping
        </a>
    @endcan
@endsection

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
            Accounting
        </a>

        <i>/</i>

        <span>
            Mappings
        </span>
                <i>/</i>

         <span>
            Payroll/resources/views/mappings/index
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Deterministic resolution
        </span>

        <h2>
            Nguồn Payroll → Tài khoản kế toán
        </h2>

        <p>
            Mapping có hiệu lực theo Policy, source code và dimension.
            Không hard-code account ID trong Payroll engine.
        </p>
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
            Tìm mapping

            <input
                name="search"
                value="{{ $f['search'] }}"
                placeholder="Source type/code…"
            >
        </label>

        <label>
            Status

            <select name="status">
                <option value="">
                    Tất cả
                </option>

                @foreach([
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
        ACCOUNTING MAPPINGS
    ================================================================ --}}
    <section class="table-shell">

        @if($vm->items === [])

            {{-- ========================================================
                EMPTY STATE
            ======================================================== --}}
            <div class="empty-block">
                <div class="empty-icon">
                    ↔
                </div>

                <h3>
                    Chưa có mapping
                </h3>

                <p>
                    Tạo Chart of Accounts và policy trước,
                    sau đó mapping từng source code thực tế.
                </p>
            </div>

        @else

            {{-- ========================================================
                MAPPINGS TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Policy</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->items as $m)
                            <tr>

                                {{-- Source --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $m['source_code'] }}
                                    </strong>

                                    <span>
                                        {{ $m['source_type'] }}
                                    </span>
                                </td>


                                {{-- Policy --}}
                                <td>
                                    {{ $m['policy_code'] }}
                                    ·
                                    v{{ $m['policy_version'] }}
                                </td>


                                {{-- Debit Account --}}
                                <td>
                                    {{ $m['debit_code'] ?? '—' }}
                                </td>


                                {{-- Credit Account --}}
                                <td>
                                    {{ $m['credit_code'] ?? '—' }}
                                </td>


                                {{-- Priority --}}
                                <td>
                                    {{ $m['priority'] }}
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $m['status'] }}">
                                        {{ $m['status'] }}
                                    </span>
                                </td>


                                {{-- Actions --}}
                                <td>
                                    @can('payroll.accounting.mapping.manage')
                                        <a
                                            class="row-action"
                                            href="{{ route('admin.payroll.accounting.mappings.edit', [
                                                'mapping' => $m['id'],
                                                'company_id' => $c,
                                            ]) }}"
                                        >
                                            Sửa →
                                        </a>
                                    @endcan
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @endif

    </section>

@endsection
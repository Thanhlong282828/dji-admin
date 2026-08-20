@extends('layouts.admin')

@section('title', 'Chart of Accounts')

@section('header_action')
    @can('payroll.accounting.policy.manage')
        <a
            class="button"
            href="{{ route('admin.payroll.accounting.accounts.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Tạo account
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
            Chart of Accounts
        </span>
            <i>/</i>

         <span>
            Payroll/resources/views/accounts/index
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Company-scoped ledger foundation
        </span>

        <h2>
            Chart of Accounts
        </h2>

        <p>
            Quản lý account type, normal balance và posting eligibility.
            Hệ thống không seed hoặc hard-code account code production.
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
            Tìm account

            <input
                name="search"
                value="{{ $f['search'] }}"
                placeholder="Code hoặc tên…"
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
        CHART OF ACCOUNTS
    ================================================================ --}}
    <section class="table-shell">

        @if($vm->items === [])

            {{-- ========================================================
                EMPTY STATE
            ======================================================== --}}
            <div class="empty-block">
                <div class="empty-icon">
                    ◈
                </div>

                <h3>
                    Chưa có tài khoản kế toán
                </h3>

                <p>
                    Finance tạo Chart of Accounts phù hợp doanh nghiệp
                    trước khi cấu hình mappings.
                </p>
            </div>

        @else

            {{-- ========================================================
                ACCOUNTS TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Normal balance</th>
                            <th>Currency</th>
                            <th>Postable</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->items as $a)
                            <tr>

                                {{-- Account --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $a['code'] }}
                                    </strong>

                                    <span>
                                        {{ $a['name'] }}
                                    </span>
                                </td>


                                {{-- Account Type --}}
                                <td>
                                    {{ $a['account_type'] }}
                                </td>


                                {{-- Normal Balance --}}
                                <td>
                                    {{ $a['normal_balance'] }}
                                </td>


                                {{-- Currency --}}
                                <td>
                                    {{ $a['currency'] ?? 'Any' }}
                                </td>


                                {{-- Posting Eligibility --}}
                                <td>
                                    {{ $a['allow_posting'] ? 'Yes' : 'No' }}
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $a['status'] }}">
                                        {{ $a['status'] }}
                                    </span>
                                </td>


                                {{-- Actions --}}
                                <td>
                                    @can('payroll.accounting.policy.manage')
                                        <a
                                            class="row-action"
                                            href="{{ route('admin.payroll.accounting.accounts.edit', [
                                                'account' => $a['id'],
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
@extends('layouts.admin')

@section('header_action')
    @can('erp.integration.connection.manage')
        <a
            class="button"
            href="{{ route('admin.payroll.erp.mappings.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Mapping Rule
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.erp.connections.index', [
                'company_id' => $c,
            ]) }}"
        >
            ERP
        </a>

        <i>/</i>

        <span>
            Mapping Rules
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Effective-dated immutable versions
        </span>

        <h2>
            ERP Account Mapping
        </h2>

        <p>
            Accounting Account → ERP External Account.
            Updates supersede the old version;
            existing export snapshots never change.
        </p>
    </section>


    {{-- ================================================================
        ERP MAPPING RULES
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
                    Tạo mapping cho từng postable accounting account.
                </p>
            </div>

        @else

            {{-- ========================================================
                MAPPING TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Connection</th>
                            <th>Accounting Account</th>
                            <th>External Account</th>
                            <th>Version</th>
                            <th>Effective</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->items as $m)
                            <tr>

                                {{-- Connection --}}
                                <td>
                                    {{ $m['connection_name'] }}
                                </td>


                                {{-- Accounting Account --}}
                                <td>
                                    {{ $m['source_account_code'] }}
                                </td>


                                {{-- External Account --}}
                                <td>
                                    {{ $m['external_account_code'] }}
                                </td>


                                {{-- Version --}}
                                <td>
                                    v{{ $m['version'] }}
                                </td>


                                {{-- Effective Period --}}
                                <td>
                                    {{ $m['effective_from'] }}
                                    →
                                    {{ $m['effective_to'] ?? 'Open' }}
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $m['status'] }}">
                                        {{ $m['status'] }}
                                    </span>
                                </td>


                                {{-- Actions --}}
                                <td>
                                    <a
                                        class="row-action"
                                        href="{{ route('admin.payroll.erp.mappings.show', [
                                            'mapping' => $m['id'],
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
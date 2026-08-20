@extends('layouts.admin')

@section('header_action')
    @can('erp.integration.export.create')
        <a
            class="button"
            href="{{ route('admin.payroll.erp.exports.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Tạo ERP Export
        </a>
    @endcan
@endsection

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
            Accounting
        </a>

        <i>/</i>

        <span>
            ERP Integration
        </span>

        <i>/</i>

         <span>
            Payroll/resources/views/erp/exports/index
        </span>

    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Phase 3E-3 · provider-neutral delivery
        </span>

        <h2>
            ERP Export Control Center
        </h2>

        <p>
            Snapshot bất biến từ journal lines, SHA-256 integrity,
            idempotency và maker-checker.
            Generic CSV không gọi ERP bên ngoài.
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


        {{-- Search --}}
        <label>
            Tìm kiếm

            <input
                name="search"
                value="{{ request('search') }}"
                placeholder="Journal hoặc UUID"
            >
        </label>


        {{-- Status --}}
        <label>
            Trạng thái

            <select name="status">
                <option value="">
                    Tất cả
                </option>

                @foreach([
                    'draft',
                    'validated',
                    'exporting',
                    'exported',
                    'failed',
                    'reversed',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(request('status') === $s)
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
            Lọc
        </button>
    </form>


    {{-- ================================================================
        ERP EXPORTS
    ================================================================ --}}
    <section class="table-shell">

        @if($vm->items === [])

            {{-- ========================================================
                EMPTY STATE
            ======================================================== --}}
            <div class="empty-block">
                <div class="empty-icon">
                    ⇄
                </div>

                <h3>
                    Chưa có ERP export
                </h3>

                <p>
                    Chọn một internally-posted journal và connection hợp lệ
                    để tạo snapshot.
                </p>
            </div>

        @else

            {{-- ========================================================
                EXPORT TABLE
            ======================================================== --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Journal</th>
                            <th>Connection</th>
                            <th>Provider</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>External ref</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->items as $e)
                            <tr>

                                {{-- Journal --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $e['journal_number'] }}
                                    </strong>

                                    <span>
                                        {{ $e['uuid'] }}
                                    </span>
                                </td>


                                {{-- Connection --}}
                                <td>
                                    {{ $e['connection_name'] }}
                                </td>


                                {{-- Provider --}}
                                <td>
                                    {{ $e['provider'] }}
                                </td>


                                {{-- Version --}}
                                <td>
                                    v{{ $e['export_version'] }}
                                </td>


                                {{-- Status --}}
                                <td>
                                    <span class="status-pill is-{{ $e['status'] }}">
                                        {{ $e['status'] }}
                                    </span>
                                </td>


                                {{-- External Reference --}}
                                <td>
                                    {{ $e['external_reference'] ?? '—' }}
                                </td>


                                {{-- Actions --}}
                                <td>
                                    <a
                                        class="row-action"
                                        href="{{ route('admin.payroll.erp.exports.show', [
                                            'export' => $e['id'],
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
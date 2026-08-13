@extends('layouts.admin')

@section('title', 'Government Filings')

@section('header_action')
    @can('government.filing.create')
        <a
            class="button"
            href="{{ route('admin.payroll.government-filings.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Create Filing
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.annual-tax.index', [
                'company_id' => $c,
            ]) }}"
        >
            Annual PIT
        </a>

        <i>/</i>

        <span>
            Government Filings
        </span>
    </nav>


    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            No external transmission
        </span>

        <h2>
            Government Filing Foundation
        </h2>

        <p>
            Immutable Annual Tax snapshots, deterministic XML,
            maker-checker approval and locally simulated submission evidence.
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


        {{-- Tax Year --}}
        <label>
            Tax year

            <input
                name="tax_year"
                inputmode="numeric"
                value="{{ request('tax_year') }}"
                placeholder="2026"
            >
        </label>


        {{-- Status --}}
        <label>
            Status

            <select name="status">
                <option value="">
                    All statuses
                </option>

                @foreach([
                    'draft',
                    'generated',
                    'validated',
                    'approved',
                    'submitted',
                    'accepted',
                    'rejected',
                ] as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ ucfirst($status) }}
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
        GOVERNMENT FILINGS TABLE
    ================================================================ --}}
    <section class="table-shell">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Annual reference</th>
                        <th>Year</th>
                        <th>Jurisdiction</th>
                        <th>Type</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($vm->items as $filing)
                        <tr>

                            {{-- Annual Reference --}}
                            <td>
                                <strong>
                                    {{ $filing['annual_reference'] }}
                                </strong>

                                <small>
                                    {{ $filing['uuid'] }}
                                </small>
                            </td>


                            {{-- Tax Year --}}
                            <td>
                                {{ $filing['tax_year'] }}
                            </td>


                            {{-- Jurisdiction --}}
                            <td>
                                {{ $filing['jurisdiction'] }}
                            </td>


                            {{-- Filing Type --}}
                            <td>
                                {{ $filing['filing_type'] }}
                            </td>


                            {{-- Version --}}
                            <td>
                                v{{ $filing['version'] }}
                            </td>


                            {{-- Status --}}
                            <td>
                                <span class="status-pill is-{{ $filing['status'] }}">
                                    {{ $filing['status'] }}
                                </span>
                            </td>


                            {{-- Created --}}
                            <td>
                                {{ $filing['created_at'] }}
                            </td>


                            {{-- Actions --}}
                            <td>
                                <a
                                    class="row-action"
                                    href="{{ route('admin.payroll.government-filings.show', [
                                        'filing' => $filing['id'],
                                        'company_id' => $c,
                                    ]) }}"
                                >
                                    Detail →
                                </a>
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-block">
                                    <span class="empty-icon">
                                        G
                                    </span>

                                    <h3>
                                        No Government Filing
                                    </h3>

                                    <p>
                                        Create one from a finalized Annual Tax Reconciliation.
                                        Production submission is not connected.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

@endsection
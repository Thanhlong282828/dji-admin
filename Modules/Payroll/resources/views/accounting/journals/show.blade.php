@extends('layouts.admin')

@php($j = $vm->detail)

@section('title', 'Payroll Journal · ' . $j['journal_number'])

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
            {{ $j['journal_number'] }}
        </span>
                    <i>/</i>

         <span>
            Payroll/resources/views/journals/show
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            @if($j['is_generic_source'])
                {{ $j['source_type'] }} #{{ $j['source_id'] }} · {{ $j['posting_type'] }}
            @else
                {{ $j['run_number'] }} · calculation v{{ $j['calculation_version'] }}
            @endif
        </span>

        <h2>
            {{ $j['journal_number'] }}
        </h2>

        <p>
            @if($j['is_generic_source'])
                Nguồn nghiệp vụ {{ $j['source_type'] }} #{{ $j['source_id'] }} · kỳ {{ $j['period_code'] }}.
                Bút toán cân bằng dùng integer minor units và evidence của nguồn đã lưu.
            @else
                Policy {{ $j['policy_code'] }} · v{{ $j['policy_version'] ?? '' }} · resolution {{ $j['resolution_date'] }}.
                Bút toán dùng integer minor units và exact persisted Payroll source.
            @endif
        </p>


        {{-- ============================================================
            LIFECYCLE ACTIONS
        ============================================================ --}}
        <div class="hero-actions">

            {{-- Draft → Validate --}}
            @if($j['status'] === 'draft')
                @can('payroll.accounting.journal.review')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.mutate', [
                            'journal' => $j['id'],
                            'action' => 'validate',
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Validate
                        </button>
                    </form>
                @endcan
            @endif


            {{-- Validated → Review --}}
            @if($j['status'] === 'validated')
                @can('payroll.accounting.journal.review')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.mutate', [
                            'journal' => $j['id'],
                            'action' => 'review',
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Review
                        </button>
                    </form>
                @endcan
            @endif


            {{-- Reviewed → Approve --}}
            @if($j['status'] === 'reviewed')
                @can('payroll.accounting.journal.approve')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.mutate', [
                            'journal' => $j['id'],
                            'action' => 'approve',
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Approve
                        </button>
                    </form>
                @endcan
            @endif


            {{-- Approved → Internal Post --}}
            @if($j['status'] === 'approved')
                @can('payroll.accounting.journal.post')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.mutate', [
                            'journal' => $j['id'],
                            'action' => 'post',
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Internally post
                        </button>
                    </form>
                @endcan
            @endif


            {{-- Internally Posted → Export --}}
            @if(! $j['is_generic_source'] && $j['status'] === 'internally_posted')
                @can('payroll.accounting.journal.export')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.mutate', [
                            'journal' => $j['id'],
                            'action' => 'export',
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Generate private CSV
                        </button>
                    </form>
                @endcan
            @endif


            {{-- Exported → Download --}}
            @if($j['status'] === 'exported')
                @can('payroll.accounting.journal.download')
                    <a
                        class="button"
                        href="{{ route('admin.payroll.accounting.journals.download', [
                            'journal' => $j['id'],
                            'company_id' => $c,
                        ]) }}"
                    >
                        Download CSV
                    </a>
                @endcan
            @endif

        </div>
    </section>


    {{-- ================================================================
        ERP BOUNDARY WARNING
    ================================================================ --}}
    <div class="integrity-callout warning">
        <strong>
            Internally Posted ≠ Posted to External ERP
        </strong>

        <span>
            Phase này chưa kết nối ERP/accounting API.
            Export chỉ tạo generic private CSV có checksum.
        </span>
    </div>


    {{-- ================================================================
        JOURNAL METRICS
    ================================================================ --}}
    <section class="metric-grid">

        <article class="metric-card">
            <span>
                Status
            </span>

            <strong>
                {{ $j['status'] }}
            </strong>
        </article>


        <article class="metric-card">
            <span>
                Total debit
            </span>

            <strong>
                {{ number_format($j['total_debit_minor']) }}
            </strong>

            <small>
                {{ $j['currency'] }}
                ·
                minor units
            </small>
        </article>


        <article class="metric-card">
            <span>
                Total credit
            </span>

            <strong>
                {{ number_format($j['total_credit_minor']) }}
            </strong>

            <small>
                {{ $j['currency'] }}
                ·
                minor units
            </small>
        </article>


        <article class="metric-card">
            <span>
                Balance
            </span>

            <strong>
                {{
                    $j['total_debit_minor'] === $j['total_credit_minor']
                        ? 'BALANCED'
                        : 'BLOCKED'
                }}
            </strong>
        </article>

    </section>


    {{-- ================================================================
        LIFECYCLE
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Lifecycle
        </span>

        <div class="lifecycle-strip">
            @foreach([
                'draft',
                'validated',
                'reviewed',
                'approved',
                'internally_posted',
                'exported',
            ] as $s)
                <span class="{{ $j['status'] === $s ? 'current' : '' }}">
                    {{ str_replace('_', ' ', $s) }}
                </span>
            @endforeach
        </div>
    </section>


    {{-- ================================================================
        VALIDATION ISSUES
    ================================================================ --}}
    @if($j['issues'] !== [])
        <section class="panel">
            <span class="eyebrow">
                Validation issues
            </span>

            @foreach($j['issues'] as $i)
                <div class="notice danger">
                    <strong>
                        {{ $i['code'] }}
                    </strong>

                    ·
                    {{ $i['message'] }}
                </div>
            @endforeach
        </section>
    @endif


    {{-- ================================================================
        JOURNAL LINES
    ================================================================ --}}
    <section class="table-shell">
        <div class="table-heading">
            <div>
                <span class="eyebrow">
                    Journal lines
                </span>

                <h3>
                    Debit / Credit detail
                </h3>
            </div>

            <span>
                {{ count($j['lines']) }} lines
            </span>
        </div>


        @if($j['lines'] === [])

            {{-- Empty State --}}
            <div class="empty-block">
                <h3>
                    Không có journal lines
                </h3>

                <p>
                    Journal chưa có nguồn hạch toán hợp lệ.
                </p>
            </div>

        @else

            {{-- Journal Lines Table --}}
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Account</th>
                            <th>Description</th>
                            <th>Dimension</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Source</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($j['lines'] as $l)
                            <tr>

                                {{-- Line Number --}}
                                <td>
                                    {{ $l['line_number'] }}
                                </td>


                                {{-- Account --}}
                                <td class="primary-cell">
                                    <strong>
                                        {{ $l['account_code'] }}
                                    </strong>

                                    <span>
                                        {{ $l['account_name'] }}
                                    </span>
                                </td>


                                {{-- Description --}}
                                <td>
                                    {{ $l['description'] }}
                                </td>


                                {{-- Dimension --}}
                                <td>
                                    {{ $l['cost_center'] ?? 'Global' }}

                                    <br>

                                    <small>
                                        {{ $l['organization_unit'] ?? '—' }}
                                    </small>
                                </td>


                                {{-- Debit --}}
                                <td class="money">
                                    {{
                                        $l['debit_minor']
                                            ? number_format($l['debit_minor'])
                                            : '—'
                                    }}
                                </td>


                                {{-- Credit --}}
                                <td class="money">
                                    {{
                                        $l['credit_minor']
                                            ? number_format($l['credit_minor'])
                                            : '—'
                                    }}
                                </td>


                                {{-- Source --}}
                                <td>
                                    {{ $l['source_code'] }}

                                    <br>

                                    <small>
                                        {{ $l['source_count'] }} source rows
                                    </small>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @endif
    </section>


    {{-- ================================================================
        FILE INTEGRITY METADATA
    ================================================================ --}}
    @if($j['file_name'])
        <section class="panel">
            <span class="eyebrow">
                Integrity metadata
            </span>

            <dl class="detail-list">

                <div>
                    <dt>
                        File
                    </dt>

                    <dd>
                        {{ $j['file_name'] }}
                    </dd>
                </div>


                <div>
                    <dt>
                        SHA-256
                    </dt>

                    <dd>
                        <code>
                            {{ $j['checksum'] }}
                        </code>
                    </dd>
                </div>

            </dl>
        </section>
    @endif


    {{-- ================================================================
        CONTROLLED ACTIONS
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Controlled actions
        </span>

        <div class="hero-actions">

            {{-- Reject --}}
            @if(in_array($j['status'], ['draft', 'validated', 'reviewed'], true))
                @can('payroll.accounting.journal.approve')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.mutate', [
                            'journal' => $j['id'],
                            'action' => 'reject',
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <input
                            name="reason"
                            required
                            maxlength="1000"
                            placeholder="Lý do reject"
                        >

                        <button
                            class="button danger"
                            type="submit"
                        >
                            Reject
                        </button>
                    </form>
                @endcan
            @endif


            {{-- Reversal --}}
            @if(
                in_array($j['status'], ['internally_posted', 'exported'], true)
                && ! $j['reversed_by_journal_id']
            )
                @can('payroll.accounting.journal.reverse')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.accounting.journals.reverse', [
                            'journal' => $j['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $c }}"
                        >

                        <input
                            type="hidden"
                            name="idempotency_key"
                            value="{{ (string) Illuminate\Support\Str::uuid() }}"
                        >

                        <input
                            name="reason"
                            required
                            maxlength="1000"
                            placeholder="Lý do reversal"
                        >

                        <button
                            class="button secondary"
                            type="submit"
                        >
                            Create reversal
                        </button>
                    </form>
                @endcan
            @endif

        </div>
    </section>


    {{-- ================================================================
        ACTIVITY HISTORY
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Activity history
        </span>

        @forelse($j['history'] as $h)
            <div class="history-row">
                <strong>
                    {{ $h['event'] }}
                </strong>

                <span>
                    {{ $h['description'] }}
                </span>

                <time>
                    {{ $h['created_at'] }}
                </time>
            </div>

        @empty
            <p class="empty-state">
                Chưa có history.
            </p>
        @endforelse
    </section>

@endsection

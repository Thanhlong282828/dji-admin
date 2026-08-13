@extends('layouts.admin')

@section('title', 'Payroll Adjustment Detail')

@section('content')
    @php($a = $vm->data['adjustment'])

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.adjustments.index', [
                'company_id' => $c,
            ]) }}"
        >
            Adjustments
        </a>

        <i>/</i>

        <span>
            {{ $a['adjustment_number'] }}
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Delta-only payroll control
        </span>

        <h2>
            {{ $a['adjustment_number'] }}
        </h2>

        <p>
            {{ $a['employee_code'] }}
            ·
            {{ $a['full_name'] }}
            ·
            source {{ $a['period_code'] }}
            /
            calculation v{{ $a['source_calculation_version'] }}
        </p>

        <div class="hero-actions">
            <span class="status-pill is-{{ $a['status'] }}">
                {{ $a['status'] }}
            </span>
        </div>
    </section>


    {{-- ================================================================
        FLASH MESSAGE
    ================================================================ --}}
    @if(session('success'))
        <div class="notice success">
            {{ session('success') }}
        </div>
    @endif


    {{-- ================================================================
        VALIDATION ERRORS
    ================================================================ --}}
    @if($errors->any())
        <div class="notice danger">
            {{ $errors->first() }}
        </div>
    @endif


    {{-- ================================================================
        ADJUSTMENT SUMMARY
    ================================================================ --}}
    <section class="stats-grid">

        <article class="stat-card">
            <span>
                Reason
            </span>

            <strong>
                {{ $a['reason_name'] }}
            </strong>

            <small>
                {{ $a['adjustment_type'] }}
            </small>
        </article>


        <article class="stat-card">
            <span>
                Source
            </span>

            <strong>
                #{{ $a['source_payroll_item_id'] }}
            </strong>

            <small>
                Run #{{ $a['source_payroll_run_id'] }}
            </small>
        </article>


        <article class="stat-card">
            <span>
                Maker
            </span>

            <strong>
                {{ $a['creator_name'] }}
            </strong>

            <small>
                Checker: {{ $a['approver_name'] ?? '—' }}
            </small>
        </article>


        <article class="stat-card">
            <span>
                Target period
            </span>

            <strong>
                {{ $a['target_payment_period_id'] ?? 'Not assigned' }}
            </strong>

            <small>
                {{ $a['requested_effective_date'] }}
            </small>
        </article>

    </section>


    {{-- ================================================================
        LIFECYCLE ACTIONS
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Lifecycle actions
        </span>

        <div class="hero-actions">

            {{-- ========================================================
                CALCULATE CORRECTION DELTA
            ======================================================== --}}
            @if(in_array($a['status'], ['draft', 'calculated', 'rejected'], true))
                @can('payroll.adjustment.calculate')
                    <details>
                        <summary class="button">
                            Calculate correction delta
                        </summary>

                        <form
                            class="form-grid"
                            method="POST"
                            action="{{ route('admin.payroll.adjustments.mutate', [
                                'adjustment' => $a['id'],
                                'action' => 'calculate',
                            ]) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="company_id"
                                value="{{ $c }}"
                            >

                            @foreach([
                                'gross' => 'Gross',
                                'employee_insurance' => 'Employee insurance',
                                'employer_insurance' => 'Employer insurance',
                                'personal_income_tax' => 'PIT',
                                'net' => 'Net',
                            ] as $k => $label)
                                <label>
                                    {{ $label }} corrected minor

                                    <input
                                        type="number"
                                        name="corrected_{{ $k }}_minor"
                                        required
                                    >
                                </label>
                            @endforeach

                            <button
                                class="button"
                                type="submit"
                            >
                                Calculate atomically
                            </button>
                        </form>
                    </details>
                @endcan
            @endif


            {{-- ========================================================
                STANDARD STATE TRANSITIONS
            ======================================================== --}}
            @php
                $actions = [
                    'calculated' => [
                        'validate',
                        'payroll.adjustment.calculate',
                    ],
                    'validated' => [
                        'submit',
                        'payroll.adjustment.update',
                    ],
                    'submitted' => [
                        'review',
                        'payroll.adjustment.review',
                    ],
                    'reviewed' => [
                        'approve',
                        'payroll.adjustment.approve',
                    ],
                    'approved' => [
                        'apply',
                        'payroll.adjustment.apply',
                    ],
                ];
            @endphp

            @if(isset($actions[$a['status']]))
                @can($actions[$a['status']][1])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.adjustments.mutate', [
                            'adjustment' => $a['id'],
                            'action' => $actions[$a['status']][0],
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
                            {{ ucfirst($actions[$a['status']][0]) }}
                        </button>
                    </form>
                @endcan
            @endif


            {{-- ========================================================
                REJECT
            ======================================================== --}}
            @if(in_array($a['status'], ['submitted', 'reviewed'], true))
                @can('payroll.adjustment.approve')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.adjustments.mutate', [
                            'adjustment' => $a['id'],
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
                            maxlength="2000"
                            placeholder="Reject reason"
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

        </div>
    </section>


    {{-- ================================================================
        HISTORICAL VS CORRECTED
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Historical vs corrected
        </span>

        <h3>
            Delta components
        </h3>

        @if($vm->data['components'] === [])
            <div class="empty-block">
                <h3>
                    Adjustment chưa calculate
                </h3>

                <p>
                    Nhập correction totals để tạo delta.
                    Giá trị dùng integer minor units, không float.
                </p>
            </div>
        @else
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Historical/effective</th>
                            <th>Corrected</th>
                            <th>Delta</th>
                            <th>Currency</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($vm->data['components'] as $x)
                            <tr>
                                <td>
                                    {{ $x['component_code'] }}
                                </td>

                                <td class="money">
                                    {{ number_format($x['historical_amount_minor']) }}
                                </td>

                                <td class="money">
                                    {{ number_format($x['corrected_amount_minor']) }}
                                </td>

                                <td class="money">
                                    {{ ($x['delta_amount_minor'] >= 0 ? '+' : '') . number_format($x['delta_amount_minor']) }}
                                </td>

                                <td>
                                    {{ $x['currency'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>


    {{-- ================================================================
        VALIDATION ISSUES
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Validation
        </span>

        @forelse($vm->data['issues'] as $i)
            <div class="notice {{ $i['is_blocking'] ? 'danger' : '' }}">
                <strong>
                    {{ $i['code'] }}
                </strong>

                —
                {{ $i['safe_message'] }}
            </div>
        @empty
            <p>
                Không có validation issue.
            </p>
        @endforelse
    </section>


    {{-- ================================================================
        DOWNSTREAM INTENTS
    ================================================================ --}}
    @php
        $intents = collect($vm->data['intents']);

        $paymentIntent = $intents->first(
            fn ($x) => in_array(
                $x['intent_type'],
                ['payment', 'payment_eligible'],
                true
            )
        );

        $accountingIntent = $intents->first(
            fn ($x) => in_array(
                $x['intent_type'],
                ['accounting', 'accounting_eligible'],
                true
            )
        );

        $statementIntent = $intents->firstWhere(
            'intent_type',
            'statement'
        );
    @endphp


    {{-- ================================================================
        REAL DOWNSTREAM ARTIFACTS
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Real downstream artifacts
        </span>

        <h3>
            Payment, Accounting & Statement
        </h3>

        <div class="stats-grid">

            {{-- ========================================================
                PAYMENT INSTRUCTION
            ======================================================== --}}
            <article class="stat-card">
                <span>
                    Payment instruction
                </span>

                <strong>
                    {{
                        $paymentIntent['status']
                        ?? ($a['status'] === 'applied'
                            ? 'not ready'
                            : 'pending apply')
                    }}
                </strong>

                @if($vm->data['payment'])
                    <small>
                        {{ $vm->data['payment']['batch_number'] }}
                        ·
                        {{ number_format($vm->data['payment']['amount']) }}
                        {{ $vm->data['payment']['currency'] }}
                        ·
                        {{ $vm->data['payment']['source_type'] }}
                    </small>

                    <a
                        class="row-action"
                        href="{{ route('admin.payroll.payment-batches.show', [
                            'batch' => $vm->data['payment']['batch_id'],
                            'company_id' => $c,
                        ]) }}"
                    >
                        Open draft batch →
                    </a>

                @elseif($vm->data['recovery'])
                    <small>
                        Negative delta uses recovery;
                        no bank transfer.
                    </small>

                @elseif($paymentIntent)
                    <small>
                        {{
                            $paymentIntent['failure_code']
                            ?? 'Waiting for verified payment account.'
                        }}
                    </small>
                @endif


                {{-- Retry Payment --}}
                @if(
                    $paymentIntent
                    && in_array(
                        $paymentIntent['status'],
                        ['blocked', 'failed', 'eligible'],
                        true
                    )
                )
                    @can('payroll.adjustment.downstream.retry')
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.adjustments.downstream.retry', [
                                'adjustment' => $a['id'],
                                'type' => 'payment',
                            ]) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="company_id"
                                value="{{ $c }}"
                            >

                            <button
                                class="button secondary"
                                type="submit"
                            >
                                Retry payment
                            </button>
                        </form>
                    @endcan
                @endif
            </article>


            {{-- ========================================================
                ACCOUNTING JOURNAL
            ======================================================== --}}
            <article class="stat-card">
                <span>
                    Accounting Journal
                </span>

                <strong>
                    {{
                        $accountingIntent['status']
                        ?? ($a['status'] === 'applied'
                            ? 'not ready'
                            : 'pending apply')
                    }}
                </strong>

                @if($vm->data['journal'])
                    <small>
                        {{ $vm->data['journal']['journal_number'] }}
                        ·
                        Debit {{ number_format($vm->data['journal']['total_debit_minor']) }}
                        /
                        Credit {{ number_format($vm->data['journal']['total_credit_minor']) }}
                        ·
                        {{ $vm->data['journal']['status'] }}
                    </small>

                    <a
                        class="row-action"
                        href="{{ route('admin.payroll.accounting.journals.show', [
                            'journal' => $vm->data['journal']['id'],
                            'company_id' => $c,
                        ]) }}"
                    >
                        Open Journal →
                    </a>

                @elseif($accountingIntent)
                    <small>
                        {{
                            $accountingIntent['failure_code']
                            ?? 'Waiting for Adjustment accounting mappings.'
                        }}
                    </small>
                @endif


                {{-- Retry Accounting --}}
                @if(
                    $accountingIntent
                    && in_array(
                        $accountingIntent['status'],
                        ['blocked', 'failed', 'eligible'],
                        true
                    )
                )
                    @can('payroll.adjustment.downstream.retry')
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.adjustments.downstream.retry', [
                                'adjustment' => $a['id'],
                                'type' => 'accounting',
                            ]) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="company_id"
                                value="{{ $c }}"
                            >

                            <button
                                class="button secondary"
                                type="submit"
                            >
                                Retry accounting
                            </button>
                        </form>
                    @endcan
                @endif
            </article>


            {{-- ========================================================
                ADJUSTMENT STATEMENT
            ======================================================== --}}
            <article class="stat-card">
                <span>
                    Adjustment Statement
                </span>

                <strong>
                    {{
                        $statementIntent['status']
                        ?? ($a['status'] === 'applied'
                            ? 'not ready'
                            : 'pending apply')
                    }}
                </strong>

                @if(
                    $vm->data['statement']
                    && $vm->data['statement']['checksum']
                )
                    <small>
                        {{ $vm->data['statement']['file_name'] }}
                        ·
                        {{ number_format($vm->data['statement']['file_size']) }} bytes
                        ·
                        SHA-256
                        {{ substr($vm->data['statement']['checksum'], 0, 12) }}…
                    </small>

                    @can('payroll.adjustment.statement.download')
                        <a
                            class="row-action"
                            href="{{ route('admin.payroll.adjustments.statement.download', [
                                'adjustment' => $a['id'],
                                'company_id' => $c,
                            ]) }}"
                        >
                            Secure download →
                        </a>
                    @endcan

                @elseif($statementIntent)
                    <small>
                        {{
                            $statementIntent['failure_code']
                            ?? 'Waiting for generation.'
                        }}
                    </small>
                @endif


                {{-- Retry Statement --}}
                @if(
                    $statementIntent
                    && in_array(
                        $statementIntent['status'],
                        ['blocked', 'failed', 'eligible'],
                        true
                    )
                )
                    @can('payroll.adjustment.downstream.retry')
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.adjustments.downstream.retry', [
                                'adjustment' => $a['id'],
                                'type' => 'statement',
                            ]) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="company_id"
                                value="{{ $c }}"
                            >

                            <button
                                class="button secondary"
                                type="submit"
                            >
                                Retry statement
                            </button>
                        </form>
                    @endcan
                @endif
            </article>

        </div>


        {{-- ============================================================
            MAKER-CHECKER NOTICE
        ============================================================ --}}
        <div class="integrity-callout">
            <strong>
                Maker-checker boundary
            </strong>

            <span>
                Adjustment applied does not mean Payment released
                or Accounting internally posted.
                Both artifacts remain in their controlled draft lifecycle.
            </span>
        </div>
    </section>


    {{-- ================================================================
        IMMUTABLE AUDIT TRAIL
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Immutable audit trail
        </span>

        <div class="timeline">
            @foreach($vm->data['history'] as $h)
                <article>
                    <strong>
                        {{ $h['action'] }}
                    </strong>

                    <span>
                        {{ $h['from_status'] ?? 'new' }}
                        →
                        {{ $h['to_status'] }}
                        ·
                        {{ $h['actor_name'] }}
                        ·
                        {{ $h['created_at'] }}
                    </span>

                    @if($h['safe_reason'])
                        <p>
                            {{ $h['safe_reason'] }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>


    {{-- ================================================================
        IMPORTANT INTEGRITY NOTICE
    ================================================================ --}}
    <section class="notice">
        <strong>
            Important:
        </strong>

        Adjustment does not modify finalized historical payroll.
        Applied adjustment is immutable.
        Negative adjustment is not a negative bank transfer.
    </section>

@endsection
@extends('layouts.admin')

@section('title', 'Annual PIT Detail')

@section('content')
    @php
    ($a = $data['reconciliation'])
@endphp

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.annual-tax.index', [
                'company_id' => $c,
            ]) }}"
        >
            Annual Tax
        </a>

        <i>/</i>

        <span>
            {{ $a['reference'] }}
        </span>
          <i>/</i>

         <span>
            Payroll/resources/views/annual-tax/show
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Version {{ $a['version'] }}
            ·
            {{ $a['status'] }}
            ·
            {{ $a['result_type'] ?? 'not calculated' }}
        </span>

        <h1>
            {{ $a['reference'] }}
            ·
            {{ $a['employee_code'] }}
            {{ $a['full_name'] }}
        </h1>

        <p>
            {{ $a['tax_year'] }}
            ·
            {{ $a['jurisdiction'] }}
            ·
            {{ $a['currency'] }}
            ·
            Employer-scoped; not government filed.
        </p>

        <p>
            Integrity:

            <strong>
                {{
                    ($a['source_checksum'] ?? null)
                        ? 'checksum ' . substr($a['source_checksum'], 0, 10) . '…'
                        : 'not collected'
                }}
            </strong>
        </p>
    </section>


    {{-- ================================================================
        BLOCKING ISSUES
    ================================================================ --}}
    @php
        $blocking = collect($data['issues'])
            ->where('severity', 'blocking')
            ->whereNull('resolved_at')
            ->count();
    @endphp

    @if(($a['result_type'] ?? null) === 'blocked' || $blocking > 0)
        <div class="notice danger">
            <strong>
                Blocked reconciliation
            </strong>

            <p>
                {{ $blocking }} blocking issue(s) are visible on this page.
                Submit, approve and finalize remain server-blocked.
            </p>
        </div>
    @endif


    {{-- ================================================================
        LIFECYCLE ACTIONS
    ================================================================ --}}
    @php
        $map = [
            'draft' => [
                'collect',
                'calculate',
            ],
            'calculated' => [
                'validate',
                'calculate',
            ],
            'validated' => [
                'submit',
            ],
            'submitted' => [
                'review',
                'reject',
            ],
            'reviewed' => [
                'approve',
                'reject',
            ],
            'approved' => [
                'finalize',
                'reject',
            ],
            'finalized' => [
                'supersede',
            ],
        ];
    @endphp

    <div class="action-cluster">
        @foreach($map[$a['status']] ?? [] as $action)

            @continue(
                $blocking > 0
                && in_array($action, ['submit', 'approve', 'finalize'], true)
            )

            @continue(
                in_array($action, ['review', 'approve'], true)
                && (int) $a['created_by'] === (int) auth()->id()
            )

            @continue(
                $action === 'approve'
                && (int) ($a['reviewed_by'] ?? 0) === (int) auth()->id()
            )

            @continue(
                $action === 'finalize'
                && (int) ($a['approved_by'] ?? 0) === (int) auth()->id()
            )

            @can('payroll.annual_tax.' . $action)
                <form
                    method="POST"
                    action="{{ route('admin.payroll.annual-tax.' . $action, [
                        'reconciliation' => $a['id'],
                    ]) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="company_id"
                        value="{{ $c }}"
                    >

                    @if(in_array($action, ['reject', 'supersede'], true))
                        <input
                            name="reason"
                            required
                            maxlength="1000"
                            placeholder="Required reason"
                        >
                    @endif

                    @if($action === 'supersede')
                        <input
                            type="hidden"
                            name="reason_code"
                            value="correction"
                        >

                        <input
                            type="hidden"
                            name="idempotency_key"
                            value="{{ (string) Illuminate\Support\Str::uuid() }}"
                        >
                    @endif

                    <button
                        class="button"
                        type="submit"
                    >
                        {{ ucfirst($action) }}
                    </button>
                </form>
            @endcan

        @endforeach
    </div>


    {{-- ================================================================
        RECONCILIATION SUMMARY
    ================================================================ --}}
    <section class="stats-grid">
        @foreach([
            'gross_income_minor' => 'Gross income',
            'taxable_income_minor' => 'Taxable income',
            'employee_insurance_deduction_minor' => 'Insurance',
            'personal_deduction_minor' => 'Personal deduction',
            'dependent_deduction_minor' => 'Dependent deduction',
            'final_tax_liability_minor' => 'Final liability',
            'pit_withheld_minor' => 'PIT withheld',
            'additional_tax_payable_minor' => 'Additional payable',
            'tax_refund_minor' => 'Expected refund',
        ] as $k => $label)
            <article class="stat-card">
                <span>
                    {{ $label }}
                </span>

                <strong>
                    {{ number_format($a[$k]) }}
                </strong>

                <small>
                    {{ $a['currency'] }}
                </small>
            </article>
        @endforeach
    </section>


    {{-- ================================================================
        IMMUTABLE SOURCES
    ================================================================ --}}
    <h2>
        Immutable sources
    </h2>

    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Type/reference</th>
                    <th>Period/attribution</th>
                    <th>Gross</th>
                    <th>Taxable</th>
                    <th>PIT</th>
                    <th>Version</th>
                </tr>
            </thead>

            <tbody>
                @forelse($data['sources'] as $s)
                    <tr>
                        <td>
                            {{ $s['source_type'] }}

                            <br>

                            {{ $s['source_reference'] }}
                        </td>

                        <td>
                            {{ $s['source_period_year'] }}
                            -
                            {{ $s['source_period_month'] }}
                            /
                            tax {{ $s['tax_attribution_year'] }}
                        </td>

                        <td>
                            {{ number_format($s['gross_income_minor']) }}
                        </td>

                        <td>
                            {{ number_format($s['taxable_income_minor']) }}
                        </td>

                        <td>
                            {{ number_format($s['pit_withheld_minor']) }}
                        </td>

                        <td>
                            {{ $s['source_calculation_version'] }}
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="6">
                            No persisted source.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $data['sources_page']->appends(
        request()->except('sources_page')
    )->links() }}


    {{-- ================================================================
        VALIDATION ISSUES
    ================================================================ --}}
    <h2>
        Validation
    </h2>

    @forelse($data['issues'] as $i)
        <div class="notice {{ $i['severity'] === 'blocking' ? 'danger' : '' }}">
            <strong>
                {{ $i['issue_code'] }}
                ·
                {{ $i['validation_stage'] ?? 'source_collection' }}
            </strong>

            <p>
                {{ $i['safe_message'] }}
            </p>
        </div>

    @empty
        <p>
            No validation issue.
        </p>
    @endforelse

    {{ $data['issues_page']->appends(
        request()->except('issues_page')
    )->links() }}


    {{-- ================================================================
        HISTORY
    ================================================================ --}}
    <h2>
        History
    </h2>

    @forelse($data['history'] as $h)
        <div class="panel">
            <strong>
                {{ $h['action'] }}
            </strong>

            ·
            {{ $h['from_status'] ?? '—' }}
            →
            {{ $h['to_status'] }}
            ·
            {{ $h['created_at'] }}

            @if($h['safe_reason'] ?? null)
                <p>
                    {{ $h['safe_reason'] }}
                </p>
            @endif
        </div>

    @empty
        <p>
            No history.
        </p>
    @endforelse

    {{ $data['history_page']->appends(
        request()->except('history_page')
    )->links() }}


    {{-- ================================================================
        FINALIZED DELIVERY
    ================================================================ --}}
    @if($a['status'] === 'finalized')
        <section class="panel">
            <span class="eyebrow">
                Private delivery · Phase 3E-2D
            </span>

            <h2>
                Annual Tax Statement
            </h2>

            <p>
                PDF được tạo duy nhất từ immutable reconciliation snapshots,
                sources và settlement result.
                Storage path không xuất hiện trên giao diện.
            </p>


            {{-- ========================================================
                DELIVERY ACTIONS
            ======================================================== --}}
            <div class="action-cluster">

                {{-- Generate Statement --}}
                @can('payroll.annual_tax.statement.generate')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.annual-tax.statement.generate', [
                            'reconciliation' => $a['id'],
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
                            Generate private PDF
                        </button>
                    </form>
                @endcan


                {{-- Payment Eligibility --}}
                @can('payroll.annual_tax.payment.create')
                    @if($a['result_type'] === 'payable')
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.annual-tax.payment-intent', [
                                'reconciliation' => $a['id'],
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
                                Create payment eligibility
                            </button>
                        </form>
                    @endif
                @endcan


                {{-- Refund Obligation --}}
                @can('payroll.annual_tax.refund.create')
                    @if($a['result_type'] === 'refund')
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.annual-tax.refund-intent', [
                                'reconciliation' => $a['id'],
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
                                Create refund obligation
                            </button>
                        </form>
                    @endif
                @endcan


                {{-- Settlement Journal --}}
                @can('payroll.annual_tax.accounting.create')
                    @if($a['result_type'] !== 'balanced')
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.annual-tax.settlement-journal', [
                                'reconciliation' => $a['id'],
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
                                Create balanced journal
                            </button>
                        </form>
                    @endif
                @endcan


                {{-- Retry Downstream --}}
                @can('payroll.annual_tax.downstream.retry')
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.annual-tax.downstream.retry', [
                            'reconciliation' => $a['id'],
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
                            Retry incomplete delivery
                        </button>
                    </form>
                @endcan

            </div>


            {{-- ========================================================
                STATEMENT DOCUMENTS
            ======================================================== --}}
            @forelse($data['documents'] as $document)
                <article class="integrity-callout">
                    <strong>
                        Statement v{{ $document['document_version'] }}
                    </strong>

                    <span>
                        {{ $document['mime_type'] }}
                        ·
                        {{ number_format($document['file_size']) }} bytes
                        ·
                        SHA-256 {{ substr($document['checksum'], 0, 12) }}…
                    </span>

                    @can('payroll.annual_tax.statement.download')
                        <a
                            class="button secondary"
                            href="{{ route('admin.payroll.annual-tax.statement.download', [
                                'reconciliation' => $a['id'],
                                'document' => $document['id'],
                                'company_id' => $c,
                            ]) }}"
                        >
                            Secure download
                        </a>
                    @endcan
                </article>

            @empty
                <p>
                    No statement generated.
                </p>
            @endforelse

        </section>
    @endif


    {{-- ================================================================
        DOWNSTREAM EXECUTION
    ================================================================ --}}
    <h2>
        Downstream execution
    </h2>

    @forelse($data['intents'] as $i)
        <div class="panel">
            {{ $i['intent_type'] }}:

            <strong>
                {{ $i['status'] }}
            </strong>

            ·
            attempt {{ $i['attempt_count'] }}.
        </div>

    @empty
        <p>
            Downstream eligibility is created only after Finalize.
        </p>
    @endforelse

@endsection
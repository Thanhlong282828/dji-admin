@extends('layouts.admin')

@section('title', 'Chi tiết kỳ lương')

@section('header_action')
    <a
        class="button"
        href="{{ route('admin.payroll.periods.index', [
            'company_id' => $companyId,
        ]) }}"
    >
        ← Danh sách
    </a>
@endsection

@section('content')
    @php($p = $viewModel->detail)

    <div
        class="space-y-6"
        data-workforce-page
        data-refresh-scope="payroll-period-summary"
    >

        {{-- ============================================================
            FLASH MESSAGE
        ============================================================ --}}
        @if(session('success'))
            <div class="notice">
                {{ session('success') }}
            </div>
        @endif


        {{-- ============================================================
            VALIDATION ERRORS
        ============================================================ --}}
        @if($errors->any())
            <div class="notice">
                @foreach($errors->all() as $error)
                    <p>
                        {{ $error }}
                    </p>
                @endforeach
            </div>
        @endif


        {{-- ============================================================
            PAYROLL PERIOD SUMMARY
        ============================================================ --}}
        <section class="panel">
            <span class="eyebrow">
                PAYROLL PERIOD
            </span>

            <h1>
                {{ $p['code'] }}
                —
                {{ $p['name'] }}
            </h1>

            <p>
                {{ $p['start'] }}
                –
                {{ $p['end'] }}
                ·
                Pay {{ $p['pay_date'] }}
                ·
                <strong>
                    {{ strtoupper($p['status']) }}
                </strong>
                ·
                V{{ $p['version'] }}
            </p>

            @if($p['note'])
                <p>
                    {{ $p['note'] }}
                </p>
            @endif
        </section>


        {{-- ============================================================
            PERIOD STATISTICS
        ============================================================ --}}
        <section class="grid gap-4 md:grid-cols-4">

            <article class="panel">
                <small>
                    Snapshots
                </small>

                <strong>
                    {{ $p['snapshot_count'] }}
                </strong>
            </article>

            <article class="panel">
                <small>
                    Payroll Runs
                </small>

                <strong>
                    {{ $p['run_count'] }}
                </strong>
            </article>

            <article class="panel">
                <small>
                    Latest Snapshot
                </small>

                <strong>
                    {{ $p['latest_snapshot_id'] ?? '—' }}
                </strong>
            </article>

            <article class="panel">
                <small>
                    Latest Run
                </small>

                <strong>
                    {{ $p['latest_run_id'] ?? '—' }}
                </strong>
            </article>

        </section>


        {{-- ============================================================
            PAYROLL PERIOD ACTIONS
        ============================================================ --}}
        <section
            class="flex flex-wrap gap-3"
            data-refresh-scope="payroll-period-actions"
        >

            {{-- ========================================================
                DRAFT ACTIONS
            ======================================================== --}}
            @if($p['status'] === 'draft')

                {{-- Edit --}}
                @can('payroll-period.update-record', [$companyId, $p['id']])
                    <a
                        class="button"
                        href="{{ route('admin.payroll.periods.edit', [
                            'period' => $p['id'],
                            'company_id' => $companyId,
                        ]) }}"
                    >
                        Sửa
                    </a>
                @endcan


                {{-- Open Period --}}
                @can('payroll-period.open-record', [$companyId, $p['id']])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.periods.open', [
                            'period' => $p['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Mở kỳ
                        </button>
                    </form>
                @endcan


                {{-- Delete Period --}}
                @can('payroll-period.delete-record', [$companyId, $p['id']])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.periods.destroy', [
                            'period' => $p['id'],
                        ]) }}"
                        onsubmit="return confirm('Xóa kỳ draft này?')"
                    >
                        @csrf
                        @method('DELETE')

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Xóa
                        </button>
                    </form>
                @endcan

            @endif


            {{-- ========================================================
                COLLECTING / REVIEW ACTIONS
            ======================================================== --}}
            @if(in_array($p['status'], ['collecting', 'review'], true))

                {{-- Create Payroll Input --}}
                <a
                    class="button"
                    href="{{ route('admin.payroll.inputs.create', [
                        'company_id' => $companyId,
                        'payroll_period_id' => $p['id'],
                    ]) }}"
                >
                    Tạo Payroll Input
                </a>


                {{-- View Payroll Inputs --}}
                <a
                    class="button"
                    href="{{ route('admin.payroll.inputs.index', [
                        'company_id' => $companyId,
                        'period_id' => $p['id'],
                    ]) }}"
                >
                    Xem Payroll Inputs
                </a>


                {{-- Lock Period --}}
                @can('payroll-period.lock-operational-record', [$companyId, $p['id']])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.periods.lock', [
                            'period' => $p['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Khóa kỳ
                        </button>
                    </form>
                @endcan

            @endif


            {{-- ========================================================
                PAYROLL RUNS
            ======================================================== --}}
            @if(in_array($p['status'], ['review', 'locked', 'processed'], true))
                <a
                    class="button"
                    href="{{ route('admin.payroll.runs.index', [
                        'company_id' => $companyId,
                        'period_id' => $p['id'],
                    ]) }}"
                >
                    Xem Payroll Runs
                </a>
            @endif


            {{-- ========================================================
                CLOSE PERIOD
            ======================================================== --}}
            @if($p['status'] === 'locked')
                @can('payroll-period.close-record', [$companyId, $p['id']])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.periods.close', [
                            'period' => $p['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            Đóng kỳ
                        </button>
                    </form>
                @endcan
            @endif


            {{-- ========================================================
                LATEST SNAPSHOT
            ======================================================== --}}
            @if($p['latest_snapshot_id'])
                <a
                    class="button"
                    href="{{ route('admin.payroll.inputs.show', [
                        'snapshot' => $p['latest_snapshot_id'],
                        'company_id' => $companyId,
                    ]) }}"
                >
                    Xem Snapshot
                </a>
            @endif


            {{-- ========================================================
                LATEST PAYROLL RUN
            ======================================================== --}}
            @if($p['latest_run_id'])
                <a
                    class="button"
                    href="{{ route('admin.payroll.runs.show', [
                        'run' => $p['latest_run_id'],
                        'company_id' => $companyId,
                    ]) }}"
                >
                    Xem Run
                </a>
            @endif

        </section>


        {{-- ============================================================
            PERIOD HISTORY
        ============================================================ --}}
        <section class="panel">
            <h2>
                Lịch sử
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th>
                                Action
                            </th>

                            <th>
                                Transition
                            </th>

                            <th>
                                Actor
                            </th>

                            <th>
                                Version
                            </th>

                            <th>
                                At
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($viewModel->history as $h)
                            <tr>
                                <td>
                                    {{ $h['action'] }}
                                </td>

                                <td>
                                    {{ $h['from_status'] ?? '—' }}
                                    →
                                    {{ $h['to_status'] }}
                                </td>

                                <td>
                                    {{ $h['actor'] }}
                                </td>

                                <td>
                                    {{ $h['version'] }}
                                </td>

                                <td>
                                    {{ $h['created_at'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="5"
                                    class="p-8 text-center"
                                >
                                    Chưa có lịch sử.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
@endsection
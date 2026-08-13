@extends('layouts.admin')

@section('title', 'Payroll Input Detail')

@section('header_action')
    <a
        class="button"
        href="{{ route('admin.payroll.inputs.index', [
            'company_id' => $companyId,
        ]) }}"
    >
        ← Danh sách
    </a>
@endsection

@section('content')
    @php($s = $viewModel->summary)

    <div class="space-y-6">

        {{-- ============================================================
            EMPLOYEE INPUTS ACTION
        ============================================================ --}}
        <p>
            <a
                class="button"
                href="{{ route('admin.payroll.inputs.employees.index', [
                    'snapshot' => $s['id'],
                    'company_id' => $companyId,
                ]) }}"
            >
                Xem toàn bộ Employee Inputs
            </a>
        </p>


        <div
            class="space-y-6"
            data-workforce-page
            data-refresh-scope="payroll-input-summary"
            data-refresh-url="{{ route('admin.payroll.inputs.show', [
                'snapshot' => $s['id'],
                'company_id' => $companyId,
            ]) }}"
            data-company-id="{{ $companyId }}"
            data-entity-id="{{ $s['id'] }}"
            data-record-version="{{ $s['version'] }}"
        >

            {{-- ========================================================
                SNAPSHOT HEADER
            ======================================================== --}}
            <section class="panel">
                <span class="eyebrow">
                    IMMUTABLE SNAPSHOT
                </span>

                <h1>
                    {{ $s['label'] }}
                    ·
                    {{ strtoupper($s['status']) }}
                </h1>

                <p>
                    {{ $s['period'] }}
                    · Captured {{ $s['captured_at'] }}
                    by {{ $s['captured_by'] }}
                </p>

                @if($s['note'])
                    <p>
                        {{ $s['note'] }}
                    </p>
                @endif
            </section>


            {{-- ========================================================
                SNAPSHOT SUMMARY
            ======================================================== --}}
            <section class="grid gap-3 md:grid-cols-4">
                @foreach([
                    'employee_count'      => 'Employees',
                    'attendance_count'    => 'Attendance records',
                    'leave_count'         => 'Leave records',
                    'overtime_count'      => 'Overtime records',
                    'scheduled_minutes'   => 'Scheduled minutes',
                    'worked_minutes'      => 'Worked minutes',
                    'paid_leave_minutes'  => 'Paid leave',
                    'overtime_minutes'    => 'Approved overtime',
                ] as $key => $label)
                    <div class="rounded-xl border border-zinc-800 p-4">
                        <small>
                            {{ $label }}
                        </small>

                        <strong class="block text-xl">
                            {{ number_format($s[$key]) }}
                        </strong>
                    </div>
                @endforeach
            </section>


            {{-- ========================================================
                READINESS
            ======================================================== --}}
            <section class="rounded-xl border border-zinc-800 p-4">
                <h2>
                    Readiness
                </h2>

                @if($s['status'] === 'ready')
                    <strong>
                        Ready for Payroll Run
                    </strong>

                @elseif($s['status'] === 'blocked')
                    <strong>
                        Payroll Input chưa sẵn sàng.
                        Hãy xử lý validation issues hoặc tạo snapshot version mới.
                    </strong>

                @elseif($s['status'] === 'superseded')
                    <strong>
                        Snapshot đã superseded và chỉ còn quyền xem.
                    </strong>

                @else
                    <strong>
                        Snapshot cần được Validate.
                    </strong>
                @endif
            </section>


            {{-- ========================================================
                EMPLOYEE INPUT TABLE
            ======================================================== --}}
            <section class="overflow-x-auto rounded-xl border border-zinc-800">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Organization</th>
                            <th>Position</th>
                            <th>Scheduled</th>
                            <th>Worked</th>
                            <th>Paid Leave</th>
                            <th>Unpaid Leave</th>
                            <th>Overtime</th>
                            <th>Source records</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($viewModel->employees as $row)
                            <tr>
                                <td>
                                    {{ $row['number'] }}
                                    ·
                                    {{ $row['name'] }}
                                </td>

                                <td>
                                    {{ $row['organization'] ?? '—' }}
                                </td>

                                <td>
                                    {{ $row['position'] ?? '—' }}
                                </td>

                                <td>
                                    {{ $row['scheduled'] }}
                                </td>

                                <td>
                                    {{ $row['worked'] }}
                                </td>

                                <td>
                                    {{ $row['paid_leave'] }}
                                </td>

                                <td>
                                    {{ $row['unpaid_leave'] }}
                                </td>

                                <td>
                                    {{ $row['overtime'] }}
                                </td>

                                <td>
                                    A {{ $row['attendance_count'] }}
                                    /
                                    L {{ $row['leave_count'] }}
                                    /
                                    OT {{ $row['overtime_count'] }}
                                </td>

                                <td>
                                    {{ strtoupper($row['status']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    Không có employee input row.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>


            {{-- ========================================================
                VALIDATION ISSUES
            ======================================================== --}}
            <section class="rounded-xl border border-zinc-800 p-4">
                <h2>
                    Validation Issues
                </h2>

                @forelse($viewModel->issues as $issue)
                    <p>
                        <strong>
                            {{ strtoupper($issue['severity']) }}
                            ·
                            {{ $issue['code'] }}
                        </strong>

                        —
                        {{ $issue['message'] }}
                    </p>
                @empty
                    <p>
                        Không có validation issue.
                    </p>
                @endforelse
            </section>


            {{-- ========================================================
                HISTORY
            ======================================================== --}}
            <section class="rounded-xl border border-zinc-800 p-4">
                <h2>
                    History
                </h2>

                @forelse($viewModel->history as $entry)
                    <p>
                        {{ $entry['created_at'] }}
                        ·
                        {{ $entry['action'] }}
                        ·
                        {{ $entry['from_status'] ?? '—' }}
                        →
                        {{ $entry['to_status'] }}
                        ·
                        {{ $entry['actor'] }}
                    </p>
                @empty
                    <p>
                        Chưa có lịch sử.
                    </p>
                @endforelse
            </section>


            {{-- ========================================================
                ACTIONS
            ======================================================== --}}
            <section class="flex flex-wrap gap-3">

                {{-- Validate Snapshot --}}
                @can('payroll-input.validate-record', [$companyId, $s['id']])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.inputs.validate', [
                            'snapshot' => $s['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <input
                            type="hidden"
                            name="idempotency_key"
                            value="{{ (string) \Illuminate\Support\Str::uuid() }}"
                        >

                        <button class="button">
                            Validate persisted snapshot
                        </button>
                    </form>
                @endcan


                {{-- Create New Snapshot Version --}}
                @can('payroll-input.create-version-record', [$companyId, $s['id']])
                    <form
                        method="POST"
                        action="{{ route('admin.payroll.inputs.versions.store', [
                            'snapshot' => $s['id'],
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="company_id"
                            value="{{ $companyId }}"
                        >

                        <input
                            type="hidden"
                            name="idempotency_key"
                            value="{{ (string) \Illuminate\Support\Str::uuid() }}"
                        >

                        <button class="button">
                            Create New Version
                        </button>
                    </form>
                @endcan


                {{-- Payroll Run --}}
                @if($s['run_id'])
                    <a
                        class="button"
                        href="{{ route('admin.payroll.runs.show', [
                            'run' => $s['run_id'],
                            'company_id' => $companyId,
                        ]) }}"
                    >
                        View Payroll Run
                    </a>
                @else
                    @can('payroll-input.create-run-record', [$companyId, $s['id']])
                        <form
                            method="POST"
                            action="{{ route('admin.payroll.inputs.create-run', [
                                'snapshot' => $s['id'],
                            ]) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="company_id"
                                value="{{ $companyId }}"
                            >

                            <input
                                type="hidden"
                                name="idempotency_key"
                                value="{{ (string) \Illuminate\Support\Str::uuid() }}"
                            >

                            <button class="button">
                                Create Payroll Run
                            </button>
                        </form>
                    @endcan
                @endif

            </section>

        </div>
    </div>
@endsection
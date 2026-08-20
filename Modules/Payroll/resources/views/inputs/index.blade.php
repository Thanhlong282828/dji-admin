@extends('layouts.admin')

@section('title', 'Payroll inputs')

@section('header_action')
    @can('payroll-input.generate')
        <a
            class="button"
            href="{{ route('admin.payroll.inputs.create', [
                'company_id' => $companyId,
            ]) }}"
        >
            + Tạo Payroll Input
        </a>
    @endcan
@endsection

@section('content')
    <div
        class="space-y-6"
        data-workforce-page
        data-refresh-scope="payroll-input-list"
        data-refresh-url="{{ request()->fullUrl() }}"
        data-company-id="{{ $companyId }}"
    >

        {{-- ============================================================
            PAGE HEADER
        ============================================================ --}}
        <section class="panel">
            <span class="eyebrow">
                PAYROLL INPUT OPERATIONS
            </span>

            <h1>
                Payroll Input Snapshots
            </h1>
                <i>/</i>

         <span>
            Payroll/resources/views/inputs/index
        </span>

            <p>
                Ảnh chụp bất biến của attendance, leave và overtime đã duyệt.
                Không tính gross/net, thuế, bảo hiểm hoặc thanh toán.
            </p>

            <p>
                Company context: {{ $companyId }}
            </p>
        </section>


        {{-- ============================================================
            FILTERS
        ============================================================ --}}
        <form
            class="grid gap-3 rounded-xl border border-zinc-800 p-4 md:grid-cols-5"
            method="GET"
        >
            <input
                type="hidden"
                name="company_id"
                value="{{ $companyId }}"
            >

            {{-- Payroll Period --}}
            <select name="period_id">
                <option value="">
                    Tất cả kỳ
                </option>

                @foreach($viewModel->periods as $period)
                    <option
                        value="{{ $period['id'] }}"
                        @selected(
                            (string) request('period_id') === (string) $period['id']
                        )
                    >
                        {{ $period['code'] }} · {{ $period['name'] }}
                    </option>
                @endforeach
            </select>


            {{-- Status --}}
            <select name="status">
                <option value="">
                    Tất cả trạng thái
                </option>

                @foreach([
                    'draft',
                    'ready',
                    'blocked',
                    'superseded',
                    'locked',
                ] as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>


            {{-- Version --}}
            <input
                name="version"
                type="number"
                min="1"
                value="{{ request('version') }}"
                placeholder="Version"
            >


            {{-- Date From --}}
            <input
                name="date_from"
                type="date"
                value="{{ request('date_from') }}"
            >


            {{-- Submit --}}
            <button class="button">
                Lọc dữ liệu
            </button>
        </form>


        {{-- ============================================================
            PAYROLL INPUT SNAPSHOTS TABLE
        ============================================================ --}}
        <section class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Snapshot</th>
                        <th>Payroll Period</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Employees</th>
                        <th>Attendance</th>
                        <th>Leave</th>
                        <th>Overtime</th>
                        <th>Blocking</th>
                        <th>Captured</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($viewModel->snapshots as $snapshot)
                        <tr>
                            {{-- Snapshot --}}
                            <td>
                                {{ $snapshot['label'] }}
                            </td>

                            {{-- Payroll Period --}}
                            <td>
                                {{ $snapshot['period'] }}
                            </td>

                            {{-- Version --}}
                            <td>
                                {{ $snapshot['version'] }}
                            </td>

                            {{-- Status --}}
                            <td>
                                <strong>
                                    {{ strtoupper($snapshot['status']) }}
                                </strong>
                            </td>

                            {{-- Employees --}}
                            <td>
                                {{ $snapshot['employee_count'] }}
                            </td>

                            {{-- Attendance --}}
                            <td>
                                {{ $snapshot['attendance_count'] }}
                            </td>

                            {{-- Leave --}}
                            <td>
                                {{ $snapshot['leave_count'] }}
                            </td>

                            {{-- Overtime --}}
                            <td>
                                {{ $snapshot['overtime_count'] }}
                            </td>

                            {{-- Blocking Issues --}}
                            <td>
                                {{ $snapshot['blocking_issues'] }}
                            </td>

                            {{-- Captured --}}
                            <td>
                                {{ $snapshot['captured_at'] }}

                                <br>

                                <small>
                                    {{ $snapshot['captured_by'] }}
                                </small>
                            </td>

                            {{-- Actions --}}
                            <td>
                                <a
                                    class="text-amber-500"
                                    href="{{ route('admin.payroll.inputs.show', [
                                        'snapshot' => $snapshot['id'],
                                        'company_id' => $companyId,
                                    ]) }}"
                                >
                                    Xem
                                </a>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td
                                colspan="11"
                                class="p-10 text-center"
                            >
                                <strong>
                                    Chưa có Payroll Input Snapshot cho công ty này.
                                </strong>

                                <p>
                                    Hãy chọn Payroll Period và tạo snapshot đầu tiên.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

    </div>
@endsection
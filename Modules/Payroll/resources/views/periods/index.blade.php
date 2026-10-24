@extends('layouts.admin')

@section('title', 'Payroll Periods')

@section('header_action')
    @can('payroll-period.create')
        <a
            class="button"
            href="{{ route('admin.payroll.periods.create', [
                'company_id' => $companyId,
            ]) }}"
        >
            + Tạo kỳ lương
        </a>
    @endcan
@endsection

@section('content')
    <div
        class="space-y-6"
        data-workforce-page
        data-refresh-scope="payroll-period-list"
    >

        {{-- ============================================================
            PAGE HEADER
        ============================================================ --}}
        <section class="panel">
            <span class="eyebrow">
                PAYROLL OPERATIONS
            </span>

            <h1>
                Payroll Period
            </h1>
    <i>/</i>

         <span>
            Payroll/resources/views/periods/index
        </span>

            <p>
                Quản lý kỳ thu thập input, khóa xử lý và đóng bảng lương theo Company.
            </p>
        </section>


        {{-- ============================================================
            SUCCESS MESSAGE
        ============================================================ --}}
        @if(session('success'))
            <div class="notice">
                {{ session('success') }}
            </div>
        @endif


        {{-- ============================================================
            FILTERS
        ============================================================ --}}
        <form
            class="panel grid gap-3 md:grid-cols-4"
            method="GET"
        >
            <input
                type="hidden"
                name="company_id"
                value="{{ $companyId }}"
            >

            <input
                name="search"
                placeholder="Tìm code hoặc name"
                value="{{ $filters['search'] }}"
            >

            <select name="status">
                <option value="">
                    Tất cả trạng thái
                </option>

                @foreach([
                    'draft',
                    'collecting',
                    'review',
                    'locked',
                    'processed',
                    'cancelled',
                ] as $status)
                    <option
                        value="{{ $status }}"
                        @selected($filters['status'] === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>

            <input
                type="number"
                name="year"
                placeholder="Năm"
                value="{{ $filters['year'] ?: '' }}"
            >

            <button class="button">
                Lọc
            </button>
        </form>


        {{-- ============================================================
            PAYROLL PERIOD TABLE
        ============================================================ --}}
        <section class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Pay date</th>
                        <th>Status</th>
                        <th>Snapshots</th>
                        <th>Runs</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($viewModel->items as $period)
                        <tr>
                            <td>
                                {{ $period['code'] }}
                            </td>

                            <td>
                                {{ $period['name'] }}
                            </td>

                            <td>
                                {{ $period['start'] }}
                            </td>

                            <td>
                                {{ $period['end'] }}
                            </td>

                            <td>
                                {{ $period['pay_date'] }}
                            </td>

                            <td>
                                <strong>
                                    {{ strtoupper($period['status']) }}
                                </strong>
                            </td>

                            <td>
                                {{ $period['snapshot_count'] }}
                            </td>

                            <td>
                                {{ $period['run_count'] }}
                            </td>

                            <td>
                                {{ $period['created_at'] }}
                            </td>

                            <td>
                                <a
                                    class="text-amber-500"
                                    href="{{ route('admin.payroll.periods.show', [
                                        'period' => $period['id'],
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
                                colspan="10"
                                class="p-10 text-center"
                            >
                                <strong>
                                    Chưa có Payroll Period.
                                </strong>

                                <p>
                                    Tạo kỳ lương thật đầu tiên qua giao diện quản trị.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>


        {{-- ============================================================
            PAGINATION
        ============================================================ --}}
        <nav class="flex justify-between">
            @if($viewModel->page > 1)
                <a
                    class="button"
                    href="{{ request()->fullUrlWithQuery([
                        'page' => $viewModel->page - 1,
                    ]) }}"
                >
                    ← Trước
                </a>
            @else
                <span></span>
            @endif

            @if($viewModel->hasMore)
                <a
                    class="button"
                    href="{{ request()->fullUrlWithQuery([
                        'page' => $viewModel->page + 1,
                    ]) }}"
                >
                    Sau →
                </a>
            @endif
        </nav>

    </div>
@endsection

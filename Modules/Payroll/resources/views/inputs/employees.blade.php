@extends('layouts.admin')

@section('title', 'Payroll Input Employees')

@section('content')
    <div class="space-y-6">

        {{-- ============================================================
            FILTERS
        ============================================================ --}}
        <section class="panel">
            <h1>Employee Input Rows</h1>

            <form
                method="GET"
                class="mt-4 grid gap-3 md:grid-cols-4"
            >
                <input
                    type="hidden"
                    name="company_id"
                    value="{{ $companyId }}"
                >

                <input
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="Employee code or name"
                >

                <select name="status">
                    <option value="">
                        All
                    </option>

                    <option
                        value="ready"
                        @selected($filters['status'] === 'ready')
                    >
                        Ready
                    </option>

                    <option
                        value="blocked"
                        @selected($filters['status'] === 'blocked')
                    >
                        Blocked
                    </option>
                </select>

                <button class="button">
                    Filter
                </button>
            </form>
        </section>


        {{-- ============================================================
            EMPLOYEE INPUT TABLE
        ============================================================ --}}
        <section class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Worked</th>
                        <th>Paid Leave</th>
                        <th>Unpaid Leave</th>
                        <th>Weekday OT</th>
                        <th>Rest-day OT</th>
                        <th>Holiday OT</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($viewModel->items as $row)
                        <tr>
                            <td>
                                {{ $row['number'] }}
                                ·
                                {{ $row['name'] }}
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
                                {{ $row['weekday_ot'] }}
                            </td>

                            <td>
                                {{ $row['weekend_ot'] }}
                            </td>

                            <td>
                                {{ $row['holiday_ot'] }}
                            </td>

                            <td>
                                <strong>
                                    {{ strtoupper($row['status']) }}
                                </strong>
                            </td>

                            <td>
                                <a
                                    class="text-amber-500"
                                    href="{{ route('admin.payroll.inputs.employees.show', [
                                        'snapshot' => $snapshot,
                                        'employeeInput' => $row['id'],
                                        'company_id' => $companyId,
                                    ]) }}"
                                >
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="9"
                                class="p-10 text-center"
                            >
                                Không có employee input phù hợp.
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
                    ← Previous
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
                    Next →
                </a>
            @endif
        </nav>

    </div>
@endsection
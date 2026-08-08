@extends('layouts.admin')

@section('title', 'Leave Management')

@section('content')
    <section
        class="panel"
        data-workforce-page
        data-workforce-region
        data-company-id="{{ $companyId }}"
        data-refresh-scope="leave-request-list"
        data-refresh-url="{{ route('admin.leave.index', ['company_id' => $companyId]) }}"
    >
        <span class="eyebrow">WORKFORCE OPERATIONS</span>
        <h1>Quản lý nghỉ phép</h1>
        <p>Thời lượng được tính phía server theo Work Calendar, holiday và timezone. Balance ledger là nguồn dữ liệu chuẩn.</p>

        @if ($errors->any())
            <div class="alert error">{{ implode(' ', $errors->all()) }}</div>
        @endif

        @can('leave.request')
            <form method="POST" action="{{ route('admin.leave.store') }}">
                @csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}">
                <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                <input type="number" name="employee_id" placeholder="Employee ID" required>
                <input type="number" name="leave_policy_id" placeholder="Leave Policy ID" required>
                <input type="datetime-local" name="starts_at" required>
                <input type="datetime-local" name="ends_at" required>
                <textarea name="reason" required></textarea>
                <button>Tạo yêu cầu</button>
            </form>
        @endcan
    </section>
@endsection

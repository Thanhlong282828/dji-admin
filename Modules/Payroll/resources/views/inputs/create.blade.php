@extends('layouts.admin')

@section('title', 'Tạo Payroll Input Snapshot')

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
    <section
        class="panel max-w-3xl"
        data-workforce-page
        data-refresh-scope="payroll-input-create-options"
    >

        {{-- ============================================================
            PAGE HEADER
        ============================================================ --}}
        <h1>
            Capture Input Snapshot
        </h1>

        <p class="mb-5">
            Snapshot không thể chỉnh sửa sau khi tạo.
            Chỉ Payroll Period đang mở mới xuất hiện.
        </p>


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
            NO OPEN PAYROLL PERIOD
        ============================================================ --}}
        @if($viewModel->periods === [])
            <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-6">
                <h2>
                    Chưa có Payroll Period đang mở.
                </h2>

                <p>
                    Bạn cần tạo và mở một Payroll Period trước khi tạo
                    Payroll Input Snapshot.
                </p>

                @can('payroll-period.create')
                    <a
                        class="button mt-4 inline-block"
                        href="{{ route('admin.payroll.periods.create', [
                            'company_id' => $companyId,
                            'return_to_input' => 1,
                        ]) }}"
                    >
                        + Tạo Payroll Period
                    </a>
                @endcan
            </div>

        @else

            {{-- ========================================================
                CREATE SNAPSHOT FORM
            ======================================================== --}}
            <form
                method="POST"
                action="{{ route('admin.payroll.inputs.store') }}"
                onsubmit="this.querySelector('button[type=submit]').disabled = true"
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
                    value="{{ old(
                        'idempotency_key',
                        (string) \Illuminate\Support\Str::uuid()
                    ) }}"
                >


                {{-- Payroll Period --}}
                <label>
                    Payroll Period

                    <select
                        required
                        name="payroll_period_id"
                    >
                        <option value="">
                            Chọn Payroll Period
                        </option>

                        @foreach($viewModel->periods as $period)
                            <option
                                value="{{ $period['id'] }}"
                                @selected(
                                    (string) old(
                                        'payroll_period_id',
                                        (string) request('payroll_period_id')
                                    ) === (string) $period['id']
                                )
                            >
                                {{ $period['code'] }}
                                —
                                {{ $period['name'] }}
                                ·
                                {{ $period['start'] }}
                                –
                                {{ $period['end'] }}
                            </option>
                        @endforeach
                    </select>
                </label>


                {{-- Note --}}
                <label>
                    Ghi chú

                    <textarea
                        name="note"
                        maxlength="2000"
                    >{{ old('note') }}</textarea>
                </label>


                {{-- Submit --}}
                <button
                    class="button"
                    type="submit"
                >
                    Capture immutable snapshot
                </button>
            </form>
        @endif

    </section>
@endsection
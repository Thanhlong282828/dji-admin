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
    FORM METHOD
============================================================ --}}
@csrf

@if(isset($method))
    @method($method)
@endif


{{-- ============================================================
    CONTEXT
============================================================ --}}
<input
    type="hidden"
    name="company_id"
    value="{{ $companyId }}"
>


@if(!isset($method))

    {{-- ========================================================
        CREATE-ONLY FIELDS
    ======================================================== --}}
    <input
        type="hidden"
        name="idempotency_key"
        value="{{ old(
            'idempotency_key',
            (string) \Illuminate\Support\Str::uuid()
        ) }}"
    >

    <input
        type="hidden"
        name="return_to_input"
        value="{{ request('return_to_input') ? 1 : 0 }}"
    >

@endif


{{-- ============================================================
    PAYROLL PERIOD CODE
============================================================ --}}
<label>
    Mã kỳ lương

    <input
        name="code"
        required
        maxlength="50"
        value="{{ old(
            'code',
            $period['code'] ?? now()->format('Y-m')
        ) }}"
    >
</label>


{{-- ============================================================
    PAYROLL PERIOD NAME
============================================================ --}}
<label>
    Tên kỳ lương

    <input
        name="name"
        required
        maxlength="255"
        value="{{ old(
            'name',
            $period['name'] ?? ('Payroll ' . now()->format('F Y'))
        ) }}"
    >
</label>


{{-- ============================================================
    PAYROLL PERIOD DATES
============================================================ --}}
<div class="grid gap-4 md:grid-cols-3">

    {{-- Start Date --}}
    <label>
        Start Date

        <input
            type="date"
            name="start_date"
            required
            value="{{ old(
                'start_date',
                $period['start'] ?? now()->startOfMonth()->toDateString()
            ) }}"
        >
    </label>


    {{-- End Date --}}
    <label>
        End Date

        <input
            type="date"
            name="end_date"
            required
            value="{{ old(
                'end_date',
                $period['end'] ?? now()->endOfMonth()->toDateString()
            ) }}"
        >
    </label>


    {{-- Pay Date --}}
    <label>
        Pay Date

        <input
            type="date"
            name="pay_date"
            required
            value="{{ old(
                'pay_date',
                $period['pay_date'] ?? now()
                    ->addMonth()
                    ->startOfMonth()
                    ->toDateString()
            ) }}"
        >
    </label>

</div>


{{-- ============================================================
    INITIAL STATUS
============================================================ --}}
@if(!isset($method))
    <label>
        Trạng thái ban đầu

        <select name="status">
            <option value="draft">
                Draft
            </option>

            <option value="collecting">
                Open ngay
            </option>
        </select>
    </label>
@endif


{{-- ============================================================
    NOTE
============================================================ --}}
<label>
    Ghi chú

    <textarea
        name="note"
        maxlength="2000"
    >{{ old('note', $period['note'] ?? '') }}</textarea>
</label>


{{-- ============================================================
    SUBMIT
============================================================ --}}
<button
    class="button"
    type="submit"
>
    Lưu Payroll Period
</button>
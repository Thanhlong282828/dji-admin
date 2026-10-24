@extends('layouts.admin')

@section('title', 'Payroll Input Foundation')

@section('content')
    <section class="panel">

        {{-- ============================================================
            PAGE HEADER
        ============================================================ --}}
        <span class="eyebrow">
            WORKFORCE OPERATIONS
        </span>

        <h2>
            Payroll Input Foundation
        </h2>

        <p>
            Snapshot bất biến của attendance, leave và overtime đã duyệt.
            Chưa tính gross/net, thuế, bảo hiểm, payslip hoặc bank payment.
        </p>
                <i>/</i>

         <span>
            Payroll/resources/views/index
        </span>

        <p>
            Company context: {{ $companyId }}
        </p>

    </section>
@endsection
@extends('layouts.admin')

@section('title', 'Payslip Detail')

@section('content')
    @php($p = $vm->detail)
    @php($s = $p['snapshot'])


    {{-- ================================================================
        PAYSLIP HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            {{ strtoupper($p['document_type']) }}
            ·
            {{ strtoupper($p['status']) }}
        </span>

        <h1>
            Payslip {{ substr($p['uuid'], 0, 8) }}
        </h1>
<i>/</i>

         <span>
            Payroll/resources/views/payslips/show
        </span>
        <p>
            Calculation V{{ $p['calculation_version'] }}
            ·
            Payslip V{{ $p['payslip_version'] }}
            ·
            Template {{ $p['template_version'] }}
        </p>

        <p>
            Checksum:
            {{ $p['checksum'] ?? 'Pending' }}
            ·
            Size:
            {{ $p['file_size'] ?? '—' }} bytes
        </p>
    </section>


    {{-- ================================================================
        IMMUTABLE PAYSLIP SNAPSHOT
    ================================================================ --}}
    @if($s)

        {{-- Snapshot Summary --}}
        <section class="grid gap-3 md:grid-cols-3">

            <article class="panel">
                <small>
                    Employee snapshot
                </small>

                <strong>
                    {{ $s['employee']['number'] }}
                    ·
                    {{ $s['employee']['name'] }}
                </strong>
            </article>


            <article class="panel">
                <small>
                    Period
                </small>

                <strong>
                    {{ $s['period']['code'] }}
                </strong>
            </article>


            <article class="panel">
                <small>
                    Run
                </small>

                <strong>
                    {{ $s['run']['number'] }}
                </strong>
            </article>

        </section>


        {{-- ============================================================
            PAYSLIP AMOUNTS
        ============================================================ --}}
        <section class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="w-full text-left">
                <tbody>
                    @foreach([
                        'gross_minor' => 'Gross Earnings',
                        'employee_insurance_minor' => 'Employee Insurance',
                        'employer_insurance_minor' => 'Employer Insurance',
                        'taxable_income_minor' => 'Taxable Income',
                        'personal_income_tax_minor' => 'Personal Income Tax',
                        'net_before_tax_minor' => 'Net Before Personal Income Tax',
                        'net_after_tax_minor' => 'Net After Personal Income Tax',
                    ] as $key => $label)
                        <tr>
                            <td>
                                {{ $label }}
                            </td>

                            <td>
                                {{ number_format($s['amounts'][$key]) }}
                                {{ $s['amounts']['currency'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

    @endif


    {{-- ================================================================
        PAYSLIP ACTIONS
    ================================================================ --}}
    <section class="flex flex-wrap gap-2">

        {{-- ============================================================
            SECURE DOWNLOAD
        ============================================================ --}}
        @if(in_array($p['status'], ['generated', 'published'], true))
            @can('payroll.payslip.download')
                <a
                    class="button"
                    href="{{ route('admin.payroll.payslips.download', [
                        'payslip' => $p['id'],
                        'company_id' => $c,
                    ]) }}"
                >
                    Secure Download
                </a>
            @endcan
        @endif


        {{-- ============================================================
            GENERATED ACTIONS
        ============================================================ --}}
        @if($p['status'] === 'generated')

            {{-- Publish --}}
            @can('payroll.payslip.publish')
                <form
                    method="POST"
                    action="{{ route('admin.payroll.payslips.publish', [
                        'payslip' => $p['id'],
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
                        Publish
                    </button>
                </form>
            @endcan


            {{-- Regenerate --}}
            @can('payroll.payslip.regenerate')
                <form
                    method="POST"
                    action="{{ route('admin.payroll.payslips.regenerate', [
                        'payslip' => $p['id'],
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
                        Regenerate same snapshot
                    </button>
                </form>
            @endcan

        @endif


        {{-- ============================================================
            REVOKE PAYSLIP
        ============================================================ --}}
        @if($p['status'] !== 'revoked')
            @can('payroll.payslip.revoke')
                <form
                    method="POST"
                    action="{{ route('admin.payroll.payslips.revoke', [
                        'payslip' => $p['id'],
                    ]) }}"
                    onsubmit="return confirm('Revoke Payslip? Employee download will be blocked.');"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="company_id"
                        value="{{ $c }}"
                    >

                    <input
                        name="reason"
                        required
                        maxlength="2000"
                        placeholder="Revocation reason"
                    >

                    <button
                        class="button"
                        type="submit"
                    >
                        Revoke
                    </button>
                </form>
            @endcan
        @endif

    </section>

@endsection
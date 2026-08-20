@extends('layouts.admin')

@section('title', 'Tax Policies')

@section('header_action')
    @can('payroll.tax.policy.create')
        <a
            class="button"
            href="{{ route('admin.payroll.tax.policies.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Tax Policy
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            PERSONAL INCOME TAX FOUNDATION
        </span>

        <h1>
            Tax Policies
        </h1>

        <p>
            Effective-dated, versioned configuration.
            Không phải tuyên bố tuân thủ pháp luật hiện hành.
        </p>
        <i>/</i>

         <span>
            Payroll/resources/views/tax/policies/index
        </span>

    </section>


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form class="grid gap-3 md:grid-cols-5">
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >

        <input
            name="search"
            value="{{ $f['search'] }}"
            placeholder="Code hoặc tên"
        >

        <select name="status">
            <option value="">
                All statuses
            </option>

            @foreach(['draft', 'active', 'inactive'] as $s)
                <option @selected($f['status'] === $s)>
                    {{ $s }}
                </option>
            @endforeach
        </select>

        <select name="method">
            <option value="">
                All methods
            </option>

            @foreach(['progressive', 'flat'] as $s)
                <option @selected($f['method'] === $s)>
                    {{ $s }}
                </option>
            @endforeach
        </select>

        <input
            name="year"
            value="{{ $f['year'] }}"
            placeholder="Effective year"
        >

        <button class="button">
            Filter
        </button>
    </form>


    {{-- ================================================================
        TAX POLICY TABLE
    ================================================================ --}}
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Effective</th>
                <th>Method</th>
                <th>Residency</th>
                <th>Currency</th>
                <th>Brackets</th>
                <th>Usage</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            @forelse($vm->items as $p)
                <tr>
                    <td>
                        {{ $p['code'] }}
                        V{{ $p['version'] }}
                    </td>

                    <td>
                        {{ $p['name'] }}
                    </td>

                    <td>
                        {{ $p['effective_from'] }}
                        →
                        {{ $p['effective_to'] ?? '∞' }}
                    </td>

                    <td>
                        {{ $p['method'] }}
                    </td>

                    <td>
                        {{ $p['residency'] }}
                    </td>

                    <td>
                        {{ $p['currency'] }}
                    </td>

                    <td>
                        {{ $p['bracket_count'] }}
                    </td>

                    <td>
                        {{ $p['usage_count'] }}
                    </td>

                    <td>
                        <a
                            href="{{ route('admin.payroll.tax.policies.show', [
                                'policy' => $p['id'],
                                'company_id' => $c,
                            ]) }}"
                        >
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        Chưa có Tax Policy.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

@endsection
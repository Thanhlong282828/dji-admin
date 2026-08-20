@extends('layouts.admin')

@section('title', 'Insurance Policy')

@section('content')
    @php($p = $vm->detail)

    {{-- ================================================================
        POLICY HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            POLICY VERSION
        </span>

        <h1>
            {{ $p['code'] }}
            V{{ $p['version'] }}
            ·
            {{ $p['name'] }}
        </h1>

        <p>
            {{ $p['effective_from'] }}
            →
            {{ $p['effective_to'] ?? '∞' }}
            ·
            {{ strtoupper($p['status']) }}
            ·
            {{ $p['currency_code'] }}
            ·
            {{ $p['rounding_rule'] }}
        </p>
    </section>


    {{-- ================================================================
        INSURANCE RATES
    ================================================================ --}}
    <section class="table-shell">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Employee BP</th>
                        <th>Employer BP</th>
                        <th>Floor</th>
                        <th>Cap</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($p['rates'] as $r)
                        <tr>

                            {{-- Insurance Type --}}
                            <td>
                                {{ $r['insurance_type'] }}
                            </td>


                            {{-- Employee Rate --}}
                            <td>
                                {{ $r['employee_rate_basis_points'] }}
                            </td>


                            {{-- Employer Rate --}}
                            <td>
                                {{ $r['employer_rate_basis_points'] }}
                            </td>


                            {{-- Minimum Base --}}
                            <td>
                                {{ $r['minimum_base_minor'] ?? '—' }}
                            </td>


                            {{-- Maximum Base --}}
                            <td>
                                {{ $r['maximum_base_minor'] ?? '—' }}
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-block">
                                    <h3>
                                        Chưa có insurance rate
                                    </h3>

                                    <p>
                                        Policy này chưa được cấu hình tỷ lệ đóng bảo hiểm.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>


    {{-- ================================================================
        ACTIONS
    ================================================================ --}}
    @can('payroll.insurance.policy.update')
        <div class="form-actions">
            <a
                class="button"
                href="{{ route('admin.payroll.insurance.policies.edit', [
                    'policy' => $p['id'],
                    'company_id' => $c,
                ]) }}"
            >
                Edit
            </a>
        </div>
    @endcan

@endsection
@extends('layouts.admin')

@section('title', 'Insurance Policies')

@section('header_action')
    @can('payroll.insurance.policy.create')
        <a
            class="button"
            href="{{ route('admin.payroll.insurance.policies.create', [
                'company_id' => $c,
            ]) }}"
        >
            + Policy
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        PAGE HEADER
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            STATUTORY FOUNDATION
        </span>

        <h2>
            Insurance Policies
        </h2>

        <p>
            Policy effective-dated;
            tỷ lệ cấu hình, không hard-code.
        </p>
    </section>


    {{-- ================================================================
        FILTERS
    ================================================================ --}}
    <form
        class="grid gap-3 md:grid-cols-4"
        method="GET"
    >
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- Search --}}
        <input
            name="search"
            value="{{ $f['search'] }}"
            placeholder="Code hoặc tên"
        >


        {{-- Status --}}
        <select name="status">
            <option value="">
                Tất cả trạng thái
            </option>

            @foreach([
                'draft',
                'active',
                'inactive',
            ] as $s)
                <option
                    value="{{ $s }}"
                    @selected($f['status'] === $s)
                >
                    {{ $s }}
                </option>
            @endforeach
        </select>


        {{-- Effective Year --}}
        <input
            name="year"
            value="{{ $f['year'] }}"
            placeholder="Effective year"
        >


        {{-- Submit --}}
        <button
            class="button"
            type="submit"
        >
            Lọc
        </button>
    </form>


    {{-- ================================================================
        INSURANCE POLICIES TABLE
    ================================================================ --}}
    <section class="overflow-x-auto rounded-xl border border-zinc-800">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Effective</th>
                    <th>Status</th>
                    <th>Currency</th>
                    <th>Rates</th>
                    <th>Profiles</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($vm->items as $p)
                    <tr>

                        {{-- Code / Version --}}
                        <td>
                            {{ $p['code'] }}
                            V{{ $p['version'] }}
                        </td>


                        {{-- Name --}}
                        <td>
                            {{ $p['name'] }}
                        </td>


                        {{-- Effective Period --}}
                        <td>
                            {{ $p['effective_from'] }}
                            →
                            {{ $p['effective_to'] ?? '∞' }}
                        </td>


                        {{-- Status --}}
                        <td>
                            <span class="status-pill is-{{ $p['status'] }}">
                                {{ $p['status'] }}
                            </span>
                        </td>


                        {{-- Currency --}}
                        <td>
                            {{ $p['currency'] }}
                        </td>


                        {{-- Rates --}}
                        <td>
                            {{ $p['rate_count'] }}
                        </td>


                        {{-- Profiles --}}
                        <td>
                            {{ $p['profile_count'] }}
                        </td>


                        {{-- Actions --}}
                        <td>
                            <a
                                class="text-amber-500"
                                href="{{ route('admin.payroll.insurance.policies.show', [
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
                        <td
                            colspan="8"
                            class="p-8 text-center"
                        >
                            Chưa có Insurance Policy.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

@endsection
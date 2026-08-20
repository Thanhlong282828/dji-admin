@extends('layouts.admin')

@php($p = $vm->detail)

@section('title', 'Accounting Policy · ' . $p['code'])

@section('header_action')
    @can('payroll.accounting.policy.manage')
        <a
            class="button"
            href="{{ route('admin.payroll.accounting.policies.edit', [
                'policy' => $p['id'],
                'company_id' => $c,
            ]) }}"
        >
            Chỉnh sửa
        </a>
    @endcan
@endsection

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.accounting.policies.index', [
                'company_id' => $c,
            ]) }}"
        >
            Accounting Policies
        </a>

        <i>/</i>

        <span>
            {{ $p['code'] }}
        </span>
                 <i>/</i>

         <span>
            Payroll/resources/views/policies/show
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Policy version {{ $p['version'] }}
        </span>

        <h2>
            {{ $p['name'] }}
        </h2>

        <p>
            Resolution theo ngày kết thúc Payroll Period
            ·
            {{ $p['currency'] }}
            ·
            {{ $p['aggregation_strategy'] }}
        </p>
    </section>


    {{-- ================================================================
        POLICY METRICS
    ================================================================ --}}
    <section class="metric-grid">

        {{-- Status --}}
        <article class="metric-card">
            <span>
                Status
            </span>

            <strong>
                {{ $p['status'] }}
            </strong>
        </article>


        {{-- Effective From --}}
        <article class="metric-card">
            <span>
                Effective from
            </span>

            <strong>
                {{ $p['effective_from'] }}
            </strong>
        </article>


        {{-- Effective To --}}
        <article class="metric-card">
            <span>
                Effective to
            </span>

            <strong>
                {{ $p['effective_to'] ?? 'Open-ended' }}
            </strong>
        </article>

    </section>


    {{-- ================================================================
        GOVERNANCE NOTE
    ================================================================ --}}
    <section class="panel">
        <span class="eyebrow">
            Governance note
        </span>

        <p>
            {{ $p['note'] ?: 'Không có ghi chú.' }}
        </p>
    </section>

@endsection
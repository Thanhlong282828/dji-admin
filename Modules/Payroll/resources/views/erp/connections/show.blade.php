@extends('layouts.admin')

@section('header_action')
    @can('erp.integration.connection.manage')
        <a
            class="button"
            href="{{ route('admin.payroll.erp.connections.edit', [
                'connection' => $vm->detail['id'],
                'company_id' => $c,
            ]) }}"
        >
            Chỉnh sửa
        </a>
    @endcan
@endsection

@section('content')
    @php($x = $vm->detail)

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.erp.connections.index', [
                'company_id' => $c,
            ]) }}"
        >
            ERP Connections
        </a>

        <i>/</i>

        <span>
            {{ $x['name'] }}
        </span>
           <i>/</i>

         <span>
            Payroll/resources/views/erp/connections/show
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            {{ $x['provider'] }}
        </span>

        <h2>
            {{ $x['name'] }}
        </h2>

        <p>
            Credential:
            {{
                $x['has_credential']
                    ? 'Configured ••••••••'
                    : 'Not configured'
            }}.

            Plaintext is never returned.
        </p>
    </section>


    {{-- ================================================================
        CONNECTION METRICS
    ================================================================ --}}
    <section class="metric-grid">

        {{-- Status --}}
        <article class="metric-card">
            <span>
                Status
            </span>

            <strong>
                {{ $x['status'] }}
            </strong>
        </article>


        {{-- Endpoint --}}
        <article class="metric-card">
            <span>
                Endpoint
            </span>

            <strong>
                {{ $x['endpoint_reference'] ?? 'Private file' }}
            </strong>
        </article>


        {{-- Verification --}}
        <article class="metric-card">
            <span>
                Verified
            </span>

            <strong>
                {{ $x['last_verified_at'] ?? 'Not verified' }}
            </strong>
        </article>

    </section>

@endsection
@extends('layouts.admin')

@section('title', 'Create Government Filing')

@section('content')

    {{-- ================================================================
        BREADCRUMB
    ================================================================ --}}
    <nav class="breadcrumb">
        <a
            href="{{ route('admin.payroll.government-filings.index', [
                'company_id' => $c,
            ]) }}"
        >
            Government Filings
        </a>

        <i>/</i>

        <span>
            Create
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Immutable source boundary
        </span>

        <h2>
            Build declaration snapshot
        </h2>

        <p>
            Only finalized Annual Tax evidence is copied.
            Live Employee, Payroll and Tax Policy data are never queried
            during later filing actions.
        </p>
    </section>


    {{-- ================================================================
        VALIDATION ERRORS
    ================================================================ --}}
    @if($errors->any())
        <div class="validation-summary">
            {{ $errors->first() }}
        </div>
    @endif


    {{-- ================================================================
        CREATE GOVERNMENT FILING FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ route('admin.payroll.government-filings.store') }}"
    >
        @csrf

        {{-- Company Context --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- Idempotency --}}
        <input
            type="hidden"
            name="idempotency_key"
            value="{{ (string) Illuminate\Support\Str::uuid() }}"
        >


        {{-- ============================================================
            AUTHORITATIVE ANNUAL TAX SNAPSHOT
        ============================================================ --}}
        <label class="span-2">
            Finalized Annual Tax Reconciliation

            <select
                name="reconciliation_id"
                required
            >
                <option value="">
                    Select authoritative snapshot
                </option>

                @foreach($vm->items as $annual)
                    <option
                        value="{{ $annual['id'] }}"
                        @selected(old('reconciliation_id') == $annual['id'])
                    >
                        {{ $annual['reference'] }}
                        ·
                        {{ $annual['tax_year'] }}
                        ·
                        {{ $annual['result_type'] }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            FILING TYPE
        ============================================================ --}}
        <label>
            Filing type

            <select
                name="filing_type"
                required
            >
                <option value="annual_pit_employer_declaration">
                    Annual PIT employer declaration
                </option>
            </select>
        </label>


        {{-- ============================================================
            SECURITY NOTICE
        ============================================================ --}}
        <div class="span-2 security-note">
            Generic XML only.
            No government endpoint, credential, private key
            or production digital signature is used.
        </div>


        {{-- ============================================================
            ACTIONS
        ============================================================ --}}
        <div class="span-2 hero-actions">
            <button
                class="button"
                type="submit"
            >
                Create immutable filing
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.government-filings.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Cancel
            </a>
        </div>

    </form>

@endsection
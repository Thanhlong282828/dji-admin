@extends('layouts.admin')

@section('content')

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
            {{ $connection ? 'Edit' : 'Create' }}
        </span>
           <i>/</i>

         <span>
            Payroll/resources/views/erp/connections/from
        </span>
    </nav>


    {{-- ================================================================
        PAGE HERO
    ================================================================ --}}
    <section class="panel page-hero">
        <span class="eyebrow">
            Secure configuration
        </span>

        <h2>
            {{ $connection
                ? 'Cập nhật Connection'
                : 'Tạo ERP Connection'
            }}
        </h2>

        <p>
            Để trống credential khi update để giữ nguyên
            encrypted reference hiện tại.
        </p>
    </section>


    {{-- ================================================================
        VALIDATION ERRORS
    ================================================================ --}}
    @if($errors->any())
        <div class="notice danger">
            {{ $errors->first() }}
        </div>
    @endif


    {{-- ================================================================
        ERP CONNECTION FORM
    ================================================================ --}}
    <form
        class="panel form-grid"
        method="POST"
        action="{{ $connection
            ? route('admin.payroll.erp.connections.update', [
                'connection' => $connection['id'],
            ])
            : route('admin.payroll.erp.connections.store')
        }}"
    >
        @csrf

        @if($connection)
            @method('PUT')
        @endif


        {{-- Company Context --}}
        <input
            type="hidden"
            name="company_id"
            value="{{ $c }}"
        >


        {{-- ============================================================
            NAME
        ============================================================ --}}
        <label>
            Name

            <input
                name="name"
                required
                maxlength="160"
                value="{{ old(
                    'name',
                    $connection['name'] ?? ''
                ) }}"
            >
        </label>


        {{-- ============================================================
            PROVIDER
        ============================================================ --}}
        <label>
            Provider

            <select name="provider">
                <option
                    value="generic_csv"
                    @selected(
                        old(
                            'provider',
                            $connection['provider'] ?? 'generic_csv'
                        ) === 'generic_csv'
                    )
                >
                    Generic CSV
                </option>
            </select>
        </label>


        {{-- ============================================================
            ENDPOINT REFERENCE
        ============================================================ --}}
        <label>
            Endpoint reference

            <input
                name="endpoint_reference"
                maxlength="255"
                value="{{ old(
                    'endpoint_reference',
                    $connection['endpoint_reference'] ?? ''
                ) }}"
                placeholder="private-file-drop"
            >
        </label>


        {{-- ============================================================
            CREDENTIAL REFERENCE
        ============================================================ --}}
        <label>
            Credential reference

            <input
                type="password"
                name="credential_reference"
                autocomplete="new-password"
                maxlength="2000"
                placeholder="{{ ($connection['has_credential'] ?? false)
                    ? 'Configured — leave blank to retain'
                    : 'Optional encrypted reference'
                }}"
            >
        </label>


        {{-- ============================================================
            STATUS
        ============================================================ --}}
        <label>
            Status

            <select name="status">
                @foreach([
                    'active',
                    'inactive',
                ] as $s)
                    <option
                        value="{{ $s }}"
                        @selected(
                            old(
                                'status',
                                $connection['status'] ?? 'active'
                            ) === $s
                        )
                    >
                        {{ $s }}
                    </option>
                @endforeach
            </select>
        </label>


        {{-- ============================================================
            ACTIONS
        ============================================================ --}}
        <div class="full form-actions">
            <button
                class="button"
                type="submit"
            >
                Lưu Connection
            </button>

            <a
                class="button secondary"
                href="{{ route('admin.payroll.erp.connections.index', [
                    'company_id' => $c,
                ]) }}"
            >
                Hủy
            </a>
        </div>

    </form>

@endsection
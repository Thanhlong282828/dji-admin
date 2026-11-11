@extends('layouts.admin')
@section('title','IAM Governance')
@section('content')
@include('iam::partials.styles')
<x-erp.dashboard eyebrow="Enterprise control plane" title="Identity & Access Governance" description="Govern who receives access, why, for how long, and who certified it.">
    <x-slot:actions>@include('iam::partials.nav')</x-slot:actions>
    <x-erp.dashboard-section title="Security status" description="Chỉ số từ read model IAM, không thay thế kiểm tra authorization runtime." columns="5">
        @foreach(['pending_requests'=>'Pending Requests','privileged_assignments'=>'Privileged','sod_violations'=>'SoD Violations','expiring_access'=>'Expiring','open_reviews'=>'Open Reviews'] as $key=>$label)
            <x-erp.kpi-card :label="$label" :value="$vm->metrics[$key]" :icon="$key === 'sod_violations' ? '!' : '◈'" />
        @endforeach
    </x-erp.dashboard-section>
    <x-erp.dashboard-section title="Governance boundary"><x-erp.widget><p>Spatie Permission remains runtime authorization. IAM controls approved, time-bounded and auditable assignment decisions.</p></x-erp.widget></x-erp.dashboard-section>
</x-erp.dashboard>
@endsection

@extends('layouts.admin')

@section('title', 'Payroll Delivery')

@section('content')
<div class="space-y-8">
    <div class="flex items-end justify-between gap-4">
        <div><p class="text-xs uppercase tracking-[.25em] text-amber-500">Schema HRM</p><h1 class="text-3xl font-semibold">Payroll Delivery</h1></div>
        <a href="{{ route('admin.payroll.inputs.index', ['company_id' => $companyId]) }}" class="rounded-lg border border-amber-500 px-4 py-2">Payroll Input</a>
    </div>
    @if (session('success')) <div class="rounded-lg bg-emerald-950 p-4 text-emerald-200">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-lg bg-red-950 p-4 text-red-200"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-zinc-800 p-5"><p class="text-zinc-400">Components</p><strong class="text-3xl">{{ count($viewModel->components) }}</strong></div>
        <div class="rounded-xl border border-zinc-800 p-5"><p class="text-zinc-400">Assignments</p><strong class="text-3xl">{{ count($viewModel->assignments) }}</strong></div>
        <div class="rounded-xl border border-zinc-800 p-5"><p class="text-zinc-400">Payroll runs</p><strong class="text-3xl">{{ count($viewModel->runs) }}</strong></div>
    </section>

    @can('payroll.component.manage')
    <section class="rounded-xl border border-zinc-800 p-5">
        <h2 class="mb-4 text-xl font-medium">Thêm thành phần lương</h2>
        <form method="POST" action="{{ route('admin.payroll.components.store', ['action' => 'store']) }}" class="grid gap-3 md:grid-cols-4">
            @csrf <input type="hidden" name="company_id" value="{{ $companyId }}">
            <input name="code" value="{{ old('code') }}" placeholder="Code" required class="rounded bg-zinc-900 p-3">
            <input name="name" value="{{ old('name') }}" placeholder="Tên" required class="rounded bg-zinc-900 p-3">
            <select name="category" class="rounded bg-zinc-900 p-3"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
            <select name="insurance_status" class="rounded bg-zinc-900 p-3"><option value="not_configured">Insurance: Not configured</option><option value="included">Included in insurance base</option><option value="excluded">Excluded from insurance base</option><option value="conditional">Conditional (blocks until resolved)</option></select>
            <select name="taxable_status" class="rounded bg-zinc-900 p-3"><option value="not_configured">Tax: Not configured</option><option value="taxable">Taxable</option><option value="non_taxable">Non-taxable</option><option value="conditionally_taxable">Conditional (blocks until resolved)</option></select>
            <select name="calculation_type" class="rounded bg-zinc-900 p-3"><option value="fixed">Fixed</option><option value="percentage">Percentage</option><option value="quantity_rate">Quantity × Rate</option></select>
            <input name="value_type" value="money" required class="rounded bg-zinc-900 p-3">
            <input name="default_value_minor" type="number" placeholder="Giá trị (minor unit)" class="rounded bg-zinc-900 p-3">
            <input name="currency_code" value="VND" maxlength="3" class="rounded bg-zinc-900 p-3">
            <input type="hidden" name="status" value="active"><button class="rounded bg-amber-500 px-4 py-3 font-semibold text-black">Tạo component</button>
        </form>
    </section>
    @endcan

    <section class="overflow-x-auto rounded-xl border border-zinc-800"><table class="w-full text-left"><thead class="bg-zinc-900"><tr><th class="p-3">Code</th><th>Name</th><th>Category</th><th>Status</th></tr></thead><tbody>@forelse($viewModel->components as $component)<tr class="border-t border-zinc-800"><td class="p-3">{{ $component['code'] }}</td><td>{{ $component['name'] }}</td><td>{{ $component['category'] }}</td><td>{{ $component['status'] }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-zinc-500">Chưa có thành phần lương.</td></tr>@endforelse</tbody></table></section>
    <section><h2 class="mb-3 text-xl font-medium">Payroll runs</h2><div class="space-y-2">@forelse($viewModel->runs as $run)<a class="block rounded-xl border border-zinc-800 p-4 hover:border-amber-500" href="{{ route('admin.payroll.runs.show', ['run' => $run['id'], 'company_id' => $companyId]) }}">{{ $run['code'] }} · {{ $run['status'] }}</a>@empty<p class="rounded-xl border border-zinc-800 p-8 text-center text-zinc-500">Chưa có kỳ chạy lương.</p>@endforelse</div></section>
</div>
@endsection

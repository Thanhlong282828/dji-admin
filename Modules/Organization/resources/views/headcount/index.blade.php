@extends('layouts.admin')
@section('title', 'Báo cáo định biên')
@section('content')
<form class="panel filter-bar" method="GET">
    <input type="hidden" name="company_id" value="{{ $companyId }}">
    <select name="type">
        <option value="">Tất cả loại đơn vị</option>
        @foreach(['company', 'branch', 'division', 'department', 'section', 'team'] as $type)
            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
    <input type="date" name="effective_date" value="{{ request('effective_date', now()->toDateString()) }}">
    <label><input type="checkbox" name="critical_only" value="1" @checked(request()->boolean('critical_only'))> Critical only</label>
    <button type="submit">Áp dụng bộ lọc</button>
    @can('organization-unit.export')
        <a class="button" href="{{ route('admin.organization.export-headcount', array_merge(request()->only(['organization_unit_id','job_position_id','job_grade','effective_date','critical_only']), ['company_id'=>$companyId])) }}">Xuất CSV</a>
    @endcan
</form>
<section class="stats">
    @foreach([
        ['Đơn vị', $stats['total_units'], 'UNITS'],
        ['Cost centers', $stats['total_cost_centers'], 'COST'],
        ['Vị trí', $stats['total_positions'], 'POSITIONS'],
        ['Approved', $stats['approved_headcount'], 'HEADCOUNT'],
        ['People', $stats['people_count'], 'PEOPLE'],
        ['Allocated FTE', number_format($stats['allocated_fte'], 2), 'FTE'],
        ['Vacancy', number_format($stats['vacancy'], 2), 'OPEN'],
        ['Critical', $stats['critical_positions'], 'CRITICAL'],
    ] as [$label, $value, $unit])
        <article><span>{{ $label }}</span><strong>{{ $value }}</strong><i>{{ $unit }}</i></article>
    @endforeach
</section>
<section class="dashboard-grid">
    <article class="panel"><small>ANNUAL BUDGET</small><h2>{{ number_format($stats['annual_budget']) }} VND</h2><p>Ngân sách cost center</p></article>
    <article class="panel"><small>POSITION COST</small><h2>{{ number_format($stats['budgeted_position_cost']) }} VND</h2><p>Chi phí vị trí được duyệt</p></article>
</section>
<section class="panel">
    <small>HEADCOUNT & VACANCY BY UNIT</small>
    <div class="headcount-chart">
        @forelse($stats['tree'] as $node)
            @php($maximum = max(1, $node['approved_headcount']))
            <article>
                <div><b>{{ $node['code'] }}</b> {{ $node['name'] }} <span>{{ number_format($node['allocated_fte'], 2) }} / {{ $node['approved_headcount'] }} FTE</span></div>
                <div class="chart-track">
                    <i style="width: {{ min(100, ($node['allocated_fte'] / $maximum) * 100) }}%"></i>
                    <em style="width: {{ min(100, ($node['vacancy'] / $maximum) * 100) }}%"></em>
                </div>
            </article>
        @empty
            <div class="empty-state">Không có dữ liệu phù hợp bộ lọc.</div>
        @endforelse
    </div>
</section>
@endsection

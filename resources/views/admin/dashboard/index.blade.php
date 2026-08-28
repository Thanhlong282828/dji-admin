@extends('layouts.admin')

@section('title', 'Tổng quan điều hành')

@section('content')
<section class="stats">
    <article><span>Tổng nhân sự</span><strong>{{ number_format($totalEmployees) }}</strong><i>HEADCOUNT</i></article>
    <article><span>Đang làm việc</span><strong>{{ number_format($activeEmployees) }}</strong><i>ACTIVE TALENT</i></article>
    <article><span>Phép chờ duyệt</span><strong>{{ number_format($pendingLeaveRequests) }}</strong><i>ACTION REQUIRED</i></article>
    <article><span>Vị trí đang tuyển</span><strong>{{ number_format($openRecruitmentRequisitions) }}</strong><i>OPEN REQUISITIONS</i></article>
</section>

@can('company.view')
<section class="stats company-stats">
    <a href="{{ route('admin.companies.index') }}"><article><span>Tổng số công ty</span><strong>{{ number_format($companyStats['total']) }}</strong><i>LEGAL ENTITIES</i></article></a>
    <a href="{{ route('admin.companies.index', ['status' => 'active']) }}"><article><span>Đang hoạt động</span><strong>{{ number_format($companyStats['active']) }}</strong><i>ACTIVE</i></article></a>
    <a href="{{ route('admin.companies.index', ['status' => 'inactive']) }}"><article><span>Ngừng hoạt động</span><strong>{{ number_format($companyStats['inactive']) }}</strong><i>INACTIVE</i></article></a>
</section>
@endcan

@if($branchSummary !== null)
<section class="panel">
    <small>BRANCH OPERATIONS · COMPANY SCOPE</small>
    <h2>Vận hành chi nhánh</h2>
    <div class="stats">
        <article><span>Chi nhánh</span><strong>{{ number_format($branchSummary['branches']) }}</strong><i>ACTIVE SCOPE</i></article>
        <article><span>Nhân sự có Branch hiệu lực</span><strong>{{ number_format($branchSummary['assigned_employees']) }}</strong><i>EFFECTIVE ASSIGNMENT</i></article>
        <article><span>Chưa xác định Branch vận hành</span><strong>{{ number_format($branchSummary['unassigned_employees']) }}</strong><i>DATA ATTENTION</i></article>
        @if($branchSummary['financials'] !== null)
            <article><span>Lợi nhuận quản trị tháng</span><strong>{{ number_format($branchSummary['financials']['profit']) }} ₫</strong><i>NOT STATUTORY</i></article>
        @endif
    </div>
    @if($branchSummary['financials'] !== null && $branchSummary['financial_trend'] !== [])
        @php($maxTrend = max(1, ...array_map(static fn(array $row): int => max($row['revenue'], $row['payroll'], $row['operating'], abs($row['profit'])), $branchSummary['financial_trend'])))
        <div class="erp-trend" aria-labelledby="erp-trend-title">
            <header><div><small>ACTUAL DATABASE · VND</small><h3 id="erp-trend-title">Doanh thu · Payroll · Chi phí · Kết quả</h3></div><span>6 tháng gần nhất</span></header>
            <div class="erp-trend__chart" role="img" aria-label="Biểu đồ tài chính quản trị sáu tháng theo dữ liệu Branch Finance">
                @foreach($branchSummary['financial_trend'] as $point)
                    <article>
                        <div class="erp-trend__bars">
                            <i class="is-revenue" style="--bar: {{ round($point['revenue'] * 100 / $maxTrend, 2) }}%" title="Doanh thu {{ number_format($point['revenue']) }} ₫"></i>
                            <i class="is-payroll" style="--bar: {{ round($point['payroll'] * 100 / $maxTrend, 2) }}%" title="Payroll {{ number_format($point['payroll']) }} ₫"></i>
                            <i class="is-operating" style="--bar: {{ round($point['operating'] * 100 / $maxTrend, 2) }}%" title="Chi phí {{ number_format($point['operating']) }} ₫"></i>
                            <i class="is-profit" style="--bar: {{ round(abs($point['profit']) * 100 / $maxTrend, 2) }}%" title="Kết quả {{ number_format($point['profit']) }} ₫"></i>
                        </div>
                        <strong>{{ $point['month'] }}</strong><small>{{ $point['profit'] >= 0 ? 'Lãi' : 'Lỗ' }} {{ number_format(abs($point['profit'])) }} ₫</small>
                    </article>
                @endforeach
            </div>
            <footer><span><b class="is-revenue"></b>Doanh thu</span><span><b class="is-payroll"></b>Payroll</span><span><b class="is-operating"></b>Chi phí</span><span><b class="is-profit"></b>Kết quả</span></footer>
        </div>
    @endif
</section>
@endif

<section class="dashboard-grid">
    <article class="panel executive-panel">
        <small>WORKFORCE INTELLIGENCE</small>
        <h2>Nhân lực trong một nhịp nhìn.</h2>
        <p>Dữ liệu tổ chức, biến động nhân sự và nhu cầu phê duyệt được tổng hợp qua CQRS Query Bus và Redis cache.</p>
        <div class="metric-bars">
            @forelse($headcountByStatus as $status => $count)
                @php($percent = $totalEmployees > 0 ? max(4, round(($count / $totalEmployees) * 100)) : 0)
                <div><span>{{ str($status)->headline() }} <b>{{ $count }}</b></span><i style="--value: {{ $percent }}%"></i></div>
            @empty
                <div class="empty-state">Chưa có dữ liệu nhân sự. Module Employee đang trong giai đoạn hoàn thiện.</div>
            @endforelse
        </div>
    </article>

    <article class="panel content-panel">
        <small>BRAND PUBLISHING</small>
        <h3>{{ $publishedArticles }}/{{ $totalArticles }}</h3>
        <p>bài viết đã xuất bản</p>
        <dl>
            <div><dt>Bản nháp</dt><dd>{{ $draftArticles }}</dd></div>
            <div><dt>Nổi bật</dt><dd>{{ $featuredArticles }}</dd></div>
        </dl>
        @can('article.create')<a class="button" href="{{ route('articles.create') }}">Tạo nội dung mới</a>@endcan
    </article>
</section>

<section class="panel activity-panel">
    <div><small>AUDIT STREAM</small><h2>Hoạt động gần đây</h2></div>
    <div class="activity-list">
        @forelse($recentActivities as $activity)
            <div><span>{{ $activity['description'] }}</span><small>{{ $activity['subject_type'] }} · {{ $activity['created_at'] }}</small></div>
        @empty
            <div class="empty-state">Chưa phát sinh activity log.</div>
        @endforelse
    </div>
</section>
@endsection

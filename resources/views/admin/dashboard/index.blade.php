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
            <article><span>Lợi nhuận quản trị tháng</span><strong>{{ number_format($branchSummary['financials']['profit']) }} ₫</strong><i>{{ $branchSummary['financials']['synthetic_revenue_records'] > 0 ? 'SYNTHETIC DEMO · NOT STATUTORY' : 'NOT STATUTORY' }}</i></article>
        @endif
    </div>
    <div class="stats">
        <a href="{{ route('admin.hr.operations.index',['company_id'=>request('company_id')]) }}"><article><span>Hợp đồng hết hạn 30 ngày</span><strong>{{ number_format($operatingSummary['hrOperations']['contracts_expiring_30']) }}</strong><i>HR OPERATIONS</i></article></a>
        <a href="{{ route('admin.hr.operations.index',['company_id'=>request('company_id')]) }}"><article><span>Resignation / Offboarding</span><strong>{{ number_format($operatingSummary['hrOperations']['pending_resignations']) }} / {{ number_format($operatingSummary['hrOperations']['offboarding']) }}</strong><i>MAKER-CHECKER</i></article></a>
        <a href="{{ route('admin.branch-commerce.customers',['company_id'=>request('company_id')]) }}"><article><span>CRM Customers · Repeat</span><strong>{{ number_format($operatingSummary['hrOperations']['crm_customers']) }} · {{ number_format($operatingSummary['hrOperations']['crm_repeat']) }}</strong><i>AT RISK {{ number_format($operatingSummary['hrOperations']['crm_at_risk']) }}</i></article></a>
        <a href="{{ route('admin.finance.accounting-adjustments',['company_id'=>request('company_id')]) }}"><article><span>Accounting Adjustments chờ xử lý</span><strong>{{ number_format($operatingSummary['hrOperations']['pending_accounting_adjustments']) }}</strong><i>IMMUTABLE JOURNAL</i></article></a>
        @can('sales.view')<a href="{{ route('admin.sales.index',['company_id'=>request('company_id')]) }}"><article><span>ERP Sales · AR Outstanding</span><strong>{{ number_format($operatingSummary['commerceData']['erp_sales_orders'] ?? 0) }} · {{ number_format($operatingSummary['commerceData']['ar_outstanding'] ?? 0) }} ₫</strong><i>INVOICE / COLLECTION</i></article></a>@endcan
    </div>
    @if($branchSummary['sales'] !== null)
        <h3>Commerce & POS từ dữ liệu canonical</h3>
        <div class="stats">
            <article><span>Marketplace Orders</span><strong>{{ number_format($branchSummary['sales']['marketplace_orders']) }}</strong><i>ORDER SOURCE</i></article>
            <article><span>POS Receipts đã thanh toán</span><strong>{{ number_format($branchSummary['sales']['pos_receipts']) }}</strong><i>RECEIPT SOURCE</i></article>
            <article><span>Khách hàng đang hoạt động</span><strong>{{ number_format($branchSummary['sales']['customers']) }}</strong><i>CRM SOURCE</i></article>
            <article><span>Phiên đang phục vụ</span><strong>{{ number_format($branchSummary['sales']['visits_open']) }}</strong><i>VISIT SOURCE</i></article>
            <article><span>Sản phẩm POS đã bán</span><strong>{{ number_format($branchSummary['sales']['products_sold']) }}</strong><i>RECEIPT LINES</i></article>
            <article><span>Dịch vụ POS đã bán</span><strong>{{ number_format($branchSummary['sales']['services_sold']) }}</strong><i>RECEIPT LINES</i></article>
            <article><span>Doanh thu Commerce + POS</span><strong>{{ number_format($branchSummary['sales']['recognized_revenue']) }} ₫</strong><i>RECOGNIZED ONLY</i></article>
            <article><span>SKU tồn thấp</span><strong>{{ number_format($branchSummary['sales']['low_stock']) }}</strong><i>AVAILABLE ≤ 5</i></article>
        </div>
    @endif
    @if($branchSummary['financials'] !== null && $branchSummary['financial_trend'] !== [])
        @php($maxTrend = max(1, ...array_map(static fn(array $row): int => max($row['revenue'], $row['payroll'], $row['operating'], abs($row['profit'])), $branchSummary['financial_trend'])))
        <div class="erp-trend" aria-labelledby="erp-trend-title">
            <header><div><small>DB-BACKED · SOURCE-AWARE · VND</small><h3 id="erp-trend-title">Doanh thu · Payroll · Chi phí · Kết quả</h3></div><span>6 tháng gần nhất</span></header>
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
            @if($branchSummary['financials']['synthetic_revenue_records'] > 0)
                <p class="notice">Revenue trên biểu đồ gồm management ledger synthetic của local demo; Payroll/OPEX/Rent/Asset vẫn lấy từ các source module tương ứng. Order unpaid không được ghi nhận.</p>
            @endif
        </div>
    @endif
</section>
@endif

@if($operatingSummary !== null)
<section class="panel operating-cockpit">
    <header class="section-heading"><div><small>CANONICAL SOURCES · COMPANY SCOPE</small><h2>Nhịp vận hành doanh nghiệp</h2></div><span>Không lưu bản sao KPI trong Dashboard</span></header>
    <div class="stats">
        <article><span>Đơn vị / Vị trí</span><strong>{{ number_format($operatingSummary['organization']['units']) }} / {{ number_format($operatingSummary['organization']['positions']) }}</strong><i>ORGANIZATION</i></article>
        <article><span>Headcount được duyệt</span><strong>{{ number_format($operatingSummary['organization']['approved_headcount']) }}</strong><i>JOB POSITIONS</i></article>
        <article><span>Seller / Store</span><strong>{{ number_format($operatingSummary['organization']['sellers']) }} / {{ number_format($operatingSummary['organization']['stores']) }}</strong><i>COMPANY OWNERSHIP</i></article>
        <a href="{{ route('admin.operations.data-quality', ['company_id' => request('company_id')]) }}"><article><span>Data Quality</span><strong>{{ number_format($operatingSummary['quality']['passed']) }}/{{ number_format($operatingSummary['quality']['rules']) }}</strong><i>{{ $operatingSummary['quality']['failed'] === 0 ? 'PASS' : 'ATTENTION' }}</i></article></a>
    </div>

    <div class="dashboard-grid">
        @if($operatingSummary['recruitmentData'] !== null)
        <article class="panel"><small>RECRUITMENT → EMPLOYEE</small><h3>Tuyển dụng</h3><dl>
            <div><dt>Yêu cầu mở / Vacancy</dt><dd>{{ number_format($operatingSummary['recruitmentData']['open_requisitions']) }} / {{ number_format($operatingSummary['recruitmentData']['vacancies']) }}</dd></div>
            <div><dt>Ứng viên / Phỏng vấn</dt><dd>{{ number_format($operatingSummary['recruitmentData']['candidates']) }} / {{ number_format($operatingSummary['recruitmentData']['scheduled_interviews']) }}</dd></div>
            <div><dt>Offer / Hired đã nối Employee</dt><dd>{{ number_format($operatingSummary['recruitmentData']['offers']) }} / {{ number_format($operatingSummary['recruitmentData']['linked_hires']) }}</dd></div>
        </dl><a class="button ghost" href="{{ route('admin.recruitment.dashboard', ['company_id' => request('company_id')]) }}">Mở Recruitment</a></article>
        @endif

        @if($operatingSummary['attendanceData'] !== null)
        <article class="panel"><small>ATTENDANCE · {{ $operatingSummary['attendanceData']['date'] }}</small><h3>Chấm công</h3><dl>
            <div><dt>Có mặt / Bản ghi</dt><dd>{{ number_format($operatingSummary['attendanceData']['present']) }} / {{ number_format($operatingSummary['attendanceData']['records']) }}</dd></div>
            <div><dt>Đi trễ / Tổng phút</dt><dd>{{ number_format($operatingSummary['attendanceData']['late']) }} / {{ number_format($operatingSummary['attendanceData']['late_minutes']) }}</dd></div>
            <div><dt>Nghỉ / OT duyệt</dt><dd>{{ number_format($operatingSummary['attendanceData']['leave']) }} / {{ number_format($operatingSummary['attendanceData']['approved_overtime_minutes']) }} phút</dd></div>
        </dl><a class="button ghost" href="{{ route('admin.attendance.index', ['company_id' => request('company_id')]) }}">Mở Attendance</a></article>
        @endif

        @if($operatingSummary['payrollData'] !== null)
        <article class="panel"><small>PAYROLL · {{ $operatingSummary['payrollData']['period'] }}</small><h3>Chi phí nhân sự</h3><dl>
            <div><dt>Gross</dt><dd>{{ number_format($operatingSummary['payrollData']['gross']) }} ₫</dd></div>
            <div><dt>Khấu trừ / Net</dt><dd>{{ number_format($operatingSummary['payrollData']['deductions']) }} / {{ number_format($operatingSummary['payrollData']['net']) }} ₫</dd></div>
            <div><dt>PIT / BH người lao động</dt><dd>{{ number_format($operatingSummary['payrollData']['pit']) }} / {{ number_format($operatingSummary['payrollData']['insurance_employee']) }} ₫</dd></div>
        </dl><a class="button ghost" href="{{ route('admin.payroll.runs.show', ['run' => $operatingSummary['payrollData']['run_id'], 'company_id' => request('company_id')]) }}">Đối chiếu Payroll Run</a></article>
        @endif

        @if($operatingSummary['commerceData'] !== null)
        <article class="panel"><small>COMMERCE + POS · RECOGNIZED</small><h3>Bán hàng & khách hàng</h3><dl>
            <div><dt>Đơn paid / fulfilled</dt><dd>{{ number_format($operatingSummary['commerceData']['paid_orders']) }} / {{ number_format($operatingSummary['commerceData']['fulfilled_orders']) }}</dd></div>
            <div><dt>POS receipt / Khách quay lại</dt><dd>{{ number_format($operatingSummary['commerceData']['receipts']) }} / {{ number_format($operatingSummary['commerceData']['repeat_customers']) }}</dd></div>
            <div><dt>Doanh thu thuần ghi nhận</dt><dd>{{ number_format($operatingSummary['commerceData']['net_recognized_revenue']) }} ₫</dd></div>
        </dl><a class="button ghost" href="{{ route('admin.branch-commerce.dashboard', ['company_id' => request('company_id')]) }}">Mở Commerce/POS</a></article>
        @endif

        @if($operatingSummary['inventoryData'] !== null)
        <article class="panel"><small>SUPPLY CHAIN · OWNER READ MODELS</small><h3>Chuỗi cung ứng</h3><dl>
            <div><dt>SKU / On hand</dt><dd>{{ number_format($operatingSummary['inventoryData']['skus']) }} / {{ number_format($operatingSummary['inventoryData']['on_hand']) }}</dd></div>
            <div><dt>Tồn thấp / Hết hàng</dt><dd>{{ number_format($operatingSummary['inventoryData']['low_stock']) }} / {{ number_format($operatingSummary['inventoryData']['out_of_stock']) }}</dd></div>
            <div><dt>Open PO / Pending receipt</dt><dd>{{ number_format($operatingSummary['inventoryData']['open_purchase_orders']) }} / {{ number_format($operatingSummary['inventoryData']['pending_receipts']) }}</dd></div>
            <div><dt>Open demand / Pending fulfillment</dt><dd>{{ number_format($operatingSummary['inventoryData']['open_sales_demand']) }} / {{ number_format($operatingSummary['inventoryData']['pending_fulfillment']) }}</dd></div>
            <div><dt>Inventory value</dt><dd>{{ number_format($operatingSummary['inventoryData']['inventory_value']) }} ₫</dd></div>
        </dl><a class="button ghost" href="{{ route('admin.supply-chain.index', ['company_id' => request('company_id')]) }}">Mở Tổng quan chuỗi cung ứng</a></article>
        @endif

        @if($operatingSummary['financeData'] !== null)
        <article class="panel"><small>FINANCE · POSTED GL / SUBLEDGERS</small><h3>Tài chính</h3><dl>
            <div><dt>Revenue / COGS</dt><dd>{{ number_format($operatingSummary['financeData']['revenue']) }} / {{ number_format($operatingSummary['financeData']['cogs']) }} ₫</dd></div>
            <div><dt>Gross / Operating Profit</dt><dd>{{ number_format($operatingSummary['financeData']['gross_profit']) }} / {{ number_format($operatingSummary['financeData']['operating_profit']) }} ₫</dd></div>
            <div><dt>AR / AP Outstanding</dt><dd>{{ number_format($operatingSummary['financeData']['ar_outstanding']) }} / {{ number_format($operatingSummary['financeData']['ap_outstanding']) }} ₫</dd></div>
            <div><dt>Net Cash / Budget Available</dt><dd>{{ number_format($operatingSummary['financeData']['net_cash_movement']) }} / {{ number_format($operatingSummary['financeData']['budget_available']) }} ₫</dd></div>
        </dl><a class="button ghost" href="{{ route('admin.finance.overview', ['company_id' => request('company_id')]) }}">Mở Tổng quan tài chính</a></article>
        @endif
    </div>
</section>
@endif

<section class="dashboard-grid">
    <article class="panel executive-panel">
        <small>WORKFORCE INTELLIGENCE</small>
        <h2>Nhân lực trong một nhịp nhìn.</h2>
        <p>Dữ liệu tổ chức, biến động nhân sự và nhu cầu phê duyệt được tổng hợp tươi theo Company qua CQRS Query Bus; dashboard không giữ một bản số liệu riêng.</p>
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

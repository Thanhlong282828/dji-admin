@extends('layouts.admin')

@section('title', 'Tổng quan điều hành')

@section('content')
<div class="executive-command-center" data-executive-command-center>
@php
    $dashboardUser = auth()->user();
    $dashboardCompanyId = (int) $dashboardCompanyId;
    $dashboardGroups = $dashboardUser instanceof \App\Models\User
        ? app(\App\Support\Navigation\AdminNavigation::class)->for($dashboardUser, request(), $dashboardCompanyId)
        : [];
    $dashboardHelp = $dashboardUser instanceof \App\Models\User
        ? app(\App\Support\Navigation\AdminHelpRegistry::class)->resolve(request()->route()?->getName(), $dashboardCompanyId)
        : null;
@endphp
<section class="command-center-intro command-center-intro--v4">
    <div class="command-center-intro__copy">
        <span class="command-center-intro__eyebrow"><i aria-hidden="true"></i> ERP COMMAND CENTER · COMPANY #{{ $dashboardCompanyId }}</span>
        <h1>Tổng quan điều hành ERP</h1>
        <p>Điểm kiểm soát hợp nhất cho nhân lực, vận hành và hệ sinh thái ứng dụng doanh nghiệp.</p>
        <div class="command-center-intro__status" aria-label="Trạng thái dữ liệu">
            <span><i aria-hidden="true"></i> Read model trực tiếp</span>
            <span>{{ count($dashboardGroups) }} domain được cấp quyền</span>
        </div>
    </div>
    <div class="command-center-intro__actions" aria-label="Thao tác tổng quan điều hành">
        @can('payroll.assignment.view')<button type="button" class="header-icon-button" aria-label="Compensation Reference" title="Compensation Reference" data-compensation-reference-open data-url="{{ route('admin.compensation-reference', ['company_id' => $dashboardCompanyId]) }}">$</button>@endcan
        @if($dashboardHelp !== null)<button type="button" class="header-icon-button" aria-label="Hướng dẫn sử dụng trang này" title="Hướng dẫn sử dụng trang này" data-admin-help-open>?</button>@endif
        @can('article.create')<a class="erp-button erp-button--primary" href="{{ route('articles.create', ['company_id' => $dashboardCompanyId]) }}">+ Tạo bài viết</a>@endcan
        <button class="erp-button erp-button--secondary" type="button" data-executive-onboarding-open aria-haspopup="dialog">Hướng dẫn nhanh</button>
    </div>
</section>
<div class="executive-command-center__dashboard executive-command-center__dashboard--without-hero">
    <x-erp.dashboard-section title="Chỉ số điều hành" description="Dữ liệu canonical theo Company và quyền hiện tại; mỗi thẻ là một tín hiệu vận hành thật.">
        <section class="erp-kpi-grid erp-kpi-grid--command" aria-label="Chỉ số điều hành doanh nghiệp">
            <x-erp.kpi-card icon="◈" label="Tổng nhân sự" :value="number_format($totalEmployees)" meta="HEADCOUNT · EMPLOYEE SOURCE" />
            <x-erp.kpi-card icon="✓" label="Đang làm việc" :value="number_format($activeEmployees)" meta="ACTIVE TALENT" />
            <x-erp.kpi-card icon="◷" label="Phép chờ duyệt" :value="number_format($pendingLeaveRequests)" meta="ACTION REQUIRED" />
            <x-erp.kpi-card icon="◎" label="Vị trí đang tuyển" :value="number_format($openRecruitmentRequisitions)" meta="OPEN REQUISITIONS" />
            @can('company.view')
                <x-erp.kpi-card icon="◇" label="Tổng số công ty" :value="number_format($companyStats['total'])" meta="LEGAL ENTITIES" :href="route('admin.companies.index')" action-label="Mở danh sách công ty" />
                <x-erp.kpi-card icon="●" label="Đang hoạt động" :value="number_format($companyStats['active'])" meta="ACTIVE" :href="route('admin.companies.index', ['status' => 'active'])" action-label="Xem công ty hoạt động" />
                <x-erp.kpi-card icon="○" label="Ngừng hoạt động" :value="number_format($companyStats['inactive'])" meta="INACTIVE" :href="route('admin.companies.index', ['status' => 'inactive'])" action-label="Xem công ty ngừng hoạt động" />
            @endcan
        </section>
    </x-erp.dashboard-section>
</div>
<section class="erp-ecosystem erp-ecosystem--launcher" aria-labelledby="erp-ecosystem-title" data-erp-ecosystem>
    <header class="executive-widget__header erp-launcher-header"><div><small>ERP APPLICATION ECOSYSTEM</small><h2 id="erp-ecosystem-title">Ứng dụng doanh nghiệp</h2><p>Mở nhanh domain và workspace canonical trong đúng phạm vi quyền hiện tại.</p></div><div class="erp-launcher-tools"><label for="erp-launcher-search">Tìm workspace<span aria-hidden="true">⌕</span><input id="erp-launcher-search" type="search" placeholder="Payroll, Inventory, Finance…" autocomplete="off" data-erp-launcher-search></label><span data-erp-launcher-count>{{ count($dashboardGroups) }} ứng dụng</span></div></header>
    <div class="erp-ecosystem__grid">
        @foreach($dashboardGroups as $group)
            @foreach($group['items'] as $item)
                @if($loop->first)
                    <a class="erp-domain-card erp-domain-card--launcher" href="{{ route($item['route'], $item['parameters']) }}" data-erp-launcher-item data-search="{{ str($group['label'].' '.$item['label'])->lower() }}" @if($group['active']) aria-current="page" @endif>
                        <span class="erp-domain-card__icon" aria-hidden="true">{{ $group['icon'] }}</span>
                        <span class="erp-domain-card__copy"><strong>{{ $group['label'] }}</strong><small>{{ count($group['items']) }} workspace</small></span>
                        <span class="erp-domain-card__launch" aria-hidden="true">↗</span>
                    </a>
                @endif
            @endforeach
        @endforeach
    </div>
    <p class="erp-launcher-empty" data-erp-launcher-empty hidden>Không có workspace được cấp quyền khớp từ khóa này.</p>
</section>
@if($operatingSummary !== null)
<section class="executive-board" aria-labelledby="executive-board-title">
    <header class="executive-board__header">
        <div><small>OPERATING BOARD · LIVE READ MODELS</small><h2 id="executive-board-title">Bảng điều hành hôm nay</h2><p>Các tín hiệu được phân nhóm theo mức độ cần chú ý; chọn thẻ để mở nguồn canonical.</p></div>
        <div class="executive-board__tools">
            <span class="executive-board__live" title="Đọc trực tiếp khi tải trang; không dùng cache Dashboard"><i aria-hidden="true"></i> {{ \Carbon\CarbonImmutable::parse($operatingSummary['freshness']['generated_at'])->format('H:i:s') }} · Live request</span>
            <button class="erp-button erp-button--secondary" type="button" data-executive-onboarding-open aria-haspopup="dialog">Hướng dẫn</button>
            @if(request('company_id'))
                @can('analytics.view')<a class="erp-button erp-button--primary" href="{{ route('admin.executive.index', ['company_id' => request('company_id')]) }}">Phân tích</a>@endcan
            @endif
        </div>
    </header>
    <div class="executive-board__lanes">
        <section aria-labelledby="board-attention-title">
            <header><span>01</span><div><h3 id="board-attention-title">Cần chú ý</h3><small>REVIEW QUEUE</small></div></header>
            <a href="{{ route('admin.hr.operations.index', ['company_id' => request('company_id')]) }}"><strong>{{ number_format($operatingSummary['hrOperations']['contracts_expiring_30']) }}</strong><span>Hợp đồng hết hạn trong 30 ngày</span><small>HR OPERATIONS →</small></a>
            <a href="{{ route('admin.finance.accounting-adjustments', ['company_id' => request('company_id')]) }}"><strong>{{ number_format($operatingSummary['hrOperations']['pending_accounting_adjustments']) }}</strong><span>Accounting adjustments chờ xử lý</span><small>IMMUTABLE JOURNAL →</small></a>
        </section>
        <section aria-labelledby="board-workforce-title">
            <header><span>02</span><div><h3 id="board-workforce-title">Nhân lực</h3><small>WORKFORCE FLOW</small></div></header>
            <article><strong>{{ number_format($activeEmployees) }}</strong><span>Nhân sự đang làm việc</span><small>EMPLOYEE SOURCE</small></article>
            @if($operatingSummary['recruitmentData'] !== null)<a href="{{ route('admin.recruitment.dashboard', ['company_id' => request('company_id')]) }}"><strong>{{ number_format($operatingSummary['recruitmentData']['open_requisitions']) }}</strong><span>Yêu cầu tuyển dụng đang mở</span><small>RECRUITMENT →</small></a>@endif
        </section>
        <section aria-labelledby="board-business-title">
            <header><span>03</span><div><h3 id="board-business-title">Kinh doanh</h3><small>BUSINESS PULSE</small></div></header>
            @if($operatingSummary['commerceData'] !== null)<a href="{{ route('admin.branch-commerce.dashboard', ['company_id' => request('company_id')]) }}"><strong>{{ number_format($operatingSummary['commerceData']['net_recognized_revenue']) }} ₫</strong><span>Doanh thu thuần ghi nhận</span><small>COMMERCE + POS →</small></a>@endif
            @if($operatingSummary['inventoryData'] !== null)<a href="{{ route('admin.supply-chain.index', ['company_id' => request('company_id')]) }}"><strong>{{ number_format($operatingSummary['inventoryData']['low_stock']) }}</strong><span>SKU tồn thấp</span><small>SCM / WMS →</small></a>@endif
        </section>
        @if($operatingSummary['procurementWorkflow']['tasks'] !== [])
        <section aria-labelledby="board-procurement-title">
            <header><span>04</span><div><h3 id="board-procurement-title">Mua hàng</h3><small>PROCURE-TO-PAY QUEUE</small></div></header>
            @foreach($operatingSummary['procurementWorkflow']['tasks'] as $task)
                @php($taskRoute = match($task['key']) {
                    'purchase_requests' => 'admin.procurement.requests',
                    'purchase_orders' => 'admin.procurement.orders',
                    'goods_receipts' => 'admin.procurement.receipts',
                    'vendor_bills', 'overdue_vendor_bills' => 'admin.procurement.payables',
                })
                <a href="{{ route($taskRoute, ['company_id' => $dashboardCompanyId]) }}"><strong>{{ number_format($task['count']) }}</strong><span>{{ $task['label'] }}</span><small>{{ $task['state'] }} →</small></a>
            @endforeach
        </section>
        @endif
    </div>
</section>
@endif

@if($enterpriseSummary !== null)
<section class="enterprise-action-center" aria-labelledby="enterprise-action-center-title">
    <header class="enterprise-action-center__header">
        <div><small>EXECUTIVE ACTION CENTER · PERMISSION PROJECTED</small><h2 id="enterprise-action-center-title">Điều hành theo nguồn nghiệp vụ</h2><p>Tổng hợp từ read model sở hữu dữ liệu; mỗi tín hiệu mở đúng workspace canonical và không tạo bản sao giao dịch.</p></div>
        <span><i aria-hidden="true"></i> {{ \Carbon\CarbonImmutable::parse($enterpriseSummary['generated_at'])->format('H:i:s') }} · REQUEST FRESH</span>
    </header>

    @if($enterpriseSummary['kpis'] !== [])
    <div class="enterprise-performance-grid" aria-label="Hiệu suất doanh nghiệp">
        @foreach($enterpriseSummary['kpis'] as $metric)
            <a class="enterprise-performance-card" href="{{ route($metric['route'], array_filter(['company_id' => $dashboardCompanyId, 'branch_id' => $dashboardFilters['branch_id']])) }}">
                <span>{{ $metric['status'] }}</span><strong>{{ number_format($metric['value']) }} <small>₫</small></strong><h3>{{ $metric['label'] }}</h3><p>{{ $metric['source'] }}</p><em>Mở nguồn canonical →</em>
            </a>
        @endforeach
    </div>
    @endif

    <div class="enterprise-operations-grid">
        <article class="enterprise-command-widget enterprise-command-widget--queue">
            <header><div><small>WORKFLOW & RISK</small><h3>Hàng đợi cần hành động</h3></div><span>{{ count($enterpriseSummary['exceptions']) }}</span></header>
            <div class="enterprise-command-list">
                @forelse($enterpriseSummary['exceptions'] as $exception)
                    <a href="{{ route($exception['route'], array_filter(['company_id' => $dashboardCompanyId, 'branch_id' => $dashboardFilters['branch_id']])) }}"><i class="is-{{ strtolower($exception['severity']) }}" aria-hidden="true"></i><span><strong>{{ $exception['label'] }}</strong><small>{{ $exception['severity'] }} · canonical workspace</small></span><b>{{ $exception['value'] === null ? 'Kiểm tra' : number_format($exception['value']) }}</b></a>
                @empty
                    <div class="enterprise-command-empty"><strong>Không có hàng đợi được cấp quyền</strong><p>Dashboard không hiển thị dữ liệu của domain người dùng không được phép truy cập.</p></div>
                @endforelse
            </div>
        </article>

        @if($enterpriseSummary['people'] !== [] || $enterpriseSummary['crm'] !== [])
        <article class="enterprise-command-widget">
            <header><div><small>PEOPLE & CUSTOMER PULSE</small><h3>Năng lực vận hành</h3></div><span>LIVE</span></header>
            <dl class="enterprise-signal-grid">
                @if($enterpriseSummary['people'] !== [])
                    <div><dt>Nhân sự hiệu lực</dt><dd>{{ number_format($enterpriseSummary['people']['active_headcount'] ?? 0) }}</dd><small>Employee canonical</small></div>
                    <div><dt>Bản ghi đi trễ</dt><dd>{{ number_format($enterpriseSummary['people']['late_records'] ?? 0) }}</dd><small>Attendance kỳ chọn</small></div>
                    <div><dt>Phút tăng ca</dt><dd>{{ number_format($enterpriseSummary['people']['overtime_minutes'] ?? 0) }}</dd><small>Attendance approved source</small></div>
                @endif
                @if($enterpriseSummary['crm'] !== [])
                    <div><dt>Khách hàng active</dt><dd>{{ number_format($enterpriseSummary['crm']['active'] ?? 0) }}</dd><small>CRM segmentation</small></div>
                    <div><dt>Khách hàng rủi ro</dt><dd>{{ number_format($enterpriseSummary['crm']['at_risk'] ?? 0) }}</dd><small>CRM canonical</small></div>
                @endif
            </dl>
            <nav class="enterprise-command-widget__links" aria-label="Nguồn năng lực vận hành">
                @can('employee.view')<a href="{{ route('admin.employees.index', ['company_id' => $dashboardCompanyId]) }}">Nhân sự →</a>@endcan
                @can('attendance.view')<a href="{{ route('admin.attendance.index', ['company_id' => $dashboardCompanyId]) }}">Chấm công →</a>@endcan
                @can('crm.view')<a href="{{ route('admin.crm.index', ['company_id' => $dashboardCompanyId]) }}">CRM →</a>@endcan
            </nav>
        </article>
        @endif

        @if($enterpriseSummary['panels']['project'] !== [] || $enterpriseSummary['panels']['maintenance'] !== [])
        <article class="enterprise-command-widget">
            <header><div><small>PROJECTS & ASSET HEALTH</small><h3>Sức khỏe thực thi</h3></div><span>CONTROL</span></header>
            <dl class="enterprise-signal-grid">
                @if($enterpriseSummary['panels']['project'] !== [])
                    <div><dt>Dự án đang chạy</dt><dd>{{ number_format($enterpriseSummary['panels']['project']['active'] ?? 0) }}</dd><small>Project ACTIVE</small></div>
                    <div><dt>Dự án rủi ro / trễ</dt><dd>{{ number_format(($enterpriseSummary['panels']['project']['at_risk'] ?? 0) + ($enterpriseSummary['panels']['project']['delayed'] ?? 0)) }}</dd><small>Task và target date</small></div>
                @endif
                @if($enterpriseSummary['panels']['maintenance'] !== [])
                    <div><dt>Work Order mở</dt><dd>{{ number_format($enterpriseSummary['panels']['maintenance']['open'] ?? 0) }}</dd><small>Maintenance lifecycle</small></div>
                    <div><dt>Work Order quá hạn</dt><dd>{{ number_format($enterpriseSummary['panels']['maintenance']['overdue'] ?? 0) }}</dd><small>Due date canonical</small></div>
                @endif
            </dl>
            <nav class="enterprise-command-widget__links" aria-label="Nguồn dự án và tài sản">
                @can('project.view')<a href="{{ route('admin.projects.overview', ['company_id' => $dashboardCompanyId]) }}">Dự án →</a>@endcan
                @can('maintenance.view')<a href="{{ route('admin.maintenance.overview', ['company_id' => $dashboardCompanyId]) }}">Bảo trì →</a>@endcan
            </nav>
        </article>
        @endif
    </div>
</section>
@endif

@if($operatingSummary !== null)
<section class="command-analytics" aria-labelledby="command-analytics-title" data-command-analytics data-inventory='@json($operatingSummary['analytics']['inventory_composition'])' data-procurement='@json($operatingSummary['analytics']['procurement_trend'])' data-revenue='@json($operatingSummary['analytics']['recognized_revenue_trend'])'>
    <header class="executive-widget__header command-analytics__header">
        <div><small>VERIFIED READ MODELS · NO FABRICATED KPI</small><h2 id="command-analytics-title">Tín hiệu vận hành đa miền</h2><p>Inventory, Procurement và Commerce/POS từ nguồn canonical trong phạm vi Company{{ $dashboardFilters['branch_id'] ? ' và Branch đã xác thực' : '' }}.</p></div>
        <form class="command-analytics__filters" method="get" action="{{ route('dashboard') }}"><input type="hidden" name="company_id" value="{{ $dashboardCompanyId }}">@if($dashboardFilters['branch_id'])<input type="hidden" name="branch_id" value="{{ $dashboardFilters['branch_id'] }}">@endif<label for="dashboard-period">Khoảng thời gian<select id="dashboard-period" name="period" onchange="this.form.submit()">@foreach([30 => '30 ngày', 90 => '90 ngày', 365 => '12 tháng'] as $days => $label)<option value="{{ $days }}" @selected($dashboardFilters['period'] === $days)>{{ $label }}</option>@endforeach</select></label></form>
    </header>
    <div class="command-analytics__grid">
        @php($inventoryChart = $operatingSummary['analytics']['inventory_composition'])
        @if($inventoryChart !== null)
        <article class="command-chart command-chart--donut {{ $inventoryChart['status'] === 'READY' ? '' : 'command-chart--empty' }}"><header><div><small>WMS · CURRENT SNAPSHOT</small><h3>Cơ cấu giá trị tồn kho</h3></div><span>{{ $inventoryChart['currency'] ?: 'CURRENCY REQUIRED' }}</span></header>
            @if($inventoryChart['status'] === 'READY')
                <div class="command-chart__canvas-wrap"><canvas data-command-chart="inventory" width="420" height="300" role="img" tabindex="0" aria-describedby="inventory-chart-help" aria-label="Biểu đồ doughnut cơ cấu giá trị tồn kho theo danh mục"></canvas><strong>{{ number_format($inventoryChart['total_minor']) }}<small>{{ $inventoryChart['currency'] }}</small></strong><output class="command-chart__tooltip" data-command-tooltip="inventory" hidden></output></div><p class="sr-only" id="inventory-chart-help">Di chuyển chuột hoặc dùng phím mũi tên để xem từng danh mục tồn kho.</p><div class="command-chart__legend" data-command-legend="inventory"></div>
                <details><summary>Xem bảng dữ liệu</summary><div class="erp-table-scroll" tabindex="0"><table><thead><tr><th scope="col">Danh mục</th><th scope="col">Giá trị tồn</th><th scope="col">Available units</th></tr></thead><tbody>@foreach($inventoryChart['items'] as $item)<tr><th scope="row">{{ $item['label'] }}</th><td>{{ number_format($item['value_minor']) }} {{ $inventoryChart['currency'] }}</td><td>{{ number_format($item['available_units']) }}</td></tr>@endforeach</tbody></table></div></details>
            @else<div class="command-chart__empty"><strong>{{ $inventoryChart['status'] }}</strong><p>{{ $inventoryChart['status'] === 'FUNCTIONAL_CURRENCY_REQUIRED' ? 'Cần cấu hình functional currency trước khi tổng hợp giá trị tồn kho.' : 'Chưa có giá trị tồn kho theo danh mục trong phạm vi hiện tại.' }}</p><a href="{{ route('admin.seller.inventory', ['company_id' => $dashboardCompanyId]) }}">Mở Inventory →</a></div>@endif
        </article>
        @endif
        @php($procurementChart = $operatingSummary['analytics']['procurement_trend'])
        @if($procurementChart !== null)
        <article class="command-chart {{ $procurementChart['status'] === 'READY' ? '' : 'command-chart--empty' }}"><header><div><small>PMS · APPROVED/ISSUED LIFECYCLE</small><h3>Xu hướng giá trị đơn mua</h3></div><span>{{ $procurementChart['currency'] ?: 'SCOPE BLOCKED' }}</span></header>
            @if($procurementChart['status'] === 'READY')<div class="command-chart__canvas-wrap"><canvas data-command-chart="procurement" width="760" height="300" role="img" tabindex="0" aria-describedby="procurement-chart-help" aria-label="Biểu đồ đường giá trị đơn mua hợp lệ theo tháng"></canvas><output class="command-chart__tooltip" data-command-tooltip="procurement" hidden></output></div><p class="sr-only" id="procurement-chart-help">Di chuyển chuột hoặc dùng phím mũi tên để xem giá trị từng kỳ.</p><p class="command-chart__note">PO approved/sent/partially received/received/closed; không phải chi phí đã ghi sổ.</p>
            @else<div class="command-chart__empty"><strong>{{ $procurementChart['status'] }}</strong><p>{{ $procurementChart['status'] === 'MULTI_CURRENCY_BLOCKED' ? 'Có nhiều tiền tệ trong kỳ; Dashboard không tự quy đổi hoặc cộng gộp.' : 'Chưa có Purchase Order hợp lệ trong kỳ đã chọn.' }}</p><a href="{{ route('admin.procurement.orders', ['company_id' => $dashboardCompanyId]) }}">Mở Purchase Orders →</a></div>@endif
        </article>
        @endif
        @php($revenueChart = $operatingSummary['analytics']['recognized_revenue_trend'])
        @if($revenueChart !== null)
        <article class="command-chart command-chart--wide {{ $revenueChart['status'] === 'READY' ? '' : 'command-chart--empty' }}"><header><div><small>OMS / POS · RECOGNIZED SOURCE ONLY</small><h3>Doanh thu đã ghi nhận</h3></div><span>{{ $revenueChart['currency'] ?: 'SCOPE BLOCKED' }}</span></header>
            @if($revenueChart['status'] === 'READY')<div class="command-chart__canvas-wrap"><canvas data-command-chart="revenue" width="1180" height="280" role="img" tabindex="0" aria-describedby="revenue-chart-help" aria-label="Biểu đồ cột doanh thu Commerce và POS đã ghi nhận theo tháng"></canvas><output class="command-chart__tooltip" data-command-tooltip="revenue" hidden></output></div><p class="sr-only" id="revenue-chart-help">Di chuyển chuột hoặc dùng phím mũi tên để xem doanh thu từng kỳ.</p><p class="command-chart__note">Chỉ lấy management revenue trạng thái recognized; không cộng Order hoặc Invoice lần hai.</p>
            @else<div class="command-chart__empty"><strong>{{ $revenueChart['status'] }}</strong><p>{{ $revenueChart['status'] === 'MULTI_CURRENCY_BLOCKED' ? 'Có nhiều tiền tệ trong kỳ; cần nguồn FX đã xác minh trước khi tổng hợp.' : 'Chưa có doanh thu Commerce/POS được ghi nhận trong kỳ.' }}</p><a href="{{ route('admin.branch-commerce.dashboard', ['company_id' => $dashboardCompanyId]) }}">Mở Commerce/POS →</a></div>@endif
        </article>
        @endif
    </div>
</section>
@endif

@if($branchSummary !== null)
<section class="panel operational-command-section" data-command-section>
    <header class="section-heading operational-command-section__header"><div><small>BRANCH OPERATIONS · COMPANY SCOPE</small><h2>Vận hành chi nhánh</h2><p>Nguồn Branch, Commerce/POS và Finance canonical trong phạm vi hiện tại.</p></div><button type="button" class="operational-command-section__toggle" data-command-section-toggle aria-expanded="true">Thu gọn <span aria-hidden="true">⌃</span></button></header>
    <div class="operational-command-section__body" data-command-section-body>
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
            <div class="erp-trend__chart" role="img" tabindex="0" aria-label="Biểu đồ tài chính quản trị sáu tháng theo dữ liệu Branch Finance">
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
    </div>
</section>
@endif

<div class="executive-lower-command-grid">
@if($operatingSummary !== null)
<section class="panel operating-cockpit operational-command-section executive-operations-hub" data-command-section>
    <header class="section-heading operational-command-section__header"><div><small>EXECUTIVE OPERATIONS · CANONICAL SOURCES</small><h2>Trung tâm vận hành liên phòng ban</h2><p>Hàng đợi, chỉ số và lifecycle lấy từ nguồn nghiệp vụ theo đúng quyền Company/Branch hiện tại.</p></div><button type="button" class="operational-command-section__toggle" data-command-section-toggle aria-expanded="true">Thu gọn <span aria-hidden="true">⌃</span></button></header>
    <div class="operational-command-section__body" data-command-section-body>
    <div class="stats">
        <article><span>Đơn vị / Vị trí</span><strong>{{ number_format($operatingSummary['organization']['units']) }} / {{ number_format($operatingSummary['organization']['positions']) }}</strong><i>ORGANIZATION</i></article>
        <article><span>Headcount được duyệt</span><strong>{{ number_format($operatingSummary['organization']['approved_headcount']) }}</strong><i>JOB POSITIONS</i></article>
        <article><span>Seller / Store</span><strong>{{ number_format($operatingSummary['organization']['sellers']) }} / {{ number_format($operatingSummary['organization']['stores']) }}</strong><i>COMPANY OWNERSHIP</i></article>
        <a href="{{ route('admin.operations.data-quality', ['company_id' => request('company_id')]) }}"><article><span>Data Quality</span><strong>{{ number_format($operatingSummary['quality']['passed']) }}/{{ number_format($operatingSummary['quality']['rules']) }}</strong><i>{{ $operatingSummary['quality']['failed'] === 0 ? 'PASS' : 'ATTENTION' }}</i></article></a>
    </div>

    @if(($operatingSummary['enterpriseWorkflow'] ?? []) !== [])
    <section class="executive-action-queue" aria-labelledby="executive-action-queue-title">
        <header><div><small>WORKFLOW & APPROVALS</small><h3 id="executive-action-queue-title">Việc cần xử lý theo quyền hiện tại</h3></div><span>{{ count($operatingSummary['enterpriseWorkflow']) }} nguồn canonical</span></header>
        <div class="executive-action-queue__grid">
            @foreach($operatingSummary['enterpriseWorkflow'] as $key => $item)
                <a href="{{ route($item['route'], ['company_id' => $dashboardCompanyId]) }}" data-workflow-capability="{{ $key }}" @if(!$item['available']) data-source-blocked="true" @endif>
                    <span>{{ $item['label'] }}</span><strong>{{ $item['available'] ? number_format($item['count']) : '—' }}</strong><small>{{ $item['state'] }} <b aria-hidden="true">↗</b></small>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <div class="dashboard-grid executive-domain-intelligence-grid">
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
    </div>
</section>
@endif

<section class="executive-insight-grid" aria-label="Thông tin điều hành bổ sung">
    <article class="executive-widget executive-widget--workforce">
        <header class="executive-widget__header">
            <div><small>WORKFORCE INTELLIGENCE</small><h2>Nhân lực trong một nhịp nhìn</h2></div>
            <span class="executive-widget__metric"><strong>{{ number_format($totalEmployees) }}</strong> nhân sự</span>
        </header>
        <p class="executive-widget__description">Dữ liệu tổ chức và biến động nhân sự được tổng hợp trực tiếp qua CQRS Query Bus theo Company scope.</p>
        <div class="executive-workforce-bars" aria-label="Phân bổ nhân sự theo trạng thái">
            @forelse($headcountByStatus as $status => $count)
                @php($percent = $totalEmployees > 0 ? max(4, round(($count / $totalEmployees) * 100)) : 0)
                <div class="executive-workforce-bars__item">
                    <span><b>{{ str($status)->headline() }}</b><em>{{ number_format($count) }} · {{ $percent }}%</em></span>
                    <i aria-hidden="true"><b style="--value: {{ $percent }}%"></b></i>
                </div>
            @empty
                <div class="empty-state">Chưa có dữ liệu nhân sự. Module Employee đang trong giai đoạn hoàn thiện.</div>
            @endforelse
        </div>
    </article>

    <article class="executive-widget executive-widget--publishing">
        <header class="executive-widget__header"><div><small>BRAND PUBLISHING</small><h2>Trung tâm nội dung</h2></div><span class="executive-widget__status">LIVE</span></header>
        <div class="executive-publishing-score">
            <strong>{{ number_format($publishedArticles) }}<span>/{{ number_format($totalArticles) }}</span></strong>
            <p>Bài viết đã xuất bản</p>
        </div>
        <dl class="executive-publishing-metrics">
            <div><dt>Bản nháp</dt><dd>{{ number_format($draftArticles) }}</dd></div>
            <div><dt>Nổi bật</dt><dd>{{ number_format($featuredArticles) }}</dd></div>
        </dl>
        @can('article.create')<a class="erp-button erp-button--primary executive-widget__action" href="{{ route('articles.create') }}">Tạo nội dung mới <span aria-hidden="true">→</span></a>@endcan
    </article>
</section>

<section class="executive-widget executive-widget--activity" aria-labelledby="recent-activity-title">
    <header class="executive-widget__header">
        <div><small>AUDIT STREAM · LIVE EVIDENCE</small><h2 id="recent-activity-title">Hoạt động gần đây</h2></div>
        <span class="executive-board__live"><i aria-hidden="true"></i> Đang đồng bộ</span>
    </header>
    <div class="executive-activity-list">
        @forelse($recentActivities as $activity)
            <article>
                <span class="executive-activity-list__icon" aria-hidden="true">◆</span>
                <div><strong>{{ str($activity['description'])->headline() }}</strong><small>{{ $activity['subject_type'] ?: 'System event' }}</small></div>
                <time>{{ $activity['created_at'] }}</time>
            </article>
        @empty
            <div class="empty-state">Chưa phát sinh activity log.</div>
        @endforelse
    </div>
</section>
</div>

<div class="executive-onboarding" data-executive-onboarding hidden>
    <button class="executive-onboarding__backdrop" type="button" data-executive-onboarding-close aria-label="Đóng hướng dẫn"></button>
    <section class="executive-onboarding__sheet" role="dialog" aria-modal="true" aria-labelledby="executive-onboarding-title" tabindex="-1">
        <header><div><small>QUICK START · 4 STEPS</small><h2 id="executive-onboarding-title">Làm chủ trung tâm điều hành</h2></div><button type="button" data-executive-onboarding-close aria-label="Đóng hướng dẫn">×</button></header>
        <div class="executive-onboarding__progress" aria-label="Tiến độ hướng dẫn"><i data-executive-onboarding-progress></i></div>
        <div class="executive-onboarding__steps">
            <article data-executive-onboarding-step><span>01</span><h3>Đọc tín hiệu ưu tiên</h3><p>Bắt đầu từ KPI và Bảng điều hành hôm nay để nhận biết phê duyệt, dữ liệu và hàng đợi cần xử lý.</p></article>
            <article data-executive-onboarding-step hidden><span>02</span><h3>Đi tới nguồn dữ liệu</h3><p>Mỗi card có liên kết đến workspace canonical. Dashboard chỉ tổng hợp, không tạo bản sao số liệu.</p></article>
            <article data-executive-onboarding-step hidden><span>03</span><h3>Đối chiếu theo domain</h3><p>Mở các widget HRM, Commerce, WMS hoặc Finance để kiểm tra chứng từ và trạng thái nghiệp vụ gốc.</p></article>
            <article data-executive-onboarding-step hidden><span>04</span><h3>Theo dõi audit stream</h3><p>Dùng hoạt động gần đây để truy vết thay đổi; mọi quyết định vẫn tuân thủ quyền và Company scope.</p></article>
        </div>
        <footer><button class="erp-button erp-button--secondary" type="button" data-executive-onboarding-prev>Quay lại</button><span><b data-executive-onboarding-current>1</b> / 4</span><button class="erp-button erp-button--primary" type="button" data-executive-onboarding-next>Tiếp tục</button></footer>
    </section>
</div>
</div>
@endsection

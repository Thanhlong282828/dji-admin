<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="schema-user-id" content="{{ auth()->id() }}">@endauth
    <title>@yield('title', 'Control Center') · SCHEMA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        nav a.active{background:#17140e;color:var(--gold);border-color:#3a301d}.mobile-nav-toggle,.mobile-nav-label{display:none}.nav-section{margin:20px 14px 4px;color:#686868;font-size:8px;letter-spacing:.2em}.nav-disabled{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 14px;color:#555;font-size:12px}.nav-disabled i{font-size:7px;font-style:normal;color:#826d39;border:1px solid #3d3421;border-radius:30px;padding:4px 6px}.dashboard-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:20px;margin-top:20px}.executive-panel h2{font:400 clamp(32px,4vw,54px)/1.05 Georgia,serif;margin:14px 0}.executive-panel p,.content-panel p{color:#999;line-height:1.65}.metric-bars{display:grid;gap:14px;margin-top:28px}.metric-bars>div>span{display:flex;justify-content:space-between;color:#aaa;font-size:12px}.metric-bars i{display:block;height:5px;background:#292929;margin-top:7px;border-radius:9px;overflow:hidden}.metric-bars i:after{content:"";display:block;width:var(--value);height:100%;background:linear-gradient(90deg,#8b6721,var(--gold))}.content-panel h3{font:400 64px Georgia,serif;margin:22px 0 0;color:var(--gold)}.content-panel dl{margin:24px 0}.content-panel dl div{display:flex;justify-content:space-between;border-top:1px solid var(--line);padding:13px 0}.content-panel dt{color:#888}.content-panel dd{margin:0;color:#fff}.activity-panel{margin-top:20px}.activity-panel h2{font:400 30px Georgia,serif;margin:8px 0 18px}.activity-list{display:grid}.activity-list>div{display:flex;justify-content:space-between;gap:20px;padding:13px 0;border-top:1px solid var(--line)}.activity-list small,.empty-state{color:#777}@media(max-width:950px){.dashboard-grid{grid-template-columns:1fr}}@media(max-width:760px){.mobile-nav-label{display:flex;align-items:center;justify-content:space-between;margin-top:16px;padding:11px 13px;border:1px solid #3d3421;border-radius:8px;color:var(--gold);font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;cursor:pointer}.mobile-nav-label:after{content:'+';font-size:18px}.mobile-nav-toggle:not(:checked)~nav{display:none}.mobile-nav-toggle:checked~.mobile-nav-label:after{content:'−'}.mobile-nav-toggle:checked~nav{display:grid;max-height:min(58vh,480px)}}
    </style>
    @stack('styles')
</head>
<body>
<aside>
    <a class="brand" href="{{ route('dashboard') }}">SCHEMA<span>CONTROL CENTER</span></a>
    <input class="mobile-nav-toggle" type="checkbox" id="admin-mobile-navigation">
    <label class="mobile-nav-label" for="admin-mobile-navigation">Menu quản trị</label>
    <nav aria-label="Điều hướng quản trị">
        @can('dashboard.view')
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Tổng quan</a>
        @endcan
        @can('article.view')
            <a class="{{ request()->routeIs('articles.index', 'articles.edit') ? 'active' : '' }}" href="{{ route('articles.index') }}">Kho nội dung</a>
        @endcan
        @can('article.create')
            <a class="{{ request()->routeIs('articles.create') ? 'active' : '' }}" href="{{ route('articles.create') }}">Viết bài mới</a>
        @endcan
        <span class="nav-section">HRM PLATFORM</span>
        @can('company.view')
            <a class="{{ request()->routeIs('admin.companies.*') ? 'active' : '' }}" href="{{ route('admin.companies.index') }}">Công ty</a>
        @endcan
        @php($organizationCompanyId = (int) request('company_id', session('company_id', 1)))
        @can('cost-center.view')<a class="{{ request()->routeIs('admin.cost-centers.*') ? 'active' : '' }}" href="{{ route('admin.cost-centers.index',['company_id'=>$organizationCompanyId]) }}">Trung tâm chi phí</a>@endcan
        @can('organization-unit.view')<a class="{{ request()->routeIs('admin.organization-units.index','admin.organization-units.show','admin.organization-units.edit') ? 'active' : '' }}" href="{{ route('admin.organization-units.index',['company_id'=>$organizationCompanyId]) }}">Cơ cấu tổ chức</a><a class="{{ request()->routeIs('admin.organization-units.tree') ? 'active' : '' }}" href="{{ route('admin.organization-units.tree',['company_id'=>$organizationCompanyId]) }}">Cây tổ chức</a>@endcan
        @can('job-position.view')<a class="{{ request()->routeIs('admin.job-positions.*') ? 'active' : '' }}" href="{{ route('admin.job-positions.index',['company_id'=>$organizationCompanyId]) }}">Vị trí công việc</a>@endcan
        @can('organization-unit.view')<a class="{{ request()->routeIs('admin.organization.headcount') ? 'active' : '' }}" href="{{ route('admin.organization.headcount',['company_id'=>$organizationCompanyId]) }}">Báo cáo định biên</a>@endcan
        <span class="nav-section">NHÂN SỰ</span>
        @can('employee.view')<a class="{{ request()->routeIs('admin.employees.*') ? 'active' : '' }}" href="{{ route('admin.employees.index',['company_id'=>$organizationCompanyId]) }}">Danh sách nhân viên</a>@endcan
        @can('hr.lifecycle.view')<a class="{{ request()->routeIs('admin.employee-lifecycle.*') ? 'active' : '' }}" href="{{ route('admin.employee-lifecycle.index',['company_id'=>$organizationCompanyId]) }}">Vòng đời nhân sự</a>@endcan
        @can('talent.performance.view')<a class="{{ request()->routeIs('admin.talent.performance.*') ? 'active' : '' }}" href="{{ route('admin.talent.performance.index',['company_id'=>$organizationCompanyId]) }}">Hiệu suất & tài năng</a>@endcan
        @can('career.view')<a class="{{ request()->routeIs('admin.career.*') ? 'active' : '' }}" href="{{ route('admin.career.index',['company_id'=>$organizationCompanyId]) }}">Career & kế nhiệm</a>@endcan
        @can('analytics.view')<a class="{{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" href="{{ route('admin.analytics.workforce',['company_id'=>$organizationCompanyId]) }}">Workforce Analytics</a>@endcan
        @can('hr.governance.view')<a class="{{ request()->routeIs('admin.hr.governance.*') ? 'active' : '' }}" href="{{ route('admin.hr.governance.dashboard',['company_id'=>$organizationCompanyId]) }}">HR Data Governance</a>@endcan
        <span class="nav-section">WORKFLOW</span>
        @can('workflow.view')<a class="{{ request()->routeIs('admin.workflows.dashboard','admin.workflows.instances.*') ? 'active' : '' }}" href="{{ route('admin.workflows.dashboard',['company_id'=>$organizationCompanyId]) }}">Workflow Center</a><a class="{{ request()->routeIs('admin.workflows.tasks*') ? 'active' : '' }}" href="{{ route('admin.workflows.tasks',['company_id'=>$organizationCompanyId]) }}">My Approval Tasks</a><a class="{{ request()->routeIs('admin.workflows.definitions*') ? 'active' : '' }}" href="{{ route('admin.workflows.definitions',['company_id'=>$organizationCompanyId]) }}">Workflow Definitions</a>@endcan
        @can('notification.view')<a class="{{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index',['company_id'=>$organizationCompanyId]) }}">Notifications <i data-notification-unread>—</i></a>@endcan
        @can('iam.view')<a class="{{ request()->routeIs('admin.iam.*') ? 'active' : '' }}" href="{{ route('admin.iam.dashboard',['company_id'=>$organizationCompanyId]) }}">IAM Governance</a>@endcan
        <span class="nav-section">TUYỂN DỤNG</span>
        @can('recruitment-requisition.view')<a class="{{ request()->routeIs('admin.recruitment.dashboard') ? 'active' : '' }}" href="{{ route('admin.recruitment.dashboard',['company_id'=>$organizationCompanyId]) }}">Dashboard tuyển dụng</a><a class="{{ request()->routeIs('admin.recruitment-requisitions.*') ? 'active' : '' }}" href="{{ route('admin.recruitment-requisitions.index',['company_id'=>$organizationCompanyId]) }}">Yêu cầu tuyển dụng</a>@endcan
        @can('candidate.view')<a class="{{ request()->routeIs('admin.candidates.*') ? 'active' : '' }}" href="{{ route('admin.candidates.index',['company_id'=>$organizationCompanyId]) }}">Ứng viên</a>@endcan
        @can('interview.view')<a class="{{ request()->routeIs('admin.interviews.*') ? 'active' : '' }}" href="{{ route('admin.interviews.index',['company_id'=>$organizationCompanyId]) }}">Lịch phỏng vấn</a>@endcan
        @can('candidate-offer.view')<a class="{{ request()->routeIs('admin.candidate-offers.*') ? 'active' : '' }}" href="{{ route('admin.candidate-offers.index',['company_id'=>$organizationCompanyId]) }}">Candidate Offers</a>@endcan
        @can('application.view')<a class="{{ request()->routeIs('admin.applications.*') ? 'active' : '' }}" href="{{ route('admin.applications.pipeline',['company_id'=>$organizationCompanyId]) }}">Pipeline ứng tuyển</a>@endcan
        <span class="nav-section">ONBOARDING</span>
        @can('onboarding.view')<a class="{{ request()->routeIs('admin.onboarding.dashboard') ? 'active' : '' }}" href="{{ route('admin.onboarding.dashboard',['company_id'=>$organizationCompanyId]) }}">Dashboard onboarding</a><a class="{{ request()->routeIs('admin.onboarding-cases.*') ? 'active' : '' }}" href="{{ route('admin.onboarding-cases.index',['company_id'=>$organizationCompanyId]) }}">Hồ sơ onboarding</a>@endcan
        <span class="nav-section">TIME & PAYROLL</span>
        @can('attendance.view')<a class="{{ request()->routeIs('admin.attendance.*') ? 'active' : '' }}" href="{{ route('admin.attendance.index',['company_id'=>$organizationCompanyId]) }}">Chấm công</a>@endcan
        @can('shift.view')<a class="{{ request()->routeIs('admin.workforce.*') ? 'active' : '' }}" href="{{ route('admin.workforce.management',['section'=>'shifts','company_id'=>$organizationCompanyId]) }}">Ca & lịch làm việc</a>@endcan
        @can('leave.view')<a class="{{ request()->routeIs('admin.leave.*') ? 'active' : '' }}" href="{{ route('admin.leave.index',['company_id'=>$organizationCompanyId]) }}">Nghỉ phép</a>@endcan
        @can('overtime.view')<a class="{{ request()->routeIs('admin.overtime.*') ? 'active' : '' }}" href="{{ route('admin.overtime.index',['company_id'=>$organizationCompanyId]) }}">Overtime</a>@endcan
        @can('payroll-period.view')<a class="{{ request()->routeIs('admin.payroll.periods.*') ? 'active' : '' }}" href="{{ route('admin.payroll.periods.index',['company_id'=>$organizationCompanyId]) }}">Kỳ lương</a>@endcan
        @can('payroll-period.view')<a class="{{ request()->routeIs('admin.payroll.inputs.*', 'admin.payroll-period.*') ? 'active' : '' }}" href="{{ route('admin.payroll.inputs.index',['company_id'=>$organizationCompanyId]) }}">Payroll Inputs</a>@endcan
        @can('payroll.run.view')<a class="{{ request()->routeIs('admin.payroll.runs.*') ? 'active' : '' }}" href="{{ route('admin.payroll.runs.index',['company_id'=>$organizationCompanyId]) }}">Payroll Runs</a>@endcan
        @can('payroll.adjustment.view')<a class="{{ request()->routeIs('admin.payroll.adjustments.*') ? 'active' : '' }}" href="{{ route('admin.payroll.adjustments.index',['company_id'=>$organizationCompanyId]) }}">Payroll Adjustments</a>@endcan
        @can('payroll.annual_tax.view')<a class="{{ request()->routeIs('admin.payroll.annual-tax.*') ? 'active' : '' }}" href="{{ route('admin.payroll.annual-tax.index',['company_id'=>$organizationCompanyId]) }}">Annual PIT</a>@endcan
        @can('government.filing.view')<a class="{{ request()->routeIs('admin.payroll.government-filings.*') ? 'active' : '' }}" href="{{ route('admin.payroll.government-filings.index',['company_id'=>$organizationCompanyId]) }}">Government Filings</a>@endcan
        @can('payroll.insurance.view')<a class="{{ request()->routeIs('admin.payroll.insurance.policies.*') ? 'active' : '' }}" href="{{ route('admin.payroll.insurance.policies.index',['company_id'=>$organizationCompanyId]) }}">Insurance Policies</a><a class="{{ request()->routeIs('admin.payroll.insurance.employees.*') ? 'active' : '' }}" href="{{ route('admin.payroll.insurance.employees.index',['company_id'=>$organizationCompanyId]) }}">Employee Insurance</a>@endcan
        @can('payroll.tax.view')<a class="{{ request()->routeIs('admin.payroll.tax.policies.*') ? 'active' : '' }}" href="{{ route('admin.payroll.tax.policies.index',['company_id'=>$organizationCompanyId]) }}">Tax Policies</a><a class="{{ request()->routeIs('admin.payroll.tax.employees.*') ? 'active' : '' }}" href="{{ route('admin.payroll.tax.employees.index',['company_id'=>$organizationCompanyId]) }}">Employee Tax Profiles</a>@endcan
        @can('payroll.payslip.view')<a class="{{ request()->routeIs('admin.payroll.payslips.*') ? 'active' : '' }}" href="{{ route('admin.payroll.payslips.index',['company_id'=>$organizationCompanyId]) }}">Payslips</a>@endcan
        @can('payroll.payment.view')<a class="{{ request()->routeIs('admin.payroll.payment-accounts.*') ? 'active' : '' }}" href="{{ route('admin.payroll.payment-accounts.index',['company_id'=>$organizationCompanyId]) }}">Payment Accounts</a><a class="{{ request()->routeIs('admin.payroll.payment-batches.*') ? 'active' : '' }}" href="{{ route('admin.payroll.payment-batches.index',['company_id'=>$organizationCompanyId]) }}">Payment Batches</a>@endcan
        @can('payroll.accounting.view')<a class="{{ request()->routeIs('admin.payroll.accounting.accounts.*') ? 'active' : '' }}" href="{{ route('admin.payroll.accounting.accounts.index',['company_id'=>$organizationCompanyId]) }}">Chart of Accounts</a><a class="{{ request()->routeIs('admin.payroll.accounting.policies.*') ? 'active' : '' }}" href="{{ route('admin.payroll.accounting.policies.index',['company_id'=>$organizationCompanyId]) }}">Accounting Policies</a><a class="{{ request()->routeIs('admin.payroll.accounting.mappings.*') ? 'active' : '' }}" href="{{ route('admin.payroll.accounting.mappings.index',['company_id'=>$organizationCompanyId]) }}">Accounting Mappings</a><a class="{{ request()->routeIs('admin.payroll.accounting.journals.*') ? 'active' : '' }}" href="{{ route('admin.payroll.accounting.journals.index',['company_id'=>$organizationCompanyId]) }}">Payroll Journals</a>@endcan
        @can('erp.integration.view')<a class="{{ request()->routeIs('admin.payroll.erp.exports.*') ? 'active' : '' }}" href="{{ route('admin.payroll.erp.exports.index',['company_id'=>$organizationCompanyId]) }}">ERP Exports</a>@endcan
        @can('erp.integration.connection.view')<a class="{{ request()->routeIs('admin.payroll.erp.connections.*') ? 'active' : '' }}" href="{{ route('admin.payroll.erp.connections.index',['company_id'=>$organizationCompanyId]) }}">ERP Connections</a><a class="{{ request()->routeIs('admin.payroll.erp.mappings.*') ? 'active' : '' }}" href="{{ route('admin.payroll.erp.mappings.index',['company_id'=>$organizationCompanyId]) }}">ERP Mapping Rules</a>@endcan
        @foreach (['Phân công', 'Lịch sử nhân sự', 'Chứng chỉ sắp hết hạn', 'Hợp đồng sắp hết hạn', 'Tài liệu nhân sự', 'Đồng ý dữ liệu', 'Hiệu suất', 'Đào tạo', 'Báo cáo', 'Cấu hình'] as $pendingMenu)
            <span class="nav-disabled">{{ $pendingMenu }} <i>Đang phát triển</i></span>
        @endforeach
    </nav>
    <div class="user">{{ auth()->user()?->name ?? 'SYSTEM ADMIN' }}<br><small>Enterprise HRM</small></div>
</aside>
<main>
    <header>
        <div><small>SCHEMA ENTERPRISE PLATFORM</small><h1>@yield('title')</h1></div>
        @hasSection('header_action')
            @yield('header_action')
        @else
            @can('article.create')<a class="button" href="{{ route('articles.create') }}">+ Tạo bài viết</a>@endcan
        @endif
    </header>
    @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
    @yield('content')
</main>
</body>
</html>

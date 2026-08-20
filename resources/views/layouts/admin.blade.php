<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="schema-user-id" content="{{ auth()->id() }}">@endauth
    <title>@yield('title', 'Control Center') · LA THÀNH</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<a class="skip-link" href="#main-content">Bỏ qua điều hướng, đến nội dung chính</a>
<button class="mobile-menu-button" type="button" aria-label="Mở điều hướng chính" aria-controls="admin-sidebar" aria-expanded="false" data-sidebar-open><span aria-hidden="true">☰</span><span>Menu</span></button>
<div class="sidebar-overlay" data-sidebar-overlay hidden></div>
<aside id="admin-sidebar" class="admin-sidebar" aria-label="Điều hướng quản trị" data-sidebar aria-hidden="false">
    <div class="sidebar-brand-row">
        <a class="brand" href="{{ route('dashboard') }}">LA THÀNH<span>CONTROL CENTER</span></a>
        <button class="sidebar-close" type="button" aria-label="Đóng điều hướng chính" data-sidebar-close><span aria-hidden="true">×</span></button>
    </div>
    @php
        $navigationCompanyId = (int) request('company_id', session('company_id', 1));
        $navigationUser = auth()->user();
        $navigationGroups = $navigationUser instanceof \App\Models\User
            ? app(\App\Support\Navigation\AdminNavigation::class)->for($navigationUser, request(), $navigationCompanyId)
            : [];
    @endphp
    <div class="nav-search-wrap">
        <label for="admin-navigation-search">Tìm nhanh trong menu</label>
        <input id="admin-navigation-search" type="search" autocomplete="off" placeholder="Ví dụ: payroll, bảo mật…" data-nav-search>
    </div>
    <nav class="primary-navigation" aria-label="Điều hướng chính" data-primary-navigation>
        @foreach($navigationGroups as $group)
            <section class="nav-group {{ $group['active'] ? 'is-active is-open' : '' }}" data-nav-group="{{ $group['key'] }}" data-route-active="{{ $group['active'] ? 'true' : 'false' }}">
                <h2>
                    <button type="button" class="nav-group-toggle" aria-expanded="{{ $group['active'] ? 'true' : 'false' }}" aria-controls="nav-group-{{ $group['key'] }}" data-nav-group-toggle>
                        <span class="nav-group-icon" aria-hidden="true">{{ $group['icon'] }}</span>
                        <span>{{ $group['label'] }}</span>
                        <span class="nav-chevron" aria-hidden="true">⌄</span>
                    </button>
                </h2>
                <div id="nav-group-{{ $group['key'] }}" class="nav-group-items" @if(!$group['active']) hidden @endif>
                    @foreach($group['items'] as $item)
                        <a class="nav-item {{ $item['active'] ? 'active' : '' }}" href="{{ route($item['route'], $item['parameters']) }}" @if($item['active']) aria-current="page" @endif data-nav-label="{{ Illuminate\Support\Str::lower($group['label'].' '.$item['label']) }}">
                            <span>{{ $item['label'] }}</span>
                            @if($item['key'] === 'notifications')<i data-notification-unread aria-label="Thông báo chưa đọc">—</i>@endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
        <p class="nav-search-empty" data-nav-search-empty hidden>Không tìm thấy mục điều hướng phù hợp.</p>
    </nav>
    <div class="user">{{ auth()->user()?->name ?? 'SYSTEM ADMIN' }}<br><small>Enterprise HRM</small></div>
</aside>
<main id="main-content" tabindex="-1">
    <header class="admin-page-header">
        <div><small>SCHEMA ENTERPRISE PLATFORM</small><h1>@yield('title')</h1></div>
        <div class="header-actions">
            @can('payroll.assignment.view')<button type="button" class="header-icon-button" aria-label="Compensation Reference" title="Compensation Reference" data-compensation-reference-open data-url="{{ route('admin.compensation-reference',['company_id'=>$navigationCompanyId]) }}">$</button>@endcan
            <button type="button" class="header-icon-button" aria-label="Hướng dẫn sử dụng trang này" title="Hướng dẫn sử dụng trang này" data-admin-help-open>?</button>
            @hasSection('header_action') @yield('header_action') @else @can('article.create')<a class="button" href="{{ route('articles.create') }}">+ Tạo bài viết</a>@endcan @endif
        </div>
    </header>
    @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="validation-summary" role="alert" aria-live="assertive"><strong>Vui lòng kiểm tra các lỗi sau:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@can('payroll.assignment.view')<dialog class="admin-dialog" data-compensation-reference-dialog aria-labelledby="compensation-reference-title"><div class="dialog-header"><h2 id="compensation-reference-title">Compensation Reference</h2><button type="button" aria-label="Đóng Compensation Reference" data-dialog-close>×</button></div><div data-compensation-reference-body><p>Đang tải…</p></div></dialog>@endcan
@php($adminHelp=app(\App\Support\Navigation\AdminHelpRegistry::class)->resolve(Route::currentRouteName(), $navigationCompanyId))
<dialog class="admin-dialog admin-help-dialog" data-admin-help-dialog aria-labelledby="admin-help-title">
    <div class="dialog-header"><h2 id="admin-help-title">{{ $adminHelp['title'] }}</h2><button type="button" aria-label="Đóng hướng dẫn" data-dialog-close>×</button></div>
    <section><h3>Tổng quan</h3><p>{{ $adminHelp['purpose'] }}</p></section>
    <section><h3>Dữ liệu lấy từ đâu</h3><ul>@foreach($adminHelp['dataSources'] as $source)<li>{{ $source }}</li>@endforeach</ul></section>
    @php($visibleUpstream=array_values(array_filter($adminHelp['upstreamMenus'], fn(array $menu): bool => auth()->user()?->can($menu['permission']) === true)))
    @if($visibleUpstream !== [])<section><h3>Dữ liệu đầu vào từ</h3><ul class="admin-help-related">@foreach($visibleUpstream as $menu)<li><a href="{{ route($menu['route'], $menu['parameters']) }}">{{ $menu['label'] }}</a></li>@endforeach</ul></section>@endif
    <section><h3>Các chức năng</h3><ul>@foreach($adminHelp['mainActions'] as $action)<li>{{ $action }}</li>@endforeach</ul></section>
    <section><h3>Cách thao tác</h3><ol>@foreach($adminHelp['workflowSteps'] as $step)<li>{{ $step }}</li>@endforeach</ol></section>
    <section><h3>Quy tắc quan trọng</h3><ul>@foreach($adminHelp['businessRules'] as $rule)<li>{{ $rule }}</li>@endforeach</ul></section>
    @if($adminHelp['statusGuide'] !== [])<section><h3>Trạng thái</h3><div class="table-wrap"><table><thead><tr><th>Trạng thái</th><th>Ý nghĩa</th><th>Tiếp theo</th></tr></thead><tbody>@foreach($adminHelp['statusGuide'] as $status)<tr><td><code>{{ $status['status'] }}</code></td><td>{{ $status['meaning'] }}</td><td>{{ $status['next'] }}</td></tr>@endforeach</tbody></table></div></section>@endif
    @if($adminHelp['immutabilityRules'] !== [])<section><h3><span class="status-badge">IMMUTABLE</span> Không được sửa trực tiếp</h3><ul>@foreach($adminHelp['immutabilityRules'] as $rule)<li>{{ $rule }}</li>@endforeach</ul></section>@endif
    @if($adminHelp['approvalRules'] !== [])<section><h3>Quy tắc duyệt</h3><ul>@foreach($adminHelp['approvalRules'] as $rule)<li>{{ $rule }}</li>@endforeach</ul></section>@endif
    <section><h3>Khi thao tác, dữ liệu thay đổi ở đâu</h3><ul>@foreach($adminHelp['sideEffects'] as $effect)<li>{{ $effect }}</li>@endforeach</ul></section>
    @php($visibleDownstream=array_values(array_filter($adminHelp['downstreamMenus'], fn(array $menu): bool => auth()->user()?->can($menu['permission']) === true)))
    @if($visibleDownstream !== [])<section><h3>Dữ liệu sau đó đi tới</h3><ul class="admin-help-related">@foreach($visibleDownstream as $menu)<li><a href="{{ route($menu['route'], $menu['parameters']) }}">{{ $menu['label'] }}</a></li>@endforeach</ul></section>@endif
    @php($visibleMenuFlow=array_values(array_filter($adminHelp['menuFlow'], fn(array $node): bool => $node['current'] || auth()->user()?->can($node['permission']) === true)))
    <section><h3>Luồng liên kết</h3><div class="admin-menu-flow" aria-label="Luồng nghiệp vụ giữa các menu">@forelse($visibleMenuFlow as $node)<span class="admin-menu-flow-step">@if(!$loop->first)<span class="admin-menu-flow-arrow" aria-hidden="true">↓</span>@endif @if($node['current'])<strong aria-current="page"><span class="sr-only">Menu hiện tại: </span>{{ $node['label'] }}</strong>@else<a href="{{ route($node['route'], $node['parameters']) }}">{{ $node['label'] }}</a>@endif</span>@empty<span>Trang này là điểm cuối chỉ đọc; không tạo downstream business data.</span>@endforelse</div></section>
    @php($visibleRelated=array_values(array_filter($adminHelp['relatedMenus'], fn(array $menu): bool => auth()->user()?->can($menu['permission']) === true)))
    @if($visibleRelated !== [])<section><h3>Liên quan</h3><ul class="admin-help-related">@foreach($visibleRelated as $menu)<li><a href="{{ route($menu['route'], $menu['parameters']) }}">{{ $menu['label'] }}</a></li>@endforeach</ul></section>@endif
    <section><h3>Quyền cần có</h3><ul>@foreach($adminHelp['permissions'] as $permission)<li><code>{{ $permission }}</code></li>@endforeach</ul></section>
    <section><h3>Lưu ý</h3><ul>@foreach($adminHelp['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></section>
    <section><h3>Luồng hệ thống</h3>@foreach($adminHelp['technicalFlow'] as $flow)<p class="technical-flow">{{ $flow }}</p>@endforeach</section>
</dialog>
</body>
</html>

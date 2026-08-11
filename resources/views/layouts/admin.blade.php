<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="schema-user-id" content="{{ auth()->id() }}">@endauth
    <title>@yield('title', 'Control Center') · SCHEMA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<a class="skip-link" href="#main-content">Bỏ qua điều hướng, đến nội dung chính</a>
<button class="mobile-menu-button" type="button" aria-label="Mở điều hướng chính" aria-controls="admin-sidebar" aria-expanded="false" data-sidebar-open><span aria-hidden="true">☰</span><span>Menu</span></button>
<div class="sidebar-overlay" data-sidebar-overlay hidden></div>
<aside id="admin-sidebar" class="admin-sidebar" aria-label="Điều hướng quản trị" data-sidebar aria-hidden="false">
    <div class="sidebar-brand-row">
        <a class="brand" href="{{ route('dashboard') }}">SCHEMA<span>CONTROL CENTER</span></a>
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
        @hasSection('header_action')
            @yield('header_action')
        @else
            @can('article.create')<a class="button" href="{{ route('articles.create') }}">+ Tạo bài viết</a>@endcan
        @endif
    </header>
    @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="validation-summary" role="alert" aria-live="assertive"><strong>Vui lòng kiểm tra các lỗi sau:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body>
</html>

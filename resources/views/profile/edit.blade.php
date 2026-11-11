@extends('layouts.admin')

@section('title', 'Tài khoản của tôi')

@section('content')
<div class="account-command-center">
    <section class="account-profile-summary" id="profile-information">
        @php($initials=collect(preg_split('/\s+/u',trim((string)$user->name))?:[])->filter()->take(2)->map(fn(string $part):string=>mb_strtoupper(mb_substr($part,0,1)))->join(''))
        <span class="erp-account-avatar erp-account-avatar--large" aria-hidden="true">{{ $initials ?: '?' }}</span>
        <div><small>AUTHENTICATED PROFILE</small><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><span class="account-status">{{ $user->access_enabled === false ? 'Tài khoản tạm khóa' : 'Tài khoản đang hoạt động' }}</span></div>
    </section>
    <div class="account-settings-grid" id="account-settings">
        <article class="account-settings-card">@include('profile.partials.update-profile-information-form')</article>
        <article class="account-settings-card">@include('profile.partials.update-password-form')</article>
        <article class="account-settings-card account-settings-card--danger">@include('profile.partials.delete-user-form')</article>
    </div>
</div>
@endsection

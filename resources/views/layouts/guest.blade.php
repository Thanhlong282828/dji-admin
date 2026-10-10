@props(['variant' => 'default'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-body font-sans antialiased">
        @if($variant === 'login')
            <main class="auth-shell auth-shell--login">
                <section class="auth-brand" aria-labelledby="auth-brand-title">
                    <a class="auth-dragon" href="/" aria-label="LA THÀNH — Về trang chủ">
                        <span class="sr-only" id="auth-brand-title">LA THÀNH</span>
                        <img src="{{ asset('images/la-thanh-dragon.png') }}" alt="" width="246" height="256">
                        <span class="auth-dragon__shine" aria-hidden="true" style="--auth-dragon-image:url('{{ asset('images/la-thanh-dragon.png') }}')"></span>
                    </a>
                </section>
                <section class="auth-card" aria-labelledby="login-title">{{ $slot }}</section>
            </main>
        @else
            <main class="auth-shell auth-shell--simple"><a class="auth-brand__logo" href="/" aria-label="Về trang chủ"><x-application-logo class="auth-brand__mark" /></a><section class="auth-card">{{ $slot }}</section></main>
        @endif
    </body>
</html>

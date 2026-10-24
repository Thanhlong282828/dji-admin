<x-guest-layout variant="login">
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="auth-card__heading"><span class="auth-eyebrow">SECURE ACCESS</span><h2 id="login-title">Đăng nhập</h2><p>Sử dụng tài khoản doanh nghiệp được cấp quyền.</p></div>
    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <!-- Email Address -->
        <div class="auth-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="auth-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="name@company.com" :aria-invalid="$errors->has('email') ? 'true' : 'false'" aria-describedby="email-error" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="auth-field">
            <x-input-label for="password" :value="__('Password')" />

            <div class="auth-password"><x-text-input id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" :aria-invalid="$errors->has('password') ? 'true' : 'false'" aria-describedby="password-error" /><button type="button" class="auth-password__toggle" data-password-toggle="password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button></div>

            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="auth-form__options">
            <label for="remember_me" class="auth-check">
                <input id="remember_me" type="checkbox" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>
        <x-primary-button class="auth-submit">{{ __('Log in') }} <span aria-hidden="true">→</span></x-primary-button>
    </form>

    <div class="auth-alternatives">
        <span class="auth-divider">Hoặc tiếp tục với</span>
        @foreach (['google' => 'Google', 'facebook' => 'Facebook'] as $provider => $label)
            @if (config("services.$provider.enabled"))
                <a class="auth-secondary" href="{{ route('social.redirect', $provider) }}">Đăng nhập với {{ $label }}</a>
            @endif
        @endforeach
        @if(Route::has('phone.login'))<a class="auth-secondary" href="{{ route('phone.login') }}">Đăng nhập bằng số điện thoại</a>@endif
    </div>
</x-guest-layout>

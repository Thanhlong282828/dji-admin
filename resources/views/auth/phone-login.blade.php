<x-guest-layout>
    <h1 class="text-xl font-semibold">Đăng nhập bằng số điện thoại</h1>
    <p class="mt-2 text-sm text-gray-600">Chỉ số điện thoại đã xác minh mới có thể đăng nhập.</p>
    <x-auth-session-status class="mt-4" :status="session('status')" />
    <form method="POST" action="{{ route('phone.login.request') }}" class="mt-5 space-y-3">@csrf
        <x-input-label for="phone" value="Số điện thoại" /><x-text-input id="phone" name="phone" class="w-full" required autofocus />
        <x-input-error :messages="$errors->get('phone')" /><x-primary-button>Gửi OTP</x-primary-button>
    </form>
    @if (session('phone_demo_code'))<p class="mt-3 text-xs text-amber-700">Mã demo cục bộ: {{ session('phone_demo_code') }}</p>@endif
    <form method="POST" action="{{ route('phone.login.verify') }}" class="mt-6 space-y-3">@csrf
        <x-input-label for="code" value="Mã OTP" /><x-text-input id="code" name="code" inputmode="numeric" maxlength="6" class="w-full" required />
        <x-input-error :messages="$errors->get('code')" /><x-primary-button>Đăng nhập</x-primary-button>
    </form>
    <a href="{{ route('login') }}" class="inline-flex mt-5 text-sm underline">Quay lại đăng nhập email</a>
</x-guest-layout>

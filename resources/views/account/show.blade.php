<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Tài khoản & bảo mật</h2></x-slot>
    <div class="py-10"><div class="max-w-6xl mx-auto px-4 space-y-6">
        <x-auth-session-status class="mb-4" :status="session('status')" />
        <section class="bg-white shadow rounded-xl p-6">
            <h3 class="text-lg font-semibold">Hồ sơ đăng nhập</h3>
            <div class="mt-4 flex items-center gap-3">
                @if ($employee && is_string($employee->avatar_url) && $employee->avatar_url !== '')
                    <img src="{{ $employee->avatar_url }}" alt="Avatar của {{ $user->name }}" class="h-14 w-14 rounded-full object-cover">
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 font-semibold text-white" aria-label="Avatar mặc định của {{ $user->name }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                @endif
                <p class="text-sm text-gray-500">Avatar hồ sơ dùng nguồn Employee hiện có; không sao chép ảnh từ provider vào User.</p>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-gray-500">Họ tên</dt><dd>{{ $user->name }}</dd></div>
                <div><dt class="text-sm text-gray-500">Email</dt><dd>{{ $user->email }} · {{ $user->email_verified_at ? 'Đã xác minh' : 'Chưa xác minh' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Điện thoại</dt><dd>{{ $user->phone_e164 ?: 'Chưa thiết lập' }} · {{ $user->phone_verified_at ? 'Đã xác minh' : 'Chưa xác minh' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Trạng thái truy cập</dt><dd>{{ $user->access_enabled ? 'Đang hoạt động' : 'Đã vô hiệu hóa' }}</dd></div>
            </dl>
            <a href="{{ route('profile.edit') }}" class="inline-flex mt-5 text-sm underline">Sửa hồ sơ hoặc mật khẩu</a>
        </section>

        <section class="bg-white shadow rounded-xl p-6">
            <h3 class="text-lg font-semibold">Phương thức đăng nhập ngoài</h3>
            <p class="mt-1 text-sm text-gray-500">Google/Facebook chỉ được dùng sau khi bạn chủ động liên kết. Hệ thống không tự gộp tài khoản theo email.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach (['google' => 'Google', 'facebook' => 'Facebook'] as $provider => $label)
                    @php($identity = $user->externalIdentities->firstWhere('provider', $provider))
                    <div class="rounded-lg border p-4">
                        <strong>{{ $label }}</strong>
                        @if ($identity)
                            <p class="text-sm text-gray-500">Đã liên kết {{ optional($identity->linked_at)->format('d/m/Y H:i') }}</p>
                            <form method="POST" action="{{ route('social.unlink', $provider) }}" class="mt-3">@csrf @method('DELETE')<button class="text-sm text-red-700 underline">Ngắt liên kết</button></form>
                        @elseif (config("services.$provider.enabled"))
                            <a class="inline-flex mt-3 text-sm underline" href="{{ route('social.redirect', $provider) }}">Liên kết {{ $label }}</a>
                        @else
                            <p class="text-sm text-gray-500">Chưa được cấu hình trong môi trường này.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('provider')" class="mt-3" />
        </section>

        <section class="bg-white shadow rounded-xl p-6">
            <h3 class="text-lg font-semibold">Xác minh số điện thoại</h3>
            <form method="POST" action="{{ route('account.phone.request') }}" class="mt-4 flex flex-wrap gap-3">@csrf
                <x-text-input name="phone" value="{{ old('phone', $user->phone_e164) }}" placeholder="0901234567" required />
                <x-primary-button>Gửi OTP</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            @if (session('phone_challenge_id'))
                <form method="POST" action="{{ route('account.phone.verify') }}" class="mt-4 flex flex-wrap gap-3">@csrf
                    <input type="hidden" name="challenge_id" value="{{ session('phone_challenge_id') }}">
                    <x-text-input name="code" inputmode="numeric" maxlength="6" placeholder="Mã OTP" required />
                    <x-primary-button>Xác minh</x-primary-button>
                </form>
                @if (session('phone_demo_code'))<p class="mt-2 text-xs text-amber-700">Mã demo cục bộ: {{ session('phone_demo_code') }}</p>@endif
            @endif
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </section>

        <section class="bg-white shadow rounded-xl p-6">
            <h3 class="text-lg font-semibold">Vai trò, phạm vi và OAuth</h3>
            <p class="mt-2 text-sm">Vai trò: {{ $user->roles->pluck('name')->join(', ') ?: 'Chưa có' }}</p>
            <p class="mt-2 text-sm">Hồ sơ nghiệp vụ: Employee {{ $employee ? 'Có' : 'Không' }} · Buyer {{ $buyer ? 'Có' : 'Không' }} · Seller {{ $seller ? 'Có' : 'Không' }}</p>
            <p class="mt-2 text-sm">Công ty: {{ $companies->pluck('name')->join(', ') ?: 'Chưa có' }}</p>
            <div class="mt-2 text-sm"><strong>Chi nhánh</strong>@forelse($branches as $branch)<p>{{ $branch->name }} — {{ collect([$branch->address_line,$branch->city])->filter()->join(', ') ?: 'Chưa có địa chỉ' }}<br><small>Lãnh đạo: {{ $branch->leader_name ? $branch->leader_name.' — '.$branch->leader_position : 'Chưa gán' }} @if((int)$branch->leader_user_id===(int)$user->id) · <strong>Bạn là lãnh đạo chi nhánh này</strong>@endif</small></p>@empty<p>Chưa có</p>@endforelse</div>
            <p class="mt-2 text-sm">OAuth clients: {{ $oauth['clients'] }} · Access tokens còn hiệu lực: {{ $oauth['tokens'] }}</p>
            <p class="mt-2 text-xs text-gray-500">OAuth scope chỉ giới hạn phạm vi token; quyền IAM và phạm vi công ty vẫn được kiểm tra riêng.</p>
        </section>
    </div></div>
</x-app-layout>

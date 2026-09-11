<?php

namespace Modules\Iam\Presentation\Http\Controllers;

use App\Models\User;
use App\Support\Identity\PhoneVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Iam\Application\Services\PhoneLoginService;

final readonly class PhoneVerificationController
{
    public function __construct(private PhoneVerificationService $phones, private PhoneLoginService $login) {}

    public function requestForAccount(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        [$challenge, $code] = $this->phones->issue($user, $request->string('phone')->toString(), 'verify', $request->ip() ?? 'unknown');

        return to_route('account.show')->with(['status' => 'OTP đã được tạo.', 'phone_challenge_id' => $challenge->id, 'phone_demo_code' => app()->environment(['local', 'testing']) ? $code : null]);
    }

    public function verifyAccount(Request $request): RedirectResponse
    {
        $request->validate(['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'digits:6']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->phones->verify($user, $request->string('challenge_id')->toString(), $request->string('code')->toString());

        return to_route('account.show')->with('status', 'Số điện thoại đã được xác minh.');
    }

    public function loginForm(): View
    {
        return view('auth.phone-login');
    }

    public function requestLogin(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $issued = $this->login->issue($request->string('phone')->toString(), $request->ip() ?? 'unknown');
        if ($issued !== null) {
            $request->session()->put('phone_login_challenge', $issued['challenge_id']);
            if (app()->environment(['local', 'testing'])) {
                $request->session()->flash('phone_demo_code', $issued['code']);
            }
        }

        return to_route('phone.login')->with('status', 'Nếu số điện thoại hợp lệ, OTP đã được gửi.');
    }

    public function verifyLogin(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $challengeId = $request->session()->pull('phone_login_challenge');
        $user = $this->login->verify($challengeId, $request->string('code')->toString());
        if (! $user instanceof User) {
            return back()->withErrors(['code' => 'OTP không hợp lệ hoặc đã hết hạn.']);
        }
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PhoneVerificationChallenge;
use App\Models\User;
use App\Support\Identity\PhoneVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final readonly class PhoneVerificationController
{
    public function __construct(private PhoneVerificationService $phones) {}

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
        $normalized = $this->phones->normalize($request->string('phone')->toString());
        $user = User::query()->where(['phone_e164' => $normalized, 'access_enabled' => true])->whereNotNull('phone_verified_at')->first();
        if ($user instanceof User) {
            [$challenge, $code] = $this->phones->issue($user, $normalized, 'login', $request->ip() ?? 'unknown');
            $request->session()->put('phone_login_challenge', $challenge->id);
            if (app()->environment(['local', 'testing'])) {
                $request->session()->flash('phone_demo_code', $code);
            }
        }

        return to_route('phone.login')->with('status', 'Nếu số điện thoại hợp lệ, OTP đã được gửi.');
    }

    public function verifyLogin(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $challengeId = $request->session()->pull('phone_login_challenge');
        $challenge = is_string($challengeId) ? PhoneVerificationChallenge::query()->whereKey($challengeId)->first() : null;
        if (! $challenge instanceof PhoneVerificationChallenge || ! $challenge->user instanceof User) {
            return back()->withErrors(['code' => 'OTP không hợp lệ hoặc đã hết hạn.']);
        }
        $this->phones->verify($challenge->user, $challenge->id, $request->string('code')->toString());
        Auth::login($challenge->user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

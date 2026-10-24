<?php

namespace App\Support\Identity;

use App\Models\PhoneVerificationChallenge;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class PhoneVerificationService
{
    public function __construct(private PhoneVerificationProviderInterface $provider, private DatabaseManager $db) {}

    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));
        if (! is_string($digits)) {
            $digits = '';
        }
        if (str_starts_with($digits, '84')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (! preg_match('/^[1-9][0-9]{8,10}$/', $digits)) {
            throw ValidationException::withMessages(['phone' => 'Số điện thoại Việt Nam không hợp lệ.']);
        }

        return '+84'.$digits;
    }

    /** @return array{PhoneVerificationChallenge,string} */
    public function issue(User $user, string $phone, string $purpose, string $ip): array
    {
        $phone = $this->normalize($phone);
        $key = 'phone-otp:'.$user->id.':'.hash('sha256', $phone);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['phone' => 'Vui lòng chờ trước khi yêu cầu OTP mới.']);
        }
        RateLimiter::hit($key, 60);
        PhoneVerificationChallenge::query()->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = PhoneVerificationChallenge::query()->create(['user_id' => $user->id, 'phone_e164' => $phone, 'purpose' => $purpose, 'code_hash' => Hash::make($code), 'attempts' => 0, 'max_attempts' => 5, 'expires_at' => now()->addMinutes(5), 'request_ip_hash' => hash('sha256', $ip)]);
        $this->provider->send($phone, $code);
        activity('identity')->causedBy($user)->withProperties(['purpose' => $purpose])->log('phone.challenge.issued');

        return [$challenge, $code];
    }

    public function verify(User $user, string $challengeId, string $code): void
    {
        $result = $this->db->transaction(function () use ($user, $challengeId, $code): string {
            $challenge = PhoneVerificationChallenge::query()->where(['id' => $challengeId, 'user_id' => $user->id])->lockForUpdate()->firstOrFail();
            if ($challenge->consumed_at !== null || $challenge->expires_at->isPast() || $challenge->attempts >= $challenge->max_attempts) {
                return 'expired';
            }
            $challenge->increment('attempts');
            if (! Hash::check($code, $challenge->code_hash)) {
                return 'invalid';
            }
            $duplicate = User::query()->where('phone_e164', $challenge->phone_e164)->whereKeyNot($user->id)->exists();
            if ($duplicate) {
                return 'duplicate';
            }
            $challenge->update(['consumed_at' => now()]);
            $user->forceFill(['phone_e164' => $challenge->phone_e164, 'phone_verified_at' => now()])->save();

            return 'verified';
        }, 3);

        if ($result === 'expired') {
            throw ValidationException::withMessages(['code' => 'OTP đã hết hiệu lực.']);
        }
        if ($result === 'invalid') {
            throw ValidationException::withMessages(['code' => 'OTP không hợp lệ.']);
        }
        if ($result === 'duplicate') {
            throw ValidationException::withMessages(['phone' => 'Số điện thoại đã thuộc tài khoản khác.']);
        }
        activity('identity')->causedBy($user)->log('phone.verified');
    }
}

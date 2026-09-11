<?php

namespace Modules\Iam\Application\Services;

use App\Models\PhoneVerificationChallenge;
use App\Models\User;
use App\Support\Identity\PhoneVerificationService;

final readonly class PhoneLoginService
{
    public function __construct(private PhoneVerificationService $phones) {}

    /** @return array{challenge_id:string,code:string}|null */
    public function issue(string $phone, string $ip): ?array
    {
        $normalized = $this->phones->normalize($phone);
        $user = User::query()->where(['phone_e164' => $normalized, 'access_enabled' => true])->whereNotNull('phone_verified_at')->first();
        if (! $user instanceof User) {
            return null;
        }
        [$challenge, $code] = $this->phones->issue($user, $normalized, 'login', $ip);

        return ['challenge_id' => $challenge->id, 'code' => $code];
    }

    public function verify(mixed $challengeId, string $code): ?User
    {
        $challenge = is_string($challengeId) ? PhoneVerificationChallenge::query()->whereKey($challengeId)->first() : null;
        if (! $challenge instanceof PhoneVerificationChallenge || ! $challenge->user instanceof User) {
            return null;
        }
        $this->phones->verify($challenge->user, $challenge->id, $code);

        return $challenge->user;
    }
}

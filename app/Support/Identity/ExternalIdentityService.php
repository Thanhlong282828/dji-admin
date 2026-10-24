<?php

namespace App\Support\Identity;

use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class ExternalIdentityService
{
    public function __construct(private DatabaseManager $db) {}

    public function linkedUser(string $provider, SocialiteUser $external): User
    {
        $providerId = $this->providerId($external);
        $identity = UserExternalIdentity::query()->with('user')->where(['provider' => $this->provider($provider), 'provider_user_id' => $providerId])->first();
        $user = $identity?->user;
        if (! $user instanceof User || ! $user->access_enabled) {
            throw ValidationException::withMessages(['provider' => 'Tài khoản ngoài chưa được liên kết hoặc tài khoản đã bị vô hiệu hóa.']);
        }
        $identity->update(['last_used_at' => now()]);
        activity('identity')->causedBy($user)->withProperties(['provider' => $provider])->log('external_identity.login');

        return $user;
    }

    public function link(User $user, string $provider, SocialiteUser $external): UserExternalIdentity
    {
        $provider = $this->provider($provider);
        $providerId = $this->providerId($external);

        return $this->db->transaction(function () use ($user, $provider, $providerId, $external): UserExternalIdentity {
            $collision = UserExternalIdentity::query()->where(['provider' => $provider, 'provider_user_id' => $providerId])->lockForUpdate()->first();
            if ($collision !== null && $collision->user_id !== $user->id) {
                throw ValidationException::withMessages(['provider' => 'Tài khoản ngoài này đã được liên kết với User khác.']);
            }
            $existing = UserExternalIdentity::query()->where(['user_id' => $user->id, 'provider' => $provider])->lockForUpdate()->first();
            if ($existing !== null && $existing->provider_user_id !== $providerId) {
                throw ValidationException::withMessages(['provider' => 'Hãy ngắt liên kết hiện tại trước khi kết nối tài khoản khác.']);
            }
            $identity = UserExternalIdentity::query()->updateOrCreate(
                ['user_id' => $user->id, 'provider' => $provider],
                ['provider_user_id' => $providerId, 'provider_email' => $external->getEmail(), 'provider_email_verified' => $provider === 'google' ? true : null, 'display_name' => $external->getName() ?: $external->getNickname(), 'avatar_url' => $external->getAvatar(), 'linked_at' => $existing !== null ? $existing->linked_at : now(), 'last_used_at' => now()],
            );
            activity('identity')->causedBy($user)->withProperties(['provider' => $provider])->log('external_identity.linked');

            return $identity;
        }, 3);
    }

    public function unlink(User $user, string $provider): void
    {
        $provider = $this->provider($provider);
        $identity = UserExternalIdentity::query()->where(['user_id' => $user->id, 'provider' => $provider])->firstOrFail();
        $otherMethods = UserExternalIdentity::query()->where('user_id', $user->id)->whereKeyNot($identity->id)->exists()
            || $user->phone_verified_at !== null || $user->password !== '';
        if (! $otherMethods) {
            throw ValidationException::withMessages(['provider' => 'Không thể ngắt phương thức đăng nhập cuối cùng.']);
        }
        $identity->delete();
        activity('identity')->causedBy($user)->withProperties(['provider' => $provider])->log('external_identity.unlinked');
    }

    private function provider(string $provider): string
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            abort(404);
        }

        return $provider;
    }

    private function providerId(SocialiteUser $external): string
    {
        $identifier = $external->getId();
        if ($identifier === '') {
            throw ValidationException::withMessages(['provider' => 'Provider không trả về identity hợp lệ.']);
        }

        return $identifier;
    }
}

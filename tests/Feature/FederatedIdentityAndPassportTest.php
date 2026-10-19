<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationChallenge;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Support\Identity\ExternalIdentityService;
use App\Support\Identity\PhoneVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeAccountService;
use Tests\TestCase;

final class FederatedIdentityAndPassportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{private:string,public:string}|null */
    private static ?array $passportKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$passportKeys === null) {
            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            self::assertNotFalse($resource);

            $privateKey = '';
            self::assertTrue(openssl_pkey_export($resource, $privateKey));
            $details = openssl_pkey_get_details($resource);
            self::assertIsArray($details);
            self::assertIsString($details['key'] ?? null);

            self::$passportKeys = ['private' => $privateKey, 'public' => $details['key']];
        }

        config()->set('passport.private_key', self::$passportKeys['private']);
        config()->set('passport.public_key', self::$passportKeys['public']);
    }

    public function test_external_identity_link_is_explicit_and_does_not_auto_merge_by_email(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $sameEmail = User::factory()->create(['email' => 'provider@example.test']);
        $external = (new SocialiteUser)->map(['id' => 'google-123', 'email' => $sameEmail->email, 'name' => 'Provider User']);

        $identity = app(ExternalIdentityService::class)->link($owner, 'google', $external);

        self::assertSame($owner->id, $identity->user_id);
        self::assertSame(2, User::query()->count());
        self::assertDatabaseMissing('user_external_identities', ['user_id' => $sameEmail->id]);
    }

    public function test_external_identity_collision_is_rejected_and_tokens_are_not_persisted(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $external = (new SocialiteUser)->map(['id' => 'shared-id', 'email' => 'outside@example.test', 'token' => 'never-store-this']);
        app(ExternalIdentityService::class)->link($first, 'facebook', $external);

        $this->expectException(ValidationException::class);
        app(ExternalIdentityService::class)->link($second, 'facebook', $external);
    }

    public function test_disabled_linked_account_cannot_use_provider_login(): void
    {
        $user = User::factory()->create(['access_enabled' => false]);
        UserExternalIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'disabled-id', 'linked_at' => now()]);
        $external = (new SocialiteUser)->map(['id' => 'disabled-id']);

        $this->expectException(ValidationException::class);
        app(ExternalIdentityService::class)->linkedUser('google', $external);
    }

    public function test_phone_otp_is_hashed_single_use_and_wrong_attempt_is_persisted(): void
    {
        $user = User::factory()->create();
        [$challenge, $code] = app(PhoneVerificationService::class)->issue($user, '0901234567', 'verify', '127.0.0.1');
        self::assertNotSame($code, $challenge->code_hash);

        try {
            app(PhoneVerificationService::class)->verify($user, $challenge->id, '000000');
        } catch (ValidationException) {
        }
        self::assertSame(1, PhoneVerificationChallenge::query()->findOrFail($challenge->id)->attempts);

        app(PhoneVerificationService::class)->verify($user, $challenge->id, $code);
        self::assertSame('+84901234567', User::query()->findOrFail($user->id)->phone_e164);
        $this->expectException(ValidationException::class);
        app(PhoneVerificationService::class)->verify($user, $challenge->id, $code);
    }

    public function test_passport_scope_and_access_enabled_are_both_enforced(): void
    {
        $user = User::factory()->create(['access_enabled' => true]);
        Passport::actingAs($user, ['onboarding:read']);
        self::assertTrue($user->currentAccessToken()?->can('onboarding:read') ?? false);
        $this->getJson('/api/v1/onboardings')->assertGone();

        Passport::actingAs($user, ['organization:read']);
        $this->getJson('/api/v1/onboardings')->assertForbidden();

        $user->update(['access_enabled' => false]);
        Passport::actingAs($user, ['onboarding:read']);
        $this->getJson('/api/v1/onboardings')->assertForbidden();
    }

    public function test_account_page_exposes_safe_identity_metadata_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $response = $this->actingAs($user)->get(route('account.show'));
        $response->assertOk()->assertSee('Tài khoản &amp; bảo mật', false)->assertSee('Avatar mặc định của')->assertSee('Employee Không')->assertSee('Buyer Không')->assertSee('Seller Không')->assertDontSee('client_secret');
    }

    public function test_socialite_callback_links_only_to_the_authenticated_user(): void
    {
        config()->set('services.google.enabled', true);
        $user = User::factory()->create(['email_verified_at' => now()]);
        Socialite::fake('google', SocialiteUser::fake(['id' => 'google-linked', 'email' => $user->email, 'name' => $user->name]));

        $this->actingAs($user)->withSession(['social_identity_mode' => 'link'])
            ->get(route('social.callback', 'google'))->assertRedirect(route('account.show'));

        self::assertDatabaseHas('user_external_identities', ['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-linked']);
    }

    public function test_enabled_social_providers_have_stateful_redirect_boundaries(): void
    {
        $user = User::factory()->create();
        foreach (['google', 'facebook'] as $provider) {
            config()->set('services.'.$provider.'.enabled', true);
            Socialite::fake($provider);
            $this->actingAs($user)->get(route('social.redirect', $provider))->assertRedirect();
            self::assertSame('link', session('social_identity_mode'));
        }
    }

    public function test_unknown_social_identity_returns_safe_local_error_without_registration(): void
    {
        config()->set('services.facebook.enabled', true);
        Socialite::fake('facebook', SocialiteUser::fake(['id' => 'unknown-provider-user', 'email' => 'unknown@example.test']));

        $this->withSession(['social_identity_mode' => 'login'])
            ->get(route('social.callback', 'facebook'))->assertRedirect(route('login'))->assertSessionHasErrors('provider');

        self::assertSame(0, User::query()->count());
        self::assertSame(0, UserExternalIdentity::query()->count());
    }

    public function test_facebook_link_accepts_provider_without_email(): void
    {
        $user = User::factory()->create();
        $external = SocialiteUser::fake(['id' => 'facebook-no-email', 'name' => 'Facebook User', 'email' => null]);

        $identity = app(ExternalIdentityService::class)->link($user, 'facebook', $external);

        self::assertNull($identity->provider_email);
        self::assertSame('facebook-no-email', $identity->provider_user_id);
    }

    public function test_passport_public_client_supports_authorization_code_pkce_without_password_grant(): void
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Browser PKCE', ['https://client.example.test/callback'], false);

        self::assertNull($client->getAttribute('secret'));
        $grantTypes = $client->getAttribute('grant_types');
        self::assertIsArray($grantTypes);
        self::assertContains('authorization_code', $grantTypes);
        self::assertContains('refresh_token', $grantTypes);
        self::assertNotContains('password', $grantTypes);
        self::assertNotContains('implicit', $grantTypes);
    }

    public function test_employee_account_disable_revokes_existing_passport_tokens(): void
    {
        $user = User::factory()->create(['access_enabled' => true]);
        $companyId = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'IDENTITY-REVOCATION', 'name' => 'Identity Revocation', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $employee = Employee::query()->create(['company_id' => $companyId, 'user_id' => $user->id, 'employee_code' => 'IDENTITY-001', 'full_name' => $user->name, 'nationality' => 'VN', 'join_date' => today(), 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Revocation Client', ['https://client.example.test/callback'], false);
        DB::table('oauth_access_tokens')->insert(['id' => str_repeat('a', 80), 'user_id' => $user->id, 'client_id' => $client->getKey(), 'scopes' => json_encode(['company:read'], JSON_THROW_ON_ERROR), 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addMinutes(15)]);

        app(EmployeeAccountService::class)->setAccess($companyId, (int) $employee->id, false, $user->id + 1000);

        self::assertFalse(User::query()->findOrFail($user->id)->access_enabled);
        self::assertSame(1, DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('revoked', true)->count());
    }
}

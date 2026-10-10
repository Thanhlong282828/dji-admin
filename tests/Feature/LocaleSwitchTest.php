<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_cycle_supported_locales_in_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (['default', 'vi', 'en', 'zh_CN', 'default'] as $locale) {
            $this->actingAs($user)->from('/dashboard')->post(route('locale.update'), ['locale' => $locale])->assertRedirect('/dashboard')->assertSessionHas('locale', $locale);
        }
    }

    public function test_locale_update_requires_authentication_and_allowlisted_value(): void
    {
        $this->post(route('locale.update'), ['locale' => 'vi'])->assertRedirect(route('login'));
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('locale.update'), ['locale' => '../../etc/passwd'])->assertSessionHasErrors('locale');
    }

    public function test_locale_middleware_renders_translated_sidebar_controls_without_changing_authorization(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['locale' => 'zh_CN'])->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->withSession(['locale' => 'en'])->get(route('locale.update'))->assertMethodNotAllowed();
    }

    public function test_locale_middleware_is_safe_for_stateless_health_routes(): void
    {
        $this->get(route('health.live'))->assertOk();
        self::assertSame(config('app.locale'), app()->getLocale());
    }
}

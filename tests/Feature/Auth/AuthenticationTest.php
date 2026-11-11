<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('images/la-thanh-dragon.png')
            ->assertSee('rel="icon" type="image/png"', false)
            ->assertSee('auth-card__mobile-brand', false)
            ->assertDontSee('ENTERPRISE CONTROL CENTER')
            ->assertDontSee('application-logo')
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('Đăng nhập bằng số điện thoại');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['logout_regression_marker' => 'must-be-invalidated'])
            ->post(route('logout'));

        $this->assertGuest();
        $response->assertSessionMissing('logout_regression_marker');
        $response->assertRedirect('/');
    }

    public function test_logout_route_does_not_accept_get_requests(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/logout')
            ->assertMethodNotAllowed();

        $this->assertAuthenticated();
    }
}

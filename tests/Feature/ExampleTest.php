<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_public_home_is_visible_and_admin_dashboard_requires_login(): void
    {
        $this->get('/')->assertOk()->assertSee('LA THÀNH');
        $this->get('/dashboard')->assertRedirectToRoute('login');
    }
}

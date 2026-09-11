<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ProductionSecurityHeadersTest extends TestCase
{
    public function test_runtime_responses_apply_security_headers_without_local_hsts(): void
    {
        $this->get(route('health.live'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}

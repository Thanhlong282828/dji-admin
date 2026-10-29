<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
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

    public function test_trusted_reverse_proxy_metadata_drives_https_and_client_identity(): void
    {
        Route::get('/__test/reverse-proxy-context', static fn () => response()->json([
            'secure' => request()->isSecure(),
            'host' => request()->getHost(),
            'port' => request()->getPort(),
            'client_ip' => request()->ip(),
        ]));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.25',
                'X-Forwarded-Host' => 'erp.example.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/__test/reverse-proxy-context')
            ->assertOk()
            ->assertExactJson([
                'secure' => true,
                'host' => 'erp.example.test',
                'port' => 443,
                'client_ip' => '203.0.113.25',
            ]);
    }

    public function test_untrusted_client_cannot_spoof_forwarded_scheme_or_identity(): void
    {
        Route::get('/__test/untrusted-proxy-context', static fn () => response()->json([
            'secure' => request()->isSecure(),
            'client_ip' => request()->ip(),
        ]));

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.25',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/__test/untrusted-proxy-context')
            ->assertOk()
            ->assertExactJson([
                'secure' => false,
                'client_ip' => '198.51.100.10',
            ]);
    }
}

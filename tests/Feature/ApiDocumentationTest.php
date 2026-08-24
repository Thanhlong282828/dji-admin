<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_swagger_ui_and_spec_are_protected_by_the_dedicated_permission(): void
    {
        self::assertContains($this->get('/api/documentation')->getStatusCode(), [302, 401]);
        self::assertContains($this->get('/docs')->getStatusCode(), [302, 401]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/api/documentation')->assertForbidden();
        $this->actingAs($user)->get('/docs')->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('api.documentation.view', 'web'));
        $this->actingAs($user)->get('/api/documentation')->assertOk()->assertSee('swagger-ui', false);
        $this->actingAs($user)->get('/docs')->assertOk()->assertHeader('content-type', 'application/json');
    }

    public function test_admin_documentation_landing_keeps_contextual_admin_layout_and_permission_boundary(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('admin.operations.api-documentation'))->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('api.documentation.view', 'web'));
        $this->actingAs($user)->get(route('admin.operations.api-documentation'))
            ->assertOk()
            ->assertSee('API Documentation')
            ->assertSee('Hướng dẫn sử dụng trang này')
            ->assertSee(route('l5-swagger.default.api'), false)
            ->assertSee(url('/docs'), false);
    }

    public function test_generated_openapi_document_is_valid_and_contains_no_secret_examples(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('api.documentation.view', 'web'));

        $response = $this->actingAs($user)->get('/docs')->assertOk();
        $document = $response->json();

        self::assertIsArray($document);
        self::assertArrayHasKey('openapi', $document);
        self::assertArrayHasKey('info', $document);
        self::assertIsArray($document['paths'] ?? null);
        self::assertCount(11, $document['paths']);

        $encoded = json_encode($document, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('BEGIN PRIVATE KEY', $encoded);
        self::assertStringNotContainsString('LocalAdmin123!', $encoded);
        self::assertStringNotContainsString('DB_PASSWORD=', $encoded);
        self::assertStringNotContainsString('APP_KEY=', $encoded);
    }

    public function test_production_configuration_keeps_documentation_authenticated_and_authorized(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        self::assertContains($this->get('/api/documentation')->getStatusCode(), [302, 401]);
        self::assertContains($this->get('/docs')->getStatusCode(), [302, 401]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/api/documentation')->assertForbidden();
        $this->actingAs($user)->get('/docs')->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('api.documentation.view', 'web'));
        $this->actingAs($user)->get('/api/documentation')->assertOk();
        $this->actingAs($user)->get('/docs')->assertOk();
    }
}

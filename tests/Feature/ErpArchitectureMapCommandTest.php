<?php

namespace Tests\Feature;

use App\Support\Navigation\AdminNavigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ErpArchitectureMapCommandTest extends TestCase
{
    public function test_maps_are_generated_from_existing_navigation_routes_and_manifests(): void
    {
        $this->artisan('app:erp-architecture-map')->assertSuccessful();
        $menu = (string) file_get_contents(base_path('SO_DO_LUONG_MENU_ERP.txt'));
        $modules = (string) file_get_contents(base_path('SO_DO_LIEN_KET_MODULE_ERP.txt'));
        foreach (app(AdminNavigation::class)->definitions() as $group) {
            foreach ($group['items'] as $item) {
                self::assertNotNull(Route::getRoutes()->getByName($item['route']), $item['route']);
                self::assertStringContainsString('Route      : '.$item['route'], $menu);
                self::assertStringContainsString('Permission : '.$item['permission'], $menu);
            }
        }
        self::assertStringContainsString('Sales Order → Inventory Fulfillment → COGS → Customer Invoice → AR → Collection → Treasury', $modules);
        self::assertStringContainsString('[DEPENDS] Commerce → Inventory', $modules);
        self::assertStringContainsString('[DEPENDS] Commerce → Finance', $modules);
    }
}

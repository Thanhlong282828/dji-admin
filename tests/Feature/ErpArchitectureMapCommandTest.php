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
        $navigationMap = (string) file_get_contents(base_path('docs/architecture/ERP-NAVIGATION-MAP.md'));
        $registeredItems = 0;
        foreach (app(AdminNavigation::class)->definitions() as $group) {
            foreach ($group['items'] as $item) {
                $registeredItems++;
                self::assertNotNull(Route::getRoutes()->getByName($item['route']), $item['route']);
                self::assertStringContainsString('Route      : '.$item['route'], $menu);
                self::assertStringContainsString('Permission : '.$item['permission'], $menu);
                self::assertSame(1, substr_count($navigationMap, '| `'.$item['route'].'` | `'.$item['permission'].'` |'), 'Navigation map must contain each canonical route/permission pair exactly once: '.$item['route']);
            }
        }
        self::assertSame(143, $registeredItems);
        self::assertStringContainsString('**47 discovered modules**, **28 enabled modules**, **20 sidebar groups**, **143 user-facing workspaces**', $navigationMap);
        self::assertStringContainsString('| MES | NOT_IMPLEMENTED |', $navigationMap);
        self::assertStringContainsString('Sales Order → Inventory Fulfillment → COGS → Customer Invoice → AR → Collection → Treasury', $modules);
        self::assertStringContainsString('[DEPENDS] Commerce → Inventory', $modules);
        self::assertStringContainsString('[DEPENDS] Commerce → Finance', $modules);
    }
}

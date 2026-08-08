<?php

namespace Tests\Feature\Workflow;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WorkflowBrowserFixtureCommandTest extends TestCase
{
    public function test_guarded_fixture_is_idempotent_and_cleanup_is_scoped(): void
    {
        self::assertSame(0, Artisan::call('workflow:browser-fixture'));
        self::assertSame(0, Artisan::call('workflow:browser-fixture'));
        self::assertSame(1, DB::table('companies')->where('code', 'WF-BROWSER-FIXTURE')->count());
        self::assertSame(1, DB::table('workflow_definitions')->where('code', 'ENTERPRISE-APPROVAL')->count());
        self::assertSame(1, DB::table('workflow_instances')->where('idempotency_key', 'workflow-browser-instance')->count());
        self::assertSame(0, Artisan::call('workflow:browser-fixture', ['--cleanup' => true]));
        self::assertSame(0, DB::table('companies')->where('code', 'WF-BROWSER-FIXTURE')->count());
    }
}

<?php

namespace Tests\Support\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class PayrollAdjustmentDownstreamTestCase extends TestCase
{
    use CreatesAppliedPayrollAdjustment;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['payroll.adjustment.view', 'payroll.adjustment.create', 'payroll.adjustment.update', 'payroll.adjustment.calculate', 'payroll.adjustment.review', 'payroll.adjustment.approve', 'payroll.adjustment.apply', 'payroll.adjustment.payment.create', 'payroll.adjustment.accounting.create', 'payroll.adjustment.statement.generate', 'payroll.adjustment.statement.download', 'payroll.adjustment.downstream.retry'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
    }
}

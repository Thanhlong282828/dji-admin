<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;

final class ErpIntegrationSecurityTest extends TestCase
{
    public function test_guest_cannot_access_erp_exports(): void
    {
        $this->get('/admin/payroll/erp/exports?company_id=1')->assertRedirect('/login');
    }

    public function test_erp_mutations_are_not_exposed_as_get_routes(): void
    {
        foreach (['validate', 'execute', 'retry', 'reverse'] as $action) {
            $this->get('/admin/payroll/erp/exports/1/'.$action.'?company_id=1')->assertStatus(405);
        }
    }
}

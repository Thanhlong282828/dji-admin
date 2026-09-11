<?php

namespace Tests\Feature\Commerce;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Application\Services\CustomerMetricsService;
use Modules\POS\Application\Services\BranchCommerceReadService;
use Tests\TestCase;

final class CustomerMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_metrics_are_grouped_deterministic_and_do_not_mutate_customer(): void
    {
        $actor = User::factory()->create();
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'CRM', 'name' => 'CRM Co', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'HQ', 'name' => 'HQ', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()]);
        $customer = (int) DB::table('commerce_customers')->insertGetId(['company_id' => $company, 'full_name' => 'Khách hàng hợp nhất', 'phone' => '0900000000', 'email' => 'crm@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('branch_user_access')->insert(['branch_id' => $branch, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['2026-08-01', 1_200_000], ['2026-08-20', 800_000]] as $index => [$date, $amount]) {
            $visit = (int) DB::table('customer_visits')->insertGetId(['reference' => 'CRM-VISIT-'.$index, 'company_id' => $company, 'branch_id' => $branch, 'customer_id' => $customer, 'status' => 'checked_out', 'checked_in_at' => $date.' 09:00:00', 'checked_out_at' => $date.' 10:00:00', 'created_by' => $actor->id, 'updated_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('branch_pos_receipts')->insert(['reference' => 'CRM-RECEIPT-'.$index, 'company_id' => $company, 'branch_id' => $branch, 'customer_visit_id' => $visit, 'service_total_minor' => $amount, 'product_total_minor' => 0, 'total_minor' => $amount, 'currency' => 'VND', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => $date.' 10:00:00', 'cashier_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $before = DB::table('commerce_customers')->where('id', $customer)->first();
        $metrics = app(CustomerMetricsService::class)->forCompany($company, CarbonImmutable::parse('2026-09-01'))->sole();

        self::assertSame(2, $metrics['purchase_count']);
        self::assertSame(2_000_000, $metrics['recognized_spend_minor']);
        self::assertSame(1_000_000, $metrics['average_basket_minor']);
        self::assertSame(2, $metrics['visit_count']);
        self::assertSame('REPEAT', $metrics['segment']);
        self::assertEquals($before, DB::table('commerce_customers')->where('id', $customer)->first());

        $page = app(BranchCommerceReadService::class)->page('customers', $actor, $company, ['segment' => 'REPEAT', 'branch_id' => $branch, 'search' => 'hợp nhất']);
        self::assertSame(1, $page['customers']->total());
        self::assertSame('REPEAT', $page['customers']->items()[0]->crm_metric['segment']);
    }
}

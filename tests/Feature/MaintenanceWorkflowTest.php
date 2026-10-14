<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataQuality\DataQualityAuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Application\Services\AccountingPostingPolicyService;
use Modules\Finance\Application\Services\SourceAccountingPostingService;
use Tests\TestCase;

final class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_request_approval_conversion_and_execution_are_idempotent_and_tenant_scoped(): void
    {
        $company = $this->company('CMMS-A');
        $foreign = $this->company('CMMS-B');
        $maker = $this->actor($company, 'maker@cmms.test');
        $checker = $this->actor($company, 'checker@cmms.test');
        $branch = $this->branch($company, 'HCM');
        $asset = $this->asset($company, $branch, $maker, 'ASSET-001');
        $technician = $this->employee($company, 'TECH-001');
        $foreignTechnician = $this->employee($foreign, 'TECH-X');

        $this->actingAs($maker)->post(route('admin.maintenance.requests.store'), ['company_id' => $company, 'asset_id' => $asset, 'code' => 'MR-001', 'title' => 'Kiểm tra máy lạnh', 'description' => 'Thiết bị phát tiếng ồn bất thường.', 'priority' => 'HIGH'])->assertRedirect();
        $request = (int) DB::table('maintenance_requests')->where('code', 'MR-001')->value('id');
        self::assertSame($branch, (int) DB::table('maintenance_requests')->where('id', $request)->value('branch_id'));
        $this->actingAs($maker)->post(route('admin.maintenance.requests.submit', $request), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->post(route('admin.maintenance.requests.decision', $request), ['company_id' => $company, 'decision' => 'approve'])->assertForbidden();
        $this->actingAs($checker)->post(route('admin.maintenance.requests.decision', $request), ['company_id' => $company, 'decision' => 'approve'])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.maintenance.requests.convert', $request), ['company_id' => $company])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.maintenance.requests.convert', $request), ['company_id' => $company])->assertRedirect();
        self::assertSame(1, DB::table('maintenance_work_orders')->where('maintenance_request_id', $request)->count());
        $order = (int) DB::table('maintenance_work_orders')->where('maintenance_request_id', $request)->value('id');

        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.assign', $order), ['company_id' => $company, 'employee_id' => $foreignTechnician])->assertNotFound();
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.assign', $order), ['company_id' => $company, 'employee_id' => $technician])->assertRedirect();
        foreach (['PLANNED', 'IN_PROGRESS'] as $status) {
            $this->actingAs($checker)->post(route('admin.maintenance.work-orders.status', $order), ['company_id' => $company, 'status' => $status])->assertRedirect();
        }
        $complete = ['company_id' => $company, 'status' => 'COMPLETED', 'completion_note' => 'Đã kiểm tra và thay thế linh kiện hỏng.', 'downtime_minutes' => 75];
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.status', $order), $complete)->assertRedirect();
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.status', $order), $complete)->assertRedirect();
        self::assertSame('COMPLETED', DB::table('maintenance_work_orders')->where('id', $order)->value('status'));
        self::assertSame(75, (int) DB::table('maintenance_work_orders')->where('id', $order)->value('downtime_minutes'));
        $this->actingAs($checker)->get(route('admin.maintenance.work-orders.show', ['order' => $order, 'company_id' => $foreign]))->assertNotFound();
    }

    public function test_preventive_occurrence_is_generated_once(): void
    {
        $company = $this->company('CMMS-C');
        $actor = $this->actor($company, 'planner@cmms.test');
        $branch = $this->branch($company, 'HN');
        $asset = $this->asset($company, $branch, $actor, 'ASSET-002');
        $payload = ['company_id' => $company, 'asset_id' => $asset, 'code' => 'PM-001', 'name' => 'Bảo trì UPS hàng tháng', 'frequency_type' => 'MONTHLY', 'frequency_value' => 1, 'next_due_date' => '2026-09-30'];
        $this->actingAs($actor)->post(route('admin.maintenance.plans.store'), $payload)->assertRedirect();
        $plan = (int) DB::table('preventive_maintenance_plans')->where('code', 'PM-001')->value('id');
        $this->actingAs($actor)->post(route('admin.maintenance.plans.generate', $plan), ['company_id' => $company])->assertRedirect();
        $this->actingAs($actor)->post(route('admin.maintenance.plans.generate', $plan), ['company_id' => $company])->assertRedirect();
        self::assertSame(1, DB::table('maintenance_work_orders')->where(['preventive_plan_id' => $plan, 'preventive_due_date' => '2026-09-30'])->count());
    }

    public function test_work_order_creates_idempotent_expense_and_purchase_request_through_owner_boundaries(): void
    {
        $company = $this->company('CMMS-I');
        $branch = $this->branch($company, 'CMMS-BR');
        $actor = $this->actor($company, 'integration@cmms.test');
        $costCenter = (int) DB::table('cost_centers')->insertGetId(['company_id' => $company, 'code' => 'CMMS-CC', 'name' => 'Maintenance Cost Center', 'created_at' => now(), 'updated_at' => now()]);
        $asset = $this->asset($company, $branch, $actor, 'ASSET-I');
        $this->actingAs($actor)->post(route('admin.maintenance.work-orders.store'), ['company_id' => $company, 'branch_id' => $branch, 'asset_id' => $asset, 'code' => 'MWO-LINK', 'title' => 'Sửa chữa có chứng từ', 'maintenance_type' => 'CORRECTIVE', 'priority' => 'HIGH'])->assertRedirect();
        $order = (int) DB::table('maintenance_work_orders')->where('code', 'MWO-LINK')->value('id');
        $category = (int) DB::table('finance_expense_categories')->insertGetId(['company_id' => $company, 'code' => 'REPAIR', 'name' => 'External repair', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $expense = ['company_id' => $company, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'expense_category_id' => $category, 'reference' => 'EXP-MWO-LINK', 'amount_minor' => 1750000, 'expense_date' => '2026-09-09', 'description' => 'Phí sửa chữa bên ngoài'];
        $this->actingAs($actor)->post(route('admin.maintenance.expenses.store', $order), $expense)->assertRedirect();
        $this->actingAs($actor)->post(route('admin.maintenance.expenses.store', $order), $expense)->assertRedirect();
        self::assertSame(1, DB::table('finance_expenses')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->count());
        self::assertSame('Maintenance Cost Center', DB::table('finance_expenses')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->value('cost_center_name_snapshot'));
        $pr = ['company_id' => $company, 'branch_id' => $branch, 'cost_center_id' => $costCenter, 'reference' => 'PR-MWO-LINK', 'requested_date' => '2026-09-09', 'reason' => 'Thiếu phụ tùng', 'description' => 'Mua phụ tùng thay thế', 'quantity' => 1, 'estimated_unit_cost_minor' => 900000];
        $this->actingAs($actor)->post(route('admin.maintenance.purchase-requests.store', $order), $pr)->assertRedirect();
        $this->actingAs($actor)->post(route('admin.maintenance.purchase-requests.store', $order), $pr)->assertRedirect();
        self::assertSame(1, DB::table('purchase_requests')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->count());
        self::assertSame('Maintenance Cost Center', DB::table('purchase_requests')->where(['source_type' => 'MAINTENANCE_WORK_ORDER', 'source_id' => $order])->value('cost_center_name_snapshot'));
        $this->actingAs($actor)->get(route('admin.maintenance.work-orders.show', ['order' => $order, 'company_id' => $company]))->assertOk()->assertSee('EXP-MWO-LINK')->assertSee('PR-MWO-LINK');
    }

    public function test_spare_part_issue_posts_policy_backed_inventory_consumption_exactly_once(): void
    {
        $this->travelTo('2026-09-10 09:00:00');
        $company = $this->company('CMMS-GL');
        $branch = $this->branch($company, 'CMMS-WH');
        $maker = $this->actor($company, 'parts-maker@cmms.test');
        $checker = $this->actor($company, 'parts-checker@cmms.test');
        $asset = $this->asset($company, $branch, $maker, 'ASSET-GL');

        $posting = app(SourceAccountingPostingService::class);
        $expense = $posting->accountEvidence($company, '5100', 'VND', (int) $maker->id)['id'];
        $inventory = $posting->accountEvidence($company, '1200', 'VND', (int) $maker->id)['id'];
        $policies = app(AccountingPostingPolicyService::class);
        $policy = $policies->create($company, $maker, [
            'policy_key' => 'MAINTENANCE-PART-CONSUMPTION', 'source_type' => 'maintenance_part_requirement',
            'business_event' => 'MAINTENANCE_PART_CONSUMPTION', 'classification' => null, 'version' => 1,
            'currency' => 'VND', 'effective_from' => '2026-01-01',
            'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'EXPENSE', 'account_id' => $expense],
                ['entry_side' => 'credit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory],
            ],
        ]);
        $policies->submit($company, $policy, $maker);
        $policies->approve($company, $policy, $checker);
        $returnPolicy = $policies->create($company, $maker, [
            'policy_key' => 'MAINTENANCE-PART-RETURN', 'source_type' => 'maintenance_part_return',
            'business_event' => 'MAINTENANCE_PART_RETURN', 'classification' => null, 'version' => 1,
            'currency' => 'VND', 'effective_from' => '2026-01-01',
            'lines' => [
                ['entry_side' => 'debit', 'line_role' => 'INVENTORY_ASSET', 'account_id' => $inventory],
                ['entry_side' => 'credit', 'line_role' => 'EXPENSE', 'account_id' => $expense],
            ],
        ]);
        $policies->submit($company, $returnPolicy, $maker);
        $policies->approve($company, $returnPolicy, $checker);

        $profile = (int) DB::table('seller_profiles')->insertGetId(['company_id' => $company, 'user_id' => $maker->id, 'display_name' => 'Maintenance Store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $store = (int) DB::table('stores')->insertGetId(['seller_profile_id' => $profile, 'name' => 'Maintenance Store', 'slug' => 'maintenance-store', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId(['store_id' => $store, 'code' => 'SPARE-P', 'name' => 'Spare Part', 'slug' => 'spare-part', 'base_price_minor' => 1000, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $variant = (int) DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'SPARE-001', 'name' => 'Default', 'price_minor' => 1000, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $location = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $branch, 'code' => 'MAINT-WH', 'name' => 'Maintenance Warehouse', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $location, 'on_hand' => 10, 'reserved' => 0, 'inventory_value_minor' => 10_000, 'average_unit_cost_minor' => 1000, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($maker)->post(route('admin.maintenance.work-orders.store'), ['company_id' => $company, 'branch_id' => $branch, 'asset_id' => $asset, 'code' => 'MWO-GL', 'title' => 'Replace spare part', 'maintenance_type' => 'CORRECTIVE', 'priority' => 'HIGH'])->assertRedirect();
        $order = (int) DB::table('maintenance_work_orders')->where('code', 'MWO-GL')->value('id');
        $this->actingAs($maker)->post(route('admin.maintenance.parts.store', $order), ['company_id' => $company, 'product_variant_id' => $variant, 'inventory_location_id' => $location, 'quantity' => 3, 'idempotency_key' => 'MWO-GL-PART'])->assertRedirect();
        $part = (int) DB::table('maintenance_part_requirements')->where('work_order_id', $order)->value('id');

        $issue = route('admin.maintenance.parts.issue', [$order, $part]);
        DB::table('accounting_posting_policies')->where('id', $policy)->update(['status' => 'draft']);
        $this->actingAs($maker)->post($issue, ['company_id' => $company])->assertRedirect()->assertSessionHasErrors('accounting_policy');
        self::assertSame(10, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(0, DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-PART')->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_requirement', 'source_id' => $part])->count());
        DB::table('accounting_posting_policies')->where('id', $policy)->update(['status' => 'approved']);
        $period = (int) DB::table('accounting_periods')->insertGetId(['company_id' => $company, 'year' => 2026, 'month' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($maker)->post($issue, ['company_id' => $company])->assertRedirect()->assertSessionHasErrors('period');
        self::assertSame(10, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(0, DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-PART')->count());
        self::assertSame(0, DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_requirement', 'source_id' => $part])->count());
        DB::table('accounting_periods')->where('id', $period)->update(['status' => 'open']);
        $this->actingAs($maker)->post($issue, ['company_id' => $company])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($maker)->post($issue, ['company_id' => $company])->assertRedirect()->assertSessionHasNoErrors();

        self::assertSame(7, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-PART')->count());
        self::assertSame(1, DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_requirement', 'source_id' => $part, 'posting_type' => 'maintenance_part_consumption'])->count());
        $journal = DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_requirement', 'source_id' => $part])->firstOrFail();
        $mapping = json_decode((string) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->value('mapping_snapshot'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($policy, $mapping['policy_id']);
        self::assertSame(1, $mapping['policy_version']);
        self::assertSame(3000, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->sum('debit_minor'));
        self::assertSame(3000, (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->sum('credit_minor'));
        $quality = app(DataQualityAuditService::class);
        $finding = static fn (array $snapshot, string $key): int => (int) (collect($snapshot['findings'])->firstWhere('key', $key)['count'] ?? -1);
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.part_missing_accounting'));
        $debitLine = (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->where('debit_minor', '>', 0)->value('id');
        DB::table('payroll_accounting_journal_lines')->where('id', $debitLine)->increment('debit_minor');
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_missing_accounting'), 'journal amount mismatch');
        DB::table('payroll_accounting_journal_lines')->where('id', $debitLine)->decrement('debit_minor');
        $lineSnapshots = DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->pluck('mapping_snapshot', 'id');
        DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->update(['mapping_snapshot' => json_encode(['policy' => 'accounting_posting_policy', 'policy_id' => null, 'policy_version' => null], JSON_THROW_ON_ERROR)]);
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_missing_accounting'), 'missing policy snapshot');
        foreach ($lineSnapshots as $lineId => $snapshot) {
            DB::table('payroll_accounting_journal_lines')->where('id', $lineId)->update(['mapping_snapshot' => $snapshot]);
        }
        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['source_type' => 'corrupt_source']);
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_missing_accounting'), 'issued requirement without canonical journal source');
        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['source_type' => 'maintenance_part_requirement']);
        $duplicateJournal = get_object_vars($journal);
        unset($duplicateJournal['id']);
        $duplicateJournal['uuid'] = (string) Str::uuid();
        $duplicateJournal['generic_source_key'] = $duplicateJournal['generic_source_key'].'-duplicate';
        $duplicateJournal['journal_number'] = $duplicateJournal['journal_number'].'-DUP';
        $duplicateJournal['idempotency_key'] = (string) Str::uuid();
        $duplicateJournalId = (int) DB::table('payroll_accounting_journals')->insertGetId($duplicateJournal);
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_missing_accounting'), 'duplicate accounting journal');
        DB::table('payroll_accounting_journals')->where('id', $duplicateJournalId)->delete();
        $movement = DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-PART')->firstOrFail();
        $duplicateMovement = get_object_vars($movement);
        unset($duplicateMovement['id']);
        $duplicateMovement['idempotency_key'] = 'MWO-GL-PART-DUPLICATE';
        $duplicateMovementId = (int) DB::table('inventory_movements')->insertGetId($duplicateMovement);
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_missing_inventory'), 'duplicate inventory movement');
        DB::table('inventory_movements')->where('id', $duplicateMovementId)->delete();
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.part_missing_inventory'));
        $returnRoute = route('admin.maintenance.parts.return', [$order, $part]);
        $returnOne = ['company_id' => $company, 'quantity' => 1, 'return_reference' => 'MWO-GL-RETURN-1', 'idempotency_key' => 'MWO-GL-RETURN-1', 'return_date' => '2026-09-10', 'reason' => 'Unused physical part returned'];
        DB::table('accounting_posting_policies')->where('id', $returnPolicy)->update(['status' => 'draft']);
        $this->actingAs($maker)->post($returnRoute, $returnOne)->assertRedirect()->assertSessionHasErrors('accounting_policy');
        self::assertSame(0, DB::table('maintenance_part_returns')->where('maintenance_part_requirement_id', $part)->count());
        DB::table('accounting_posting_policies')->where('id', $returnPolicy)->update(['status' => 'approved']);
        DB::table('accounting_periods')->where('id', $period)->update(['status' => 'closed']);
        $this->actingAs($maker)->post($returnRoute, $returnOne)->assertRedirect()->assertSessionHasErrors('period');
        self::assertSame(0, DB::table('inventory_movements')->where('source_type', 'maintenance_part_return')->count());
        DB::table('accounting_periods')->where('id', $period)->update(['status' => 'open']);
        $this->actingAs($maker)->post($returnRoute, $returnOne)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($maker)->post($returnRoute, $returnOne)->assertRedirect()->assertSessionHasNoErrors();
        $returnTwo = array_replace($returnOne, ['return_reference' => 'MWO-GL-RETURN-2', 'idempotency_key' => 'MWO-GL-RETURN-2']);
        $this->actingAs($maker)->post($returnRoute, $returnTwo)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($maker)->post($returnRoute, array_replace($returnOne, ['quantity' => 2, 'return_reference' => 'MWO-GL-RETURN-OVER', 'idempotency_key' => 'MWO-GL-RETURN-OVER']))->assertStatus(422);
        self::assertSame(2, DB::table('maintenance_part_returns')->where('maintenance_part_requirement_id', $part)->count());
        self::assertSame(2, DB::table('inventory_movements')->where('source_type', 'maintenance_part_return')->count());
        self::assertSame(2, DB::table('payroll_accounting_journals')->where(['source_type' => 'maintenance_part_return', 'posting_type' => 'maintenance_part_return'])->count());
        self::assertSame(9, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('on_hand'));
        self::assertSame(9_000, (int) DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->value('inventory_value_minor'));
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.part_return_inventory_invalid'));
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.part_return_accounting_invalid'));
        $firstReturn = DB::table('maintenance_part_returns')->where('return_reference', 'MWO-GL-RETURN-1')->firstOrFail();
        DB::table('inventory_movements')->where('id', $firstReturn->inventory_movement_id)->increment('movement_value_minor');
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_return_inventory_invalid'));
        DB::table('inventory_movements')->where('id', $firstReturn->inventory_movement_id)->decrement('movement_value_minor');
        $returnDebit = (int) DB::table('payroll_accounting_journal_lines')->where('journal_id', $firstReturn->accounting_journal_id)->where('debit_minor', '>', 0)->value('id');
        DB::table('payroll_accounting_journal_lines')->where('id', $returnDebit)->increment('debit_minor');
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_return_accounting_invalid'));
        DB::table('payroll_accounting_journal_lines')->where('id', $returnDebit)->decrement('debit_minor');
        $returnLineSnapshots = DB::table('payroll_accounting_journal_lines')->where('journal_id', $firstReturn->accounting_journal_id)->pluck('mapping_snapshot', 'id');
        DB::table('payroll_accounting_journal_lines')->where('journal_id', $firstReturn->accounting_journal_id)->update(['mapping_snapshot' => json_encode(['policy' => 'accounting_posting_policy', 'policy_id' => null, 'policy_version' => null], JSON_THROW_ON_ERROR)]);
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.part_return_accounting_invalid'), 'missing return policy/version snapshot');
        foreach ($returnLineSnapshots as $lineId => $snapshot) {
            DB::table('payroll_accounting_journal_lines')->where('id', $lineId)->update(['mapping_snapshot' => $snapshot]);
        }
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.part_return_accounting_invalid'));
        $this->actingAs($maker)->get(route('admin.maintenance.work-orders.show', ['order' => $order, 'company_id' => $company]))->assertOk()->assertSee((string) $journal->journal_number)->assertSee('SPARE-001');

        $insufficient = (int) DB::table('maintenance_part_requirements')->insertGetId(['work_order_id' => $order, 'product_variant_id' => $variant, 'inventory_location_id' => $location, 'quantity' => 99, 'status' => 'REQUIRED', 'idempotency_key' => 'MWO-GL-INSUFFICIENT', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($maker)->post(route('admin.maintenance.parts.issue', [$order, $insufficient]), ['company_id' => $company])->assertRedirect()->assertSessionHasErrors('inventory');
        self::assertSame(0, DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-INSUFFICIENT')->count());
        self::assertSame('REQUIRED', DB::table('maintenance_part_requirements')->where('id', $insufficient)->value('status'));

        $zeroValue = (int) DB::table('maintenance_part_requirements')->insertGetId(['work_order_id' => $order, 'product_variant_id' => $variant, 'inventory_location_id' => $location, 'quantity' => 1, 'status' => 'REQUIRED', 'idempotency_key' => 'MWO-GL-ZERO', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->where(['product_variant_id' => $variant, 'inventory_location_id' => $location])->update(['inventory_value_minor' => 0, 'average_unit_cost_minor' => 0]);
        $this->actingAs($maker)->post(route('admin.maintenance.parts.issue', [$order, $zeroValue]), ['company_id' => $company])->assertStatus(422);
        self::assertSame(0, DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-ZERO')->count());
        self::assertSame('REQUIRED', DB::table('maintenance_part_requirements')->where('id', $zeroValue)->value('status'));

        $foreignBranch = $this->branch($company, 'CMMS-OTHER');
        $foreignLocation = (int) DB::table('inventory_locations')->insertGetId(['store_id' => $store, 'branch_id' => $foreignBranch, 'code' => 'OTHER-WH', 'name' => 'Other Branch Warehouse', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_variant_id' => $variant, 'inventory_location_id' => $foreignLocation, 'on_hand' => 2, 'reserved' => 0, 'inventory_value_minor' => 2_000, 'average_unit_cost_minor' => 1_000, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $wrongBranch = (int) DB::table('maintenance_part_requirements')->insertGetId(['work_order_id' => $order, 'product_variant_id' => $variant, 'inventory_location_id' => $foreignLocation, 'quantity' => 1, 'status' => 'REQUIRED', 'idempotency_key' => 'MWO-GL-WRONG-BRANCH', 'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($maker)->post(route('admin.maintenance.parts.issue', [$order, $wrongBranch]), ['company_id' => $company])->assertStatus(422);
        self::assertSame(0, DB::table('inventory_movements')->where('idempotency_key', 'MWO-GL-WRONG-BRANCH')->count());
        DB::table('maintenance_part_requirements')->whereIn('id', [$insufficient, $zeroValue, $wrongBranch])->delete();
        DB::table('maintenance_work_orders')->where('id', $order)->update(['status' => 'COMPLETED', 'actual_start' => now()->subHour(), 'actual_end' => now(), 'completion_note' => 'Spare replacement completed', 'updated_at' => now()]);
        $this->actingAs($checker)->post(route('admin.maintenance.work-orders.accept', $order), ['company_id' => $company, 'acceptance_note' => 'Partial-return work accepted', 'idempotency_key' => 'MWO-GL-ACCEPT'])->assertRedirect();
        $closeRoute = route('admin.maintenance.work-orders.close', $order);
        $closePayload = ['company_id' => $company, 'idempotency_key' => 'MWO-GL-CLOSE'];
        $assertCloseoutRejected = function () use ($checker, $closeRoute, $closePayload, $order): void {
            $this->actingAs($checker)->post($closeRoute, $closePayload)->assertStatus(409);
            self::assertSame('COMPLETED', DB::table('maintenance_work_orders')->where('id', $order)->value('status'));
            self::assertSame(0, DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->count());
        };

        DB::table('maintenance_part_requirements')->where('id', $part)->update(['inventory_movement_id' => null]);
        $assertCloseoutRejected();
        DB::table('maintenance_part_requirements')->where('id', $part)->update(['inventory_movement_id' => $movement->id]);

        DB::table('maintenance_part_returns')->where('id', $firstReturn->id)->update(['inventory_movement_id' => null]);
        $assertCloseoutRejected();
        DB::table('maintenance_part_returns')->where('id', $firstReturn->id)->update(['inventory_movement_id' => $firstReturn->inventory_movement_id]);

        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['source_type' => 'corrupt_source']);
        $assertCloseoutRejected();
        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['source_type' => 'maintenance_part_requirement']);

        DB::table('maintenance_part_returns')->where('id', $firstReturn->id)->update(['accounting_journal_id' => null]);
        $assertCloseoutRejected();
        DB::table('maintenance_part_returns')->where('id', $firstReturn->id)->update(['accounting_journal_id' => $firstReturn->accounting_journal_id]);

        $issueLineSnapshots = DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->pluck('mapping_snapshot', 'id');
        DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->update(['mapping_snapshot' => json_encode(['policy' => 'accounting_posting_policy', 'policy_id' => null, 'policy_version' => null], JSON_THROW_ON_ERROR)]);
        $assertCloseoutRejected();
        foreach ($issueLineSnapshots as $lineId => $snapshot) {
            DB::table('payroll_accounting_journal_lines')->where('id', $lineId)->update(['mapping_snapshot' => $snapshot]);
        }

        $invalidVersionSnapshot = json_encode(['policy' => 'accounting_posting_policy', 'policy_id' => $policy, 'policy_version' => 999999], JSON_THROW_ON_ERROR);
        DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->update(['mapping_snapshot' => $invalidVersionSnapshot]);
        $assertCloseoutRejected();
        foreach ($issueLineSnapshots as $lineId => $snapshot) {
            DB::table('payroll_accounting_journal_lines')->where('id', $lineId)->update(['mapping_snapshot' => $snapshot]);
        }

        $foreignCompany = $this->company('CMMS-CLOSE-FOREIGN');
        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['company_id' => $foreignCompany]);
        $assertCloseoutRejected();
        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['company_id' => $company]);

        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['branch_id' => $foreignBranch]);
        $assertCloseoutRejected();
        DB::table('payroll_accounting_journals')->where('id', $journal->id)->update(['branch_id' => $branch]);

        DB::table('accounting_periods')->where('id', $period)->update(['status' => 'closed']);
        $journalCountBeforeCloseout = DB::table('payroll_accounting_journals')->count();
        $this->actingAs($checker)->post($closeRoute, $closePayload)->assertRedirect();
        self::assertSame($journalCountBeforeCloseout, DB::table('payroll_accounting_journals')->count(), 'Closeout is a reconciliation snapshot and must not bypass a closed period by posting a new journal.');
        $closeout = DB::table('maintenance_work_order_closeouts')->where('work_order_id', $order)->firstOrFail();
        self::assertEquals(3000, $closeout->gross_spare_cost_minor);
        self::assertEquals(2000, $closeout->returned_spare_cost_minor);
        self::assertEquals(1000, $closeout->net_spare_cost_minor);
        self::assertEquals(1000, $closeout->total_actual_cost_minor);
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.closeout_cost_snapshot_invalid'), 'valid closeout snapshot');
        DB::table('maintenance_work_order_closeouts')->where('id', $closeout->id)->increment('total_actual_cost_minor');
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.closeout_cost_snapshot_invalid'), 'corrupted closeout cost snapshot');
        DB::table('maintenance_work_order_closeouts')->where('id', $closeout->id)->decrement('total_actual_cost_minor');
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.closeout_cost_snapshot_invalid'), 'restored closeout snapshot');
        $acceptanceCompany = (int) DB::table('maintenance_work_order_acceptances')->where('work_order_id', $order)->value('company_id');
        DB::table('maintenance_work_order_acceptances')->where('work_order_id', $order)->update(['company_id' => $foreignCompany]);
        self::assertGreaterThan(0, $finding($quality->snapshot(), 'maintenance.closeout_lineage_invalid'), 'corrupted closeout tenant lineage');
        DB::table('maintenance_work_order_acceptances')->where('work_order_id', $order)->update(['company_id' => $acceptanceCompany]);
        self::assertSame(0, $finding($quality->snapshot(), 'maintenance.closeout_lineage_invalid'), 'restored closeout tenant lineage');
    }

    private function actor(int $company, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $user->givePermissionTo(['maintenance.view', 'maintenance.request.create', 'maintenance.request.submit', 'maintenance.request.approve', 'maintenance.work-order.create', 'maintenance.work-order.assign', 'maintenance.work-order.execute', 'maintenance.part.issue', 'maintenance.part.return', 'maintenance.plan.manage', 'finance.operations.manage', 'procurement.manage']);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function branch(int $company, string $code): int
    {
        return (int) DB::table('branches')->insertGetId(['company_id' => $company, 'code' => $code, 'name' => $code, 'address_line' => '1 Test Street', 'city' => 'Hồ Chí Minh', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function employee(int $company, string $code): int
    {
        return (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => $code, 'full_name' => $code, 'join_date' => today(), 'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function asset(int $company, int $branch, User $actor, string $code): int
    {
        $category = (int) DB::table('finance_asset_categories')->insertGetId(['company_id' => $company, 'code' => $code.'-CAT', 'name' => 'Thiết bị', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('finance_assets')->insertGetId(['company_id' => $company, 'asset_category_id' => $category, 'branch_id' => $branch, 'asset_code' => $code, 'name' => $code, 'acquisition_cost_minor' => 10000000, 'residual_value_minor' => 0, 'currency' => 'VND', 'acquisition_date' => '2026-01-01', 'in_service_date' => '2026-01-02', 'useful_life_months' => 36, 'depreciation_method' => 'straight_line', 'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}

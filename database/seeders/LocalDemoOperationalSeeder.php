<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Compensation\PositionCompensationReference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\DTOs\ErpAdministrationData;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\AccountingAdministrationService;
use Modules\Payroll\Domain\Services\EmployeePaymentAccountService;
use Modules\Payroll\Domain\Services\ErpAdministrationService;
use Modules\Payroll\Domain\Services\ErpExportService;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;
use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;
use Modules\Payroll\Domain\Services\PayrollAdjustmentManagementService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxLifecycleService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxOperationsService;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Modules\Payroll\Domain\Services\PayrollInputOperationsService;
use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;
use Modules\Payroll\Domain\Services\PayrollPeriodOperationsService;
use Modules\Payroll\Domain\Services\PayrollRunService;
use Modules\Payroll\Domain\Services\PayslipGenerationService;
use Modules\Payroll\Domain\Services\TaxManagementService;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Spatie\Permission\Models\Role;

final class LocalDemoOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'SCHEMA-TECH-VN')->firstOrFail();
        $admin = User::query()->whereIn('email', ['admin@local.test', 'admin@schema.vn'])->orderByRaw("email = 'admin@local.test' DESC")->firstOrFail();
        $this->actors($company->id);
        $this->positionLevels($company->id);
        $employees = Employee::query()->where('company_id', $company->id)->whereIn('employment_status', ['active', 'probation', 'leave'])->orderBy('employee_code')->limit(18)->get();
        $employeeIds = array_values($employees->pluck('id')->map(fn (mixed $id): int => $this->requiredId($id, 'employee'))->all());
        $this->workforce($company->id, $employeeIds, $admin->id);
        $this->payroll($company->id, $employeeIds, $admin->id);
        $this->notifications($company->id, $admin->id);
        $this->integration($company->id, $admin->id);
        $this->iam($company->id, $admin->id);
        $this->workflow($company->id, $admin->id);
        $this->commerceFoundation($admin);
        $this->commerceCatalog();
        $this->commerceBuyerExperience();
        app(AuditRecorderInterface::class)->record(new AuditEventData($company->id, 'user', $admin->id, 'bootstrap', 'LocalDemo', 'LocalDemoBootstrapCompleted', 'local_demo_dataset', 'SCHEMA-LOCAL-DEMO', [], ['status' => 'ready'], ['synthetic_demo' => true, 'external_delivery' => false], 'local-demo-bootstrap-v1', $this->uuid(701)));
    }

    private function commerceFoundation(User $admin): void
    {
        $cities = [['HCM', 'Chi nhánh TP.HCM', 'TP.HCM'], ['HN', 'Chi nhánh Hà Nội', 'Hà Nội'], ['DN', 'Chi nhánh Đà Nẵng', 'Đà Nẵng']];
        foreach (DB::table('companies')->orderBy('id')->get(['id', 'code']) as $companyIndex => $company) {
            DB::table('company_user_access')->updateOrInsert(['company_id' => $company->id, 'user_id' => $admin->id], ['created_at' => now(), 'updated_at' => now()]);
            foreach (array_slice($cities, 0, $companyIndex === 0 ? 3 : (($companyIndex % 2) + 1)) as [$suffix, $name, $city]) {
                DB::table('branches')->updateOrInsert(['company_id' => $company->id, 'code' => $company->code.'-'.$suffix], ['name' => $name, 'status' => 'active', 'address_line' => 'Địa chỉ synthetic phục vụ local demo', 'city' => $city, 'timezone' => 'Asia/Ho_Chi_Minh', 'email' => strtolower($suffix).'@branch.local.test', 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (DB::table('branches')->where('company_id', $company->id)->pluck('id') as $branchId) {
                DB::table('branch_user_access')->updateOrInsert(['branch_id' => $branchId, 'user_id' => $admin->id], ['created_at' => now(), 'updated_at' => now()]);
            }
            $leader = DB::table('employees as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('employee_assignments as a', function ($join): void {
                $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
            })->where('e.company_id', $company->id)->whereIn('e.employment_status', ['active', 'probation'])->where('u.access_enabled', true)->orderByDesc('e.id')->first(['e.id', 'e.user_id']);
            if ($leader !== null) {
                foreach (DB::table('branches')->where('company_id', $company->id)->pluck('id') as $branchId) {
                    DB::table('branches')->where('id', $branchId)->whereNull('leader_employee_id')->update(['leader_employee_id' => $leader->id, 'updated_at' => now()]);
                    DB::table('branch_user_access')->updateOrInsert(['branch_id' => $branchId, 'user_id' => $leader->user_id], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $seller = User::query()->updateOrCreate(['email' => 'seller@local.test'], ['name' => 'Seller Synthetic Việt Nam', 'password' => Hash::make('LocalSeller123!'), 'email_verified_at' => now(), 'access_enabled' => true]);
        $seller->syncRoles(['Seller']);
        DB::table('seller_profiles')->updateOrInsert(['user_id' => $seller->id], ['display_name' => 'Sao Việt Seller', 'status' => 'active', 'verified_at' => now(), 'verified_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $sellerProfileId = (int) DB::table('seller_profiles')->where('user_id', $seller->id)->value('id');
        DB::table('stores')->updateOrInsert(['slug' => 'sao-viet-technology-store'], ['seller_profile_id' => $sellerProfileId, 'name' => 'Sao Việt Technology Store', 'description' => 'Cửa hàng synthetic phục vụ kiểm thử Seller/Store local, chưa có Product hoặc Order.', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $buyer = User::query()->updateOrCreate(['email' => 'buyer@local.test'], ['name' => 'Buyer Synthetic Việt Nam', 'password' => Hash::make('LocalBuyer123!'), 'email_verified_at' => now(), 'access_enabled' => true]);
        $buyer->syncRoles(['Buyer']);
        DB::table('buyer_profiles')->updateOrInsert(['user_id' => $buyer->id], ['display_name' => 'Khách hàng Synthetic', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('articles')->whereNull('company_id')->update(['company_id' => DB::table('companies')->where('code', 'SCHEMA-TECH-VN')->value('id')]);
    }

    private function commerceCatalog(): void
    {
        $categories = [
            ['ELEC', 'Điện tử', null, 10],
            ['PHONE', 'Điện thoại', 'ELEC', 20],
            ['LAPTOP', 'Laptop', 'ELEC', 30],
            ['ACCESSORY', 'Phụ kiện', 'ELEC', 40],
            ['HOME', 'Gia dụng', null, 50],
            ['FASHION', 'Thời trang', null, 60],
            ['HEALTH', 'Sức khỏe', null, 70],
            ['OFFICE', 'Văn phòng', null, 80],
        ];
        $categoryIds = [];
        foreach ($categories as [$code, $name, $parentCode, $order]) {
            $slug = strtolower($code);
            DB::table('product_categories')->updateOrInsert(
                ['slug' => $slug],
                ['parent_id' => $parentCode === null ? null : ($categoryIds[$parentCode] ?? null), 'name' => $name, 'status' => 'active', 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()],
            );
            $categoryIds[$code] = $this->requiredId(DB::table('product_categories')->where('slug', $slug)->value('id'), 'product category');
        }

        $storeId = $this->requiredId(DB::table('stores')->where('slug', 'sao-viet-technology-store')->value('id'), 'commerce store');
        DB::table('inventory_locations')->updateOrInsert(
            ['store_id' => $storeId, 'code' => 'MAIN'],
            ['name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );
        $locationId = $this->requiredId(DB::table('inventory_locations')->where('store_id', $storeId)->where('code', 'MAIN')->value('id'), 'inventory location');
        $products = [
            ['SV-PHONE-01', 'Điện thoại Sao Việt Nova 5G', 'PHONE', 8_990_000, '256GB · Đen', 18],
            ['SV-LAPTOP-01', 'Laptop Sao Việt Pro 14', 'LAPTOP', 24_990_000, '16GB · 512GB', 9],
            ['SV-AUDIO-01', 'Tai nghe không dây AirBeat', 'ACCESSORY', 1_490_000, 'Màu trắng', 32],
            ['SV-KEYBOARD-01', 'Bàn phím cơ Mekong 87', 'ACCESSORY', 1_190_000, 'Blue switch', 24],
            ['SV-MONITOR-01', 'Màn hình Vision 27 QHD', 'ELEC', 6_790_000, '27 inch', 12],
            ['SV-ROUTER-01', 'Bộ phát Wi-Fi 6 HomeMesh', 'ELEC', 2_290_000, 'Bộ 2 thiết bị', 20],
            ['SV-CAMERA-01', 'Camera an ninh SmartEye', 'ELEC', 990_000, '2K · Trong nhà', 27],
            ['SV-HOME-01', 'Máy lọc không khí An Nhiên', 'HOME', 4_590_000, 'Phòng 40m²', 11],
        ];
        foreach ($products as [$code, $name, $categoryCode, $price, $variantName, $onHand]) {
            DB::table('products')->updateOrInsert(
                ['store_id' => $storeId, 'code' => $code],
                ['product_category_id' => $categoryIds[$categoryCode], 'name' => $name, 'slug' => strtolower($code), 'description' => 'Sản phẩm synthetic phục vụ local demo; không phải chào bán thương mại thật.', 'brand' => 'Sao Việt', 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            );
            $productId = $this->requiredId(DB::table('products')->where('store_id', $storeId)->where('code', $code)->value('id'), 'product');
            DB::table('product_variants')->updateOrInsert(
                ['sku' => $code.'-DEFAULT'],
                ['product_id' => $productId, 'name' => $variantName, 'attributes' => json_encode(['label' => $variantName], JSON_THROW_ON_ERROR), 'price_minor' => $price, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            );
            $variantId = $this->requiredId(DB::table('product_variants')->where('sku', $code.'-DEFAULT')->value('id'), 'product variant');
            DB::table('inventory_balances')->updateOrInsert(
                ['product_variant_id' => $variantId, 'inventory_location_id' => $locationId],
                ['on_hand' => $onHand, 'reserved' => min(2, $onHand), 'version' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $additionalStores = [
            ['seller.home@local.test', 'Nhà Việt Seller', 'nha-viet-living-store', 'Nhà Việt Living Store'],
            ['seller.office@local.test', 'Mekong Office Seller', 'mekong-office-store', 'Mekong Office Store'],
        ];
        $storeIds = [$storeId];
        foreach ($additionalStores as [$email, $sellerName, $storeSlug, $storeName]) {
            $seller = User::query()->updateOrCreate(['email' => $email], ['name' => $sellerName, 'password' => Hash::make('LocalSeller123!'), 'email_verified_at' => now(), 'access_enabled' => true]);
            $seller->syncRoles(['Seller']);
            DB::table('seller_profiles')->updateOrInsert(['user_id' => $seller->id], ['display_name' => $sellerName, 'status' => 'active', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $profileId = $this->requiredId(DB::table('seller_profiles')->where('user_id', $seller->id)->value('id'), 'seller profile');
            DB::table('stores')->updateOrInsert(['slug' => $storeSlug], ['seller_profile_id' => $profileId, 'name' => $storeName, 'description' => 'Cửa hàng synthetic phục vụ local marketplace demo.', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $storeIds[] = $this->requiredId(DB::table('stores')->where('slug', $storeSlug)->value('id'), 'commerce store');
        }

        $demoNames = [
            ['Nồi chiên Không Dầu An Nhiên', 'HOME', 2_690_000], ['Máy pha cà phê Ban Mê', 'HOME', 3_490_000],
            ['Máy hút bụi Sông Hồng', 'HOME', 4_190_000], ['Đèn bàn thông minh Lotus', 'OFFICE', 890_000],
            ['Ghế công thái học Mekong', 'OFFICE', 5_990_000], ['Bàn làm việc Tre Việt', 'OFFICE', 3_290_000],
            ['Máy in laser Văn Lang', 'OFFICE', 4_790_000], ['Webcam Hội Nghị Aurora', 'ELEC', 1_790_000],
            ['Chuột không dây Swift', 'ACCESSORY', 690_000], ['Ổ cứng di động Delta 1TB', 'ACCESSORY', 2_190_000],
            ['Sạc nhanh GaN 65W', 'ACCESSORY', 790_000], ['Đồng hồ sức khỏe Bình Minh', 'HEALTH', 2_990_000],
            ['Máy massage cổ An Tâm', 'HEALTH', 1_390_000], ['Cân điện tử SmartCare', 'HEALTH', 790_000],
            ['Áo khoác chống nắng Việt', 'FASHION', 590_000], ['Balo laptop Urban 15', 'FASHION', 890_000],
            ['Tai nghe Studio Mekong', 'ACCESSORY', 2_590_000], ['Tablet Hạ Long 11', 'ELEC', 9_490_000],
            ['Máy chiếu Mini Cinema', 'ELEC', 5_490_000], ['Bộ lưu điện HomeSafe', 'ELEC', 2_890_000],
            ['Quạt tuần hoàn Gió Việt', 'HOME', 1_690_000], ['Máy lọc nước Lam Sơn', 'HOME', 6_990_000],
        ];
        foreach ($demoNames as $index => [$name, $categoryCode, $price]) {
            $targetStore = $storeIds[$index % count($storeIds)];
            $code = 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            DB::table('products')->updateOrInsert(['store_id' => $targetStore, 'code' => $code], ['product_category_id' => $categoryIds[$categoryCode], 'name' => $name, 'slug' => strtolower($code), 'description' => 'Sản phẩm synthetic marketplace demo; không phải chào bán thương mại thật.', 'brand' => 'Thương hiệu Việt Demo', 'base_price_minor' => $price, 'currency' => 'VND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $productId = $this->requiredId(DB::table('products')->where('store_id', $targetStore)->where('code', $code)->value('id'), 'product');
            DB::table('inventory_locations')->updateOrInsert(['store_id' => $targetStore, 'code' => 'MAIN'], ['name' => 'Kho chính', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $targetLocation = $this->requiredId(DB::table('inventory_locations')->where('store_id', $targetStore)->where('code', 'MAIN')->value('id'), 'inventory location');
            foreach ([['STANDARD', 'Tiêu chuẩn', $price], ['PREMIUM', 'Cao cấp', $price + intdiv($price, 5)]] as [$suffix, $variantName, $variantPrice]) {
                $sku = $code.'-'.$suffix;
                DB::table('product_variants')->updateOrInsert(['sku' => $sku], ['product_id' => $productId, 'name' => $variantName, 'attributes' => json_encode(['edition' => $variantName], JSON_THROW_ON_ERROR), 'price_minor' => $variantPrice, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $variantId = $this->requiredId(DB::table('product_variants')->where('sku', $sku)->value('id'), 'product variant');
                DB::table('inventory_balances')->updateOrInsert(['product_variant_id' => $variantId, 'inventory_location_id' => $targetLocation], ['on_hand' => 8 + ($index % 20), 'reserved' => $index % 3, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function commerceBuyerExperience(): void
    {
        $buyerId = $this->requiredId(DB::table('users')->where('email', 'buyer@local.test')->value('id'), 'buyer');
        DB::table('carts')->updateOrInsert(['user_id' => $buyerId, 'status' => 'active'], ['created_at' => now(), 'updated_at' => now()]);
        $cartId = $this->requiredId(DB::table('carts')->where('user_id', $buyerId)->where('status', 'active')->value('id'), 'cart');
        $variants = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->where('v.status', 'active')->where('p.status', 'active')->orderBy('p.store_id')->orderBy('v.id')->limit(9)->get(['v.id', 'v.product_id', 'v.price_minor']);
        foreach ($variants as $index => $variant) {
            DB::table('cart_items')->updateOrInsert(['cart_id' => $cartId, 'product_variant_id' => $variant->id], ['product_id' => $variant->product_id, 'quantity' => ($index % 3) + 1, 'unit_price_minor' => $variant->price_minor, 'selected' => $index < 6, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('wishlists')->updateOrInsert(['user_id' => $buyerId], ['created_at' => now(), 'updated_at' => now()]);
        $wishlistId = $this->requiredId(DB::table('wishlists')->where('user_id', $buyerId)->value('id'), 'wishlist');
        foreach (DB::table('products')->where('status', 'active')->orderByDesc('id')->limit(10)->pluck('id') as $productId) {
            DB::table('wishlist_items')->updateOrInsert(['wishlist_id' => $wishlistId, 'product_id' => $productId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param list<int> $employeeIds */
    private function payroll(int $companyId, array $employeeIds, int $actorId): void
    {
        $componentDefinitions = [
            'BASE-SALARY-DEMO' => ['name' => 'Lương cơ bản synthetic', 'display_order' => 10, 'system_key' => 'base_salary', 'category' => 'earning'],
            'KPI-TARGET-DEMO' => ['name' => 'KPI mục tiêu synthetic', 'display_order' => 20, 'system_key' => 'kpi_target', 'category' => 'earning'],
            'FIXED-ALLOWANCE-DEMO' => ['name' => 'Phụ cấp cố định synthetic', 'display_order' => 30, 'system_key' => 'fixed_allowance', 'category' => 'allowance'],
        ];
        foreach ($componentDefinitions as $code => $definition) {
            DB::table('salary_components')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $definition['name'], 'description' => 'LOCAL SYNTHETIC DEMO ONLY — không phải dữ liệu luật định hoặc Net Pay.', 'category' => $definition['category'], 'calculation_type' => 'fixed', 'value_type' => 'money', 'default_value_minor' => 0, 'currency_code' => 'VND', 'taxable_status' => 'not_configured', 'insurance_status' => 'not_configured', 'affects_gross' => true, 'affects_net' => true, 'display_order' => $definition['display_order'], 'effective_from' => '2026-01-01', 'status' => 'active', 'is_system' => false, 'system_key' => $definition['system_key'], 'version' => 1, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
            );
        }
        $components = DB::table('salary_components')->where('company_id', $companyId)->whereIn('code', array_keys($componentDefinitions))->pluck('id', 'code');
        $references = app(PositionCompensationReference::class);
        foreach ($employeeIds as $employeeId) {
            $position = DB::table('employee_assignments as a')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->join('organization_units as u', 'u.id', '=', 'a.organization_unit_id')->where('a.employee_id', $employeeId)->where('a.assignment_type', 'primary')->whereNull('a.effective_to')->latest('a.effective_from')->first(['p.career_level', 'p.title', 'u.name as unit_name']);
            if ($position === null) {
                continue;
            }
            $reference = $references->resolve((int) $position->career_level, (string) $position->unit_name, (string) $position->title, '2026-01-01');
            foreach ([['BASE-SALARY-DEMO', $reference->baseSalaryMinor, 800], ['KPI-TARGET-DEMO', $reference->kpiTargetMinor, 1800], ['FIXED-ALLOWANCE-DEMO', $reference->fixedAllowanceMinor, 2800]] as [$code, $amount, $offset]) {
                DB::table('employee_salary_component_assignments')->updateOrInsert(
                    ['company_id' => $companyId, 'idempotency_key' => $this->uuid($offset + $employeeId)],
                    ['employee_id' => $employeeId, 'salary_component_id' => $this->requiredId($components[$code] ?? null, $code), 'amount_minor' => $amount, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'local_demo', 'status' => 'active', 'version' => 1, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        $makerId = $this->requiredId(User::query()->where('email', 'payroll.manager@local.test')->value('id'), 'payroll manager');
        foreach ([3 => PayrollRunStatus::Finalized, 4 => PayrollRunStatus::Approved, 5 => PayrollRunStatus::Submitted, 6 => PayrollRunStatus::Calculated, 8 => PayrollRunStatus::Calculated] as $month => $target) {
            $this->payrollMonth($companyId, $month, $makerId, $actorId, $target);
        }
        $this->payrollDelivery($companyId, $makerId, $actorId);
    }

    private function payrollDelivery(int $companyId, int $makerId, int $checkerId): void
    {
        $run = PayrollRun::query()->where('company_id', $companyId)->where('status', PayrollRunStatus::Finalized)->orderBy('id')->first();
        if (! $run instanceof PayrollRun) {
            return;
        }
        $employees = DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->get(['employee_id', 'employee_name_snapshot']);
        $accounts = app(EmployeePaymentAccountService::class);
        foreach ($employees as $index => $employee) {
            $employeeId = is_numeric($employee->employee_id ?? null) ? (int) $employee->employee_id : 0;
            if ($employeeId < 1 || DB::table('employee_payment_accounts')->where('company_id', $companyId)->where('employee_id', $employeeId)->exists()) {
                continue;
            }
            $accountId = $accounts->save($companyId, null, ['employee_id' => $employeeId, 'payment_method' => 'bank_transfer', 'bank_code' => 'DEMO', 'bank_name' => 'Ngân hàng Synthetic Việt Nam', 'branch_code' => 'HCM', 'account_number' => '9900'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT), 'account_holder_name' => is_string($employee->employee_name_snapshot ?? null) ? mb_strtoupper($employee->employee_name_snapshot) : 'NHAN VIEN DEMO', 'currency' => 'VND', 'is_primary' => true, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active'], $makerId);
            $accounts->verify($companyId, $accountId, $checkerId);
        }
        $payslips = app(PayslipGenerationService::class);
        foreach (DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->orderBy('id')->limit(2)->pluck('id') as $index => $itemId) {
            if (is_numeric($itemId) && ! DB::table('payroll_payslips')->where('company_id', $companyId)->where('payroll_item_id', $itemId)->exists()) {
                $payslips->requestItem($companyId, $run->id, (int) $itemId, $makerId, $this->uuid(1101 + $index));
            }
        }
        foreach (DB::table('payroll_payslips')->where('company_id', $companyId)->where('payroll_run_id', $run->id)->whereIn('status', ['pending', 'generating'])->orderBy('id')->limit(2)->pluck('id') as $payslipId) {
            if (is_numeric($payslipId)) {
                $payslips->generate((int) $payslipId);
            }
        }
        $payments = app(PayrollPaymentBatchService::class);
        $batchId = $payments->create($companyId, $run->id, $makerId, $this->uuid(1110));
        $batchStatus = DB::table('payroll_payment_batches')->where('id', $batchId)->value('status');
        if ($batchStatus === 'draft') {
            $payments->validate($companyId, $batchId, $makerId);
            $payments->review($companyId, $batchId, $makerId);
            $payments->approve($companyId, $batchId, $checkerId);
            $payments->release($companyId, $batchId, $makerId);
        }
        $journalId = $this->payrollAccounting($companyId, $run->id, $makerId, $checkerId);
        $this->payrollErp($companyId, $journalId, $makerId, $checkerId);
        $this->payrollAdjustment($companyId, $run->id, $makerId);
        $this->payrollAnnualTaxAndGovernment($companyId, $run->id, $makerId, $checkerId);
    }

    private function payrollAccounting(int $companyId, int $runId, int $makerId, int $checkerId): int
    {
        $admin = app(AccountingAdministrationService::class);
        $expense = DB::table('accounting_accounts')->where('company_id', $companyId)->where('code', '6421-DEMO')->value('id');
        $expenseId = is_numeric($expense) ? (int) $expense : $admin->saveAccount($companyId, null, ['code' => '6421-DEMO', 'name' => 'Chi phí lương synthetic', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null, 'parent_id' => null], $makerId);
        $payable = DB::table('accounting_accounts')->where('company_id', $companyId)->where('code', '3341-DEMO')->value('id');
        $payableId = is_numeric($payable) ? (int) $payable : $admin->saveAccount($companyId, null, ['code' => '3341-DEMO', 'name' => 'Phải trả nhân viên synthetic', 'account_type' => 'liability', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null, 'parent_id' => null], $makerId);
        $policy = DB::table('payroll_accounting_policies')->where('company_id', $companyId)->where('code', 'LOCAL-PAYROLL-DEMO')->value('id');
        $policyId = is_numeric($policy) ? (int) $policy : $admin->savePolicy($companyId, null, ['code' => 'LOCAL-PAYROLL-DEMO', 'name' => 'Chính sách kế toán lương synthetic', 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'company', 'effective_from' => '2026-01-01', 'effective_to' => null, 'rounding_rule' => 'minor_unit', 'status' => 'active', 'note' => 'LOCAL DEMO ONLY'], $makerId);
        foreach ([['earning_component', 'BASE-SALARY-DEMO', $expenseId, null], ['net_pay', 'NET_PAY', null, $payableId]] as [$type, $code, $debit, $credit]) {
            if (! DB::table('payroll_accounting_mappings')->where('company_id', $companyId)->where('accounting_policy_id', $policyId)->where('source_type', $type)->where('source_code', $code)->exists()) {
                $admin->saveMapping($companyId, null, ['accounting_policy_id' => $policyId, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'cost_center_id' => null, 'organization_unit_id' => null, 'priority' => 100, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active'], $makerId);
            }
        }
        $journals = app(PayrollAccountingJournalService::class);
        $id = $journals->create($companyId, $runId, '2026-04-03', $makerId, $this->uuid(1120));
        $status = DB::table('payroll_accounting_journals')->where('id', $id)->value('status');
        if ($status === 'draft') {
            $journals->validate($companyId, $id, $makerId);
            $journals->review($companyId, $id, $makerId);
            $journals->approve($companyId, $id, $checkerId);
            $journals->post($companyId, $id, $makerId);
        }

        return $id;
    }

    private function payrollErp(int $companyId, int $journalId, int $makerId, int $checkerId): void
    {
        $admin = app(ErpAdministrationService::class);
        $connection = DB::table('erp_connections')->where('company_id', $companyId)->where('name', 'ERP Generic CSV Synthetic')->value('id');
        $connectionId = is_numeric($connection) ? (int) $connection : $admin->execute(new ErpAdministrationData($companyId, 'connection', 'create', null, $makerId, ['name' => 'ERP Generic CSV Synthetic', 'provider' => 'generic_csv', 'endpoint_reference' => null, 'credential_reference' => null, 'status' => 'active']));
        foreach (DB::table('payroll_accounting_journal_lines')->where('company_id', $companyId)->where('journal_id', $journalId)->pluck('account_code_snapshot')->unique() as $code) {
            if (! is_string($code) || DB::table('erp_mapping_rules')->where('company_id', $companyId)->where('erp_connection_id', $connectionId)->where('source_account_code', $code)->exists()) {
                continue;
            }
            $admin->execute(new ErpAdministrationData($companyId, 'mapping', 'create', null, $makerId, ['erp_connection_id' => $connectionId, 'source_account_code' => $code, 'external_account_code' => 'ERP-'.$code, 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'active']));
        }
        $exports = app(ErpExportService::class);
        $result = $exports->create($companyId, $connectionId, $journalId, $makerId, $this->uuid(1130));
        if ($result->status === 'draft') {
            $exports->validate($companyId, $result->exportId, $checkerId);
            $exports->execute($companyId, $result->exportId, $makerId);
        }
    }

    private function payrollAdjustment(int $companyId, int $runId, int $actorId): void
    {
        DB::table('payroll_adjustment_reasons')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-OT-CORRECTION'], ['uuid' => $this->uuid(1140), 'name' => 'Điều chỉnh tăng ca synthetic', 'category' => 'earning_correction', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        $reason = $this->requiredId(DB::table('payroll_adjustment_reasons')->where('company_id', $companyId)->where('code', 'LOCAL-OT-CORRECTION')->value('id'), 'adjustment reason');
        $item = $this->requiredId(DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $runId)->orderBy('id')->value('id'), 'payroll item');
        if (! DB::table('payroll_adjustment_requests')->where('company_id', $companyId)->where('idempotency_key', $this->uuid(1141))->exists()) {
            app(PayrollAdjustmentManagementService::class)->create(new CreatePayrollAdjustmentCommand($companyId, $item, $reason, 'earning_correction', '2026-04-05', null, 'Synthetic overtime correction; no external delivery.', null, $this->uuid(1141), $actorId));
        }
    }

    private function payrollAnnualTaxAndGovernment(int $companyId, int $runId, int $makerId, int $checkerId): void
    {
        $employeeId = $this->requiredId(DB::table('payroll_items')->where('company_id', $companyId)->where('payroll_run_id', $runId)->orderBy('id')->value('employee_id'), 'annual tax employee');
        $tax = app(TaxManagementService::class);
        $policy = DB::table('tax_policies')->where('company_id', $companyId)->where('code', 'LOCAL-PIT-DEMO')->value('id');
        $policyId = is_numeric($policy) ? (int) $policy : $tax->savePolicy($companyId, null, ['code' => 'LOCAL-PIT-DEMO', 'name' => 'Thuế TNCN synthetic local', 'version' => 1, 'effective_from' => '2026-01-01', 'effective_to' => null, 'currency_code' => 'VND', 'tax_method' => 'progressive', 'residency_group' => 'resident', 'rounding_rule' => 'half_up', 'floor_taxable_income_at_zero' => true, 'flat_rate_basis_points' => null, 'status' => 'active', 'note' => 'Synthetic governance example only', 'brackets' => [['lower_bound_minor' => 0, 'upper_bound_minor' => null, 'rate_basis_points' => 1000]], 'deductions' => []], $makerId);
        if (! DB::table('employee_tax_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->exists()) {
            $tax->saveProfile($companyId, $employeeId, null, ['tax_policy_id' => $policyId, 'tax_status' => 'taxable', 'residency_status' => 'resident', 'effective_from' => '2026-01-01', 'effective_to' => null, 'tax_identification_number' => 'SYNTHETIC-TAX-ID', 'personal_deduction_eligible' => true, 'withholding_method' => 'policy', 'base_override_minor' => null, 'exemption_reason' => null], $makerId);
        }
        $annual = app(PayrollAnnualTaxReconciliationService::class);
        $existing = DB::table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('idempotency_key', $this->uuid(1150))->value('id');
        $annualId = is_numeric($existing) ? (int) $existing : $annual->create(new CreateAnnualTaxReconciliationCommand($companyId, $employeeId, 2026, 'VN', 'VND', $this->uuid(1150), $makerId))->id;
        $status = DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status');
        if ($status === 'draft') {
            $annual->collect($companyId, $annualId, $makerId);
            app(PayrollAnnualTaxOperationsService::class)->calculate($companyId, $annualId, $makerId);
            app(PayrollAnnualTaxOperationsService::class)->validate($companyId, $annualId, $makerId);
        }
        $lifecycle = app(PayrollAnnualTaxLifecycleService::class);
        foreach ([['validated', 'submit', $makerId], ['submitted', 'review', $checkerId]] as [$from, $action, $actor]) {
            if (DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status') === $from) {
                $lifecycle->transition($companyId, $annualId, $action, $actor);
            }
        }
        $reviewerId = $this->requiredId(User::query()->where('email', 'hr.manager@local.test')->value('id'), 'annual tax reviewer');
        if (DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status') === 'reviewed') {
            $lifecycle->transition($companyId, $annualId, 'approve', $reviewerId);
        }
        if (DB::table('payroll_annual_tax_reconciliations')->where('id', $annualId)->value('status') === 'approved') {
            $lifecycle->transition($companyId, $annualId, 'finalize', $makerId);
        }
        if (! DB::table('government_tax_filings')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $annualId)->exists()) {
            $filings = app(GovernmentTaxFilingService::class);
            $filing = $filings->create($companyId, $annualId, 'annual_pit_employer_declaration', $makerId, $this->uuid(1160));
            $filings->generate($companyId, $filing->filingId, $makerId);
            $filings->validate($companyId, $filing->filingId, $makerId);
            $filings->approve($companyId, $filing->filingId, $checkerId);
        }
    }

    private function payrollMonth(int $companyId, int $month, int $makerId, int $checkerId, PayrollRunStatus $target): void
    {
        $start = new CarbonImmutable(sprintf('2026-%02d-01', $month));
        $end = $month === 8 ? new CarbonImmutable('2026-08-12') : $start->endOfMonth();
        $code = sprintf('LOCAL-2026-%02d', $month);
        $base = 900 + ($month * 10);
        $period = PayrollPeriod::query()->where('company_id', $companyId)->where('code', $code)->first();
        if (! $period instanceof PayrollPeriod) {
            $period = app(PayrollPeriodOperationsService::class)->create($companyId, [
                'code' => $code,
                'name' => 'Kỳ lương synthetic tháng '.sprintf('%02d/2026', $month),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'pay_date' => $end->addDays(3)->toDateString(),
                'status' => 'collecting',
                'note' => 'LOCAL DEMO ONLY — no external payment, ERP, government, or bank traffic.',
            ], $makerId, $this->uuid($base + 1));
        }
        $existingRun = PayrollRun::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->first();
        $snapshot = $existingRun instanceof PayrollRun
            ? PayrollInputSnapshot::query()->where('company_id', $companyId)->find($existingRun->payroll_input_snapshot_id)
            : PayrollInputSnapshot::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->where('idempotency_key', $this->uuid($base + 2))->first();
        if (! $snapshot instanceof PayrollInputSnapshot) {
            $snapshot = app(PayrollInputOperationsService::class)->capture($companyId, $period->id, $makerId, $this->uuid($base + 2), 'Synthetic local workforce snapshot.');
        }
        if ($snapshot->status !== 'ready') {
            $snapshot = app(PayrollInputOperationsService::class)->validate($companyId, $snapshot->id, $makerId, $this->uuid($base + 3));
        }
        if ($snapshot->status !== 'ready') {
            return;
        }
        $run = PayrollRun::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->first();
        if (! $run instanceof PayrollRun) {
            $run = app(PayrollInputOperationsService::class)->createRun($companyId, $snapshot->id, $makerId, $this->uuid($base + 4));
        }
        $run->refresh();
        if ((int) $run->calculation_version === 0) {
            $run = app(PayrollRunService::class)->calculate($companyId, $run->id, $run->version, $this->uuid($base + 5), $makerId);
        }
        $transitions = match ($target) {
            PayrollRunStatus::Submitted => [PayrollRunStatus::Submitted],
            PayrollRunStatus::Approved => [PayrollRunStatus::Submitted, PayrollRunStatus::Approved],
            PayrollRunStatus::Finalized => [PayrollRunStatus::Submitted, PayrollRunStatus::Approved, PayrollRunStatus::Finalized],
            default => [],
        };
        foreach ($transitions as $index => $status) {
            $run->refresh();
            if ($run->status === $status || $run->status === PayrollRunStatus::Finalized) {
                continue;
            }
            if (($status === PayrollRunStatus::Submitted && $run->status !== PayrollRunStatus::Calculated)
                || ($status === PayrollRunStatus::Approved && $run->status !== PayrollRunStatus::Submitted)
                || ($status === PayrollRunStatus::Finalized && $run->status !== PayrollRunStatus::Approved)) {
                continue;
            }
            $actor = $status === PayrollRunStatus::Submitted ? $makerId : $checkerId;
            $run = app(PayrollRunService::class)->transition($companyId, $run->id, $status, $run->version, $this->uuid($base + 6 + $index), $actor);
        }
    }

    private function positionLevels(int $companyId): void
    {
        $levels = ['DEPT-MGR' => 6, 'BE-DEV' => 3, 'FE-DEV' => 3, 'MOB-DEV' => 3, 'DEVOPS-ENG' => 4, 'HR-SPEC' => 3, 'ACCOUNTANT' => 3, 'SALES-EXEC' => 2, 'MKT-SPEC' => 3];
        foreach ($levels as $code => $level) {
            DB::table('job_positions')->where('company_id', $companyId)->where('code', $code)->update(['career_level' => $level]);
        }
    }

    private function actors(int $companyId): void
    {
        foreach ([
            ['hr.manager@local.test', 'HR Manager Demo', 'HR Manager'],
            ['hr.staff@local.test', 'HR Staff Demo', 'HR Admin'],
            ['payroll.manager@local.test', 'Payroll Manager Demo', 'C&B'],
            ['recruiter@local.test', 'Recruiter Demo', 'Recruiter'],
            ['line.manager@local.test', 'Line Manager Demo', 'Manager'],
            ['employee@local.test', 'Employee Demo', 'Employee'],
            ['auditor@local.test', 'Auditor Demo', 'Viewer'],
        ] as [$email, $name, $roleName]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            if (! $user->exists) {
                $user->password = Hash::make('LocalDemoActor123!');
            }
            $user->save();
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $role = Role::findByName($roleName, 'web');
            $user->syncRoles([$role]);
            DB::table('company_user_access')->updateOrInsert(['company_id' => $companyId, 'user_id' => $user->id], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @param list<int> $employeeIds */
    private function workforce(int $companyId, array $employeeIds, int $actorId): void
    {
        DB::table('work_calendars')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-VN-5D'], ['name' => 'Lịch làm việc Việt Nam 5 ngày (Demo)', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 2400, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $calendarId = $this->requiredId(DB::table('work_calendars')->where('company_id', $companyId)->where('code', 'LOCAL-VN-5D')->value('id'), 'work calendar');
        DB::table('work_shifts')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-OFFICE'], ['name' => 'Ca hành chính 08:30–17:30 (Demo)', 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:30:00', 'ends_at' => '17:30:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 5, 'grace_out_minutes' => 5, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'version' => 1, 'rules' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        $shiftId = $this->requiredId(DB::table('work_shifts')->where('company_id', $companyId)->where('code', 'LOCAL-OFFICE')->value('id'), 'work shift');

        foreach ($employeeIds as $index => $employeeId) {
            DB::table('employee_work_profiles')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'effective_from' => '2026-01-01'], ['timezone' => 'Asia/Ho_Chi_Minh', 'work_calendar_id' => $calendarId, 'default_shift_id' => $shiftId, 'weekly_minutes' => 2400, 'daily_minutes' => 480, 'fte_basis_points' => 10000, 'attendance_required' => true, 'overtime_eligible' => true, 'leave_eligible' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            for ($day = 1; $day <= 5; $day++) {
                $date = today()->subDays($day);
                $late = ($index + $day) % 5 === 0 ? 15 : 0;
                $overtime = ($index + $day) % 7 === 0 ? 60 : 0;
                DB::table('attendance_records')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'work_date' => $date->toDateString()], ['work_shift_id' => $shiftId, 'check_in_at' => $date->copy()->setTime(8, 30)->addMinutes($late), 'check_out_at' => $date->copy()->setTime(17, 30)->addMinutes($overtime), 'source' => 'local_demo', 'scheduled_minutes' => 480, 'worked_minutes' => 480 + $overtime - $late, 'break_minutes' => 60, 'late_minutes' => $late, 'early_leave_minutes' => 0, 'overtime_minutes' => $overtime, 'attendance_status' => $late > 0 ? 'late' : 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'anomalies' => json_encode($late > 0 ? ['late_arrival'] : [], JSON_THROW_ON_ERROR), 'worked_hours' => number_format((480 + $overtime - $late) / 60, 2, '.', ''), 'overtime_hours' => number_format($overtime / 60, 2, '.', ''), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        DB::table('leave_types')->updateOrInsert(['company_id' => $companyId, 'code' => 'ANNUAL-DEMO'], ['name' => 'Nghỉ phép năm (Demo)', 'accrual_policy' => json_encode(['method' => 'annual', 'synthetic_demo' => true], JSON_THROW_ON_ERROR), 'carry_forward_policy' => json_encode(['maximum_minutes' => 960], JSON_THROW_ON_ERROR), 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $typeId = $this->requiredId(DB::table('leave_types')->where('company_id', $companyId)->where('code', 'ANNUAL-DEMO')->value('id'), 'leave type');
        DB::table('leave_policies')->updateOrInsert(['company_id' => $companyId, 'code' => 'ANNUAL-DEMO-2026'], ['leave_type_id' => $typeId, 'name' => 'Chính sách phép năm synthetic 2026', 'unit' => 'minutes', 'accrual_method' => 'annual', 'annual_entitlement_minutes' => 5760, 'carry_forward_rule' => json_encode(['synthetic_demo' => true], JSON_THROW_ON_ERROR), 'maximum_carry_forward_minutes' => 960, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'approval_workflow' => json_encode(['levels' => 1], JSON_THROW_ON_ERROR), 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $policyId = $this->requiredId(DB::table('leave_policies')->where('company_id', $companyId)->where('code', 'ANNUAL-DEMO-2026')->value('id'), 'leave policy');
        foreach (array_slice($employeeIds, 0, 6) as $index => $employeeId) {
            DB::table('leave_balances')->updateOrInsert(['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'year' => 2026], ['opening' => 0, 'accrued' => 5760, 'used' => $index * 480, 'adjusted' => 0, 'carried' => 0, 'expired' => 0, 'reserved' => $index < 2 ? 480 : 0, 'remaining' => 5760 - ($index * 480), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $status = ['pending', 'approved', 'rejected'][$index % 3];
            $key = '00000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
            DB::table('leave_requests')->updateOrInsert(['idempotency_key' => $key], ['company_id' => $companyId, 'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'leave_policy_id' => $policyId, 'starts_at' => today()->addDays($index + 3), 'ends_at' => today()->addDays($index + 3), 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'duration_type' => 'full_day', 'days' => 1, 'reason' => 'Synthetic local demo leave request', 'status' => $status, 'version' => 1, 'approved_by' => $status === 'approved' ? $actorId : null, 'submitted_at' => now(), 'approved_at' => $status === 'approved' ? now() : null, 'rejected_at' => $status === 'rejected' ? now() : null, 'reviewed_at' => $status !== 'pending' ? now() : null, 'review_comment' => $status === 'rejected' ? 'Synthetic scheduling conflict' : null, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function notifications(int $companyId, int $recipientId): void
    {
        foreach ([['welcome', 'Welcome to Minh Long Tech', 'unread', null], ['policy', 'Leave policy updated', 'sent', null], ['retry', 'Synthetic channel retry', 'failed', 'Synthetic local channel unavailable']] as $index => [$action, $title, $status, $failure]) {
            $key = 'local-demo-notification-'.$action;
            $payload = ['synthetic_demo' => true, 'category' => $action];
            DB::table('notification_messages')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid($index + 101), 'company_id' => $companyId, 'category' => 'local_demo', 'entity_type' => 'company', 'entity_id' => $companyId, 'action' => $action, 'title' => $title, 'body' => 'SYNTHETIC DEMO DATA — no external delivery.', 'navigation_reference' => 'dashboard', 'safe_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'status' => $status, 'actor_id' => $recipientId, 'created_at' => now(), 'updated_at' => now()]);
            $messageId = $this->requiredId(DB::table('notification_messages')->where('idempotency_key', $key)->value('id'), 'notification message');
            $deliveryKey = $this->uuid($index + 201);
            DB::table('notification_deliveries')->updateOrInsert(['idempotency_key' => $deliveryKey], ['company_id' => $companyId, 'notification_message_id' => $messageId, 'notification_type' => 'local_demo', 'entity_type' => 'company', 'entity_id' => $companyId, 'entity_version' => 1, 'recipient_id' => $recipientId, 'channel' => 'database', 'action' => $action, 'status' => $status === 'failed' ? 'failed' : 'sent', 'attempts' => $status === 'failed' ? 1 : 0, 'sent_at' => $status === 'failed' ? null : now(), 'failed_at' => $status === 'failed' ? now() : null, 'failure_message' => $failure, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('notification_recipients')->updateOrInsert(['notification_message_id' => $messageId, 'user_id' => $recipientId], ['company_id' => $companyId, 'resolution_source' => 'local_demo', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function integration(int $companyId, int $actorId): void
    {
        $configuration = ['adapter' => 'local_null', 'external_http' => false];
        DB::table('integration_connections')->updateOrInsert(['company_id' => $companyId, 'code' => 'LOCAL-NULL'], ['uuid' => $this->uuid(301), 'name' => 'Local Null Adapter (Synthetic)', 'type' => 'generic', 'status' => 'active', 'health_status' => 'healthy', 'base_url' => null, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR), 'credential_reference' => null, 'timeout_seconds' => 1, 'max_attempts' => 3, 'version' => 1, 'checksum' => hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR)), 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        $connectionId = $this->requiredId(DB::table('integration_connections')->where('company_id', $companyId)->where('code', 'LOCAL-NULL')->value('id'), 'integration connection');
        foreach (['queued', 'failed', 'dead_letter'] as $index => $status) {
            $key = $this->uuid($index + 401);
            $payload = ['synthetic_demo' => true, 'sequence' => $index + 1];
            DB::table('integration_outbound_messages')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid($index + 501), 'company_id' => $companyId, 'connection_id' => $connectionId, 'source_type' => 'local_demo', 'source_id' => null, 'message_type' => 'synthetic.demo', 'status' => $status, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'attempt_count' => $index, 'available_at' => now(), 'failure_code' => $status === 'queued' ? null : 'LOCAL_DEMO_FAILURE', 'safe_failure_message' => $status === 'queued' ? null : 'Synthetic failure; no HTTP request was made.', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            if ($status === 'dead_letter') {
                $messageId = $this->requiredId(DB::table('integration_outbound_messages')->where('idempotency_key', $key)->value('id'), 'integration outbound message');
                DB::table('integration_dead_letters')->updateOrInsert(['outbound_message_id' => $messageId], ['company_id' => $companyId, 'status' => 'open', 'reason_code' => 'LOCAL_DEMO_FAILURE', 'safe_reason' => 'Synthetic dead letter; external delivery disabled.', 'replay_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function iam(int $companyId, int $adminId): void
    {
        $subject = User::query()->where('email', 'employee@local.test')->firstOrFail();
        $roleId = $this->requiredId(Role::findByName('Employee', 'web')->id, 'Employee role');
        $key = $this->uuid(601);
        $checksum = hash('sha256', 'local-demo-iam-request');
        DB::table('iam_access_requests')->updateOrInsert(['idempotency_key' => $key], ['uuid' => $this->uuid(602), 'company_id' => $companyId, 'requester_id' => $subject->id, 'subject_user_id' => $subject->id, 'status' => 'requested', 'approval_mode' => 'iam', 'reason' => 'Synthetic local demo access request', 'version' => 1, 'checksum' => $checksum, 'created_at' => now(), 'updated_at' => now()]);
        $requestId = $this->requiredId(DB::table('iam_access_requests')->where('idempotency_key', $key)->value('id'), 'IAM access request');
        DB::table('iam_access_request_items')->updateOrInsert(['access_request_id' => $requestId, 'entitlement_type' => 'role', 'entitlement_id' => $roleId], ['risk_level' => 'standard', 'checksum' => hash('sha256', $checksum.'-item'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('iam_access_reviews')->updateOrInsert(['company_id' => $companyId, 'name' => 'Q3 Synthetic Access Review'], ['uuid' => $this->uuid(603), 'scope_type' => 'company', 'scope_id' => $companyId, 'status' => 'draft', 'due_date' => today()->addMonth(), 'version' => 1, 'snapshot_checksum' => hash('sha256', 'local-demo-access-review'), 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function workflow(int $companyId, int $adminId): void
    {
        $approver = User::query()->where('email', 'hr.manager@local.test')->firstOrFail();
        $definition = DB::table('workflow_definitions')->where('company_id', $companyId)->where('code', 'LOCAL-LEAVE-APPROVAL')->value('id');
        $definitionId = is_numeric($definition) ? (int) $definition : app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData($companyId, $adminId, 'LOCAL-LEAVE-APPROVAL', 'Synthetic Leave Approval', 'LOCAL DEMO ONLY — sequential maker-checker approval.', 'sequential', [
            ['key' => 'manager-review', 'name' => 'Line manager review', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $approver->id, 'due_after_minutes' => 1440],
        ]));
        $status = DB::table('workflow_definitions')->where('id', $definitionId)->value('status');
        if ($status !== 'published') {
            app(WorkflowDefinitionService::class)->publish($companyId, $definitionId, $adminId);
        }
        $existing = DB::table('workflow_instances')->where('company_id', $companyId)->where('idempotency_key', 'local-demo-leave-workflow')->value('id');
        if (! is_numeric($existing)) {
            app(WorkflowExecutionService::class)->start(new StartWorkflowData($companyId, $adminId, $definitionId, 'leave_request', 1, 'local-demo-leave-workflow', ['synthetic_demo' => true]));
        }
    }

    private function uuid(int $suffix): string
    {
        return '00000000-0000-4000-8000-'.str_pad((string) $suffix, 12, '0', STR_PAD_LEFT);
    }

    private function requiredId(mixed $value, string $resource): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new \RuntimeException("Missing numeric identifier for {$resource}.");
        }

        return (int) $value;
    }
}

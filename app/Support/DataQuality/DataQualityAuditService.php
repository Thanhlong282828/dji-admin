<?php

namespace App\Support\DataQuality;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class DataQualityAuditService
{
    /** @return array{rules:int,passed:int,failed:int,warnings:int,duplicates:int,orphans:int,cross_tenant:int,manager_issues:int,invalid_career_levels:int,attendance_issues:int,payroll_issues:int,compensation_issues:int,commerce_issues:int,identity_issues:int,score:int,checksum:string,findings:list<array{key:string,component:string,severity:string,description:string,count:int,status:string}>} */
    public function snapshot(): array
    {
        $findings = [
            new DataQualityFinding('employee.duplicate_code', 'Employee', 'critical', 'Mã nhân viên bị trùng trong cùng công ty.', $this->duplicateEmployeeCodes()),
            new DataQualityFinding('employee.orphan_user', 'Employee/IAM', 'critical', 'Liên kết tài khoản không còn tồn tại.', $this->orphanEmployeeUsers()),
            new DataQualityFinding('employee.missing_company_access', 'Employee/IAM', 'warning', 'Tài khoản liên kết chưa có quyền truy cập công ty.', $this->missingCompanyAccess()),
            new DataQualityFinding('employee.invalid_manager', 'Employee', 'critical', 'Quản lý tự tham chiếu, khác công ty hoặc không tồn tại.', $this->invalidManagers()),
            new DataQualityFinding('assignment.cross_tenant', 'Organization', 'critical', 'Phân công trỏ sang đơn vị hoặc vị trí khác công ty.', $this->crossTenantAssignments()),
            new DataQualityFinding('assignment.position_unit_mismatch', 'Organization', 'critical', 'Phân công dùng vị trí thuộc đơn vị khác.', $this->positionUnitMismatch()),
            new DataQualityFinding('assignment.duplicate_primary', 'Organization', 'critical', 'Nhân viên có nhiều phân công primary đang hiệu lực.', $this->duplicatePrimaryAssignments()),
            new DataQualityFinding('position.invalid_career_level', 'Organization', 'critical', 'Vị trí công việc phải có career level từ 1 đến 6.', $this->invalidCareerLevels()),
            new DataQualityFinding('attendance.orphan_employee', 'Attendance', 'critical', 'Bản ghi chấm công không có nhân viên hợp lệ cùng công ty.', $this->orphanAttendance()),
            new DataQualityFinding('attendance.invalid_time', 'Attendance', 'critical', 'Check-out sớm hơn check-in.', $this->invalidAttendanceTimes()),
            new DataQualityFinding('payroll.orphan_employee', 'Payroll', 'critical', 'Dữ liệu Payroll không có nhân viên hợp lệ cùng công ty.', $this->orphanPayroll()),
            new DataQualityFinding('payroll.snapshot_count_mismatch', 'Payroll', 'critical', 'Số nhân viên của Payroll snapshot không khớp các input đã chụp.', $this->payrollSnapshotCountMismatch()),
            new DataQualityFinding('payroll.run_count_mismatch', 'Payroll', 'critical', 'Số nhân viên của Payroll run không khớp các payroll item đã tính.', $this->payrollRunCountMismatch()),
            new DataQualityFinding('compensation.invalid_money', 'Payroll', 'critical', 'Salary assignment dạng money phải có amount không âm.', $this->invalidCompensationMoney()),
            new DataQualityFinding('compensation.invalid_currency', 'Payroll', 'critical', 'Salary assignment dạng money phải có currency ISO viết hoa hợp lệ.', $this->invalidCompensationCurrency()),
            new DataQualityFinding('compensation.overlap', 'Payroll', 'critical', 'Salary assignment cùng component không được overlap effective period.', $this->overlappingCompensation()),
            new DataQualityFinding('branch.cross_company_access', 'Commerce/Branch', 'critical', 'Branch access phải thuộc company access của cùng user.', $this->crossCompanyBranchAccess()),
            new DataQualityFinding('seller.orphan_owner', 'Commerce', 'critical', 'Seller profile phải có User owner hợp lệ.', $this->orphanSellers()),
            new DataQualityFinding('store.orphan_seller', 'Commerce', 'critical', 'Store phải có Seller owner hợp lệ.', $this->orphanStores()),
            new DataQualityFinding('media.orphan_owner', 'Media', 'critical', 'Media phải trỏ đến Article, Store, Product, Employee hoặc account owner hợp lệ.', $this->orphanMedia()),
            new DataQualityFinding('identity.orphan_user', 'Identity', 'critical', 'External identity phải thuộc User hợp lệ.', $this->orphanExternalIdentities()),
            new DataQualityFinding('identity.duplicate_provider_id', 'Identity', 'critical', 'Provider identity không được liên kết với nhiều User.', $this->duplicateProviderIdentities()),
            new DataQualityFinding('identity.duplicate_verified_phone', 'Identity', 'critical', 'Số điện thoại đã xác minh không được dùng chung.', $this->duplicateVerifiedPhones()),
            new DataQualityFinding('identity.invalid_verified_phone', 'Identity', 'critical', 'Số điện thoại đã xác minh phải ở định dạng E.164 Việt Nam.', $this->invalidVerifiedPhones()),
            new DataQualityFinding('branch.invalid_leader', 'Commerce/Branch', 'critical', 'Lãnh đạo chi nhánh phải là Employee cùng công ty, có account hoạt động và vị trí hiện tại.', $this->invalidBranchLeaders()),
            new DataQualityFinding('branch.leader_missing_access', 'Commerce/Branch', 'critical', 'Lãnh đạo chi nhánh phải có quyền truy cập chi nhánh đó.', $this->branchLeadersMissingAccess()),
            new DataQualityFinding('product.invalid_ownership', 'Commerce/Product', 'critical', 'Product phải thuộc Store/Seller hợp lệ và Variant phải thuộc Product hợp lệ.', $this->invalidProductOwnership()),
            new DataQualityFinding('inventory.invalid_balance', 'Commerce/Inventory', 'critical', 'Inventory phải có on_hand/reserved không âm và reserved không vượt on_hand.', $this->invalidInventoryBalances()),
            new DataQualityFinding('cart.invalid_item', 'Commerce/Cart', 'critical', 'Cart item phải thuộc đúng Product/Variant và có quantity, price snapshot hợp lệ.', $this->invalidCartItems()),
            new DataQualityFinding('wishlist.invalid_item', 'Commerce/Wishlist', 'critical', 'Wishlist item phải trỏ đến Product hợp lệ.', $this->invalidWishlistItems()),
            new DataQualityFinding('buyer_address.invalid_default', 'Commerce/Checkout', 'critical', 'Buyer có địa chỉ phải có đúng một địa chỉ mặc định.', $this->invalidAddressDefaults()),
            new DataQualityFinding('checkout.duplicate_idempotency', 'Commerce/Checkout', 'critical', 'Idempotency key của cùng Buyer không được trùng.', $this->duplicateCheckoutIdempotency()),
            new DataQualityFinding('order.invalid_ownership', 'Commerce/Order', 'critical', 'Order phải thuộc Buyer, Store và Seller owner hợp lệ.', $this->invalidOrderOwnership()),
            new DataQualityFinding('order.total_mismatch', 'Commerce/Order', 'critical', 'Order subtotal/total phải khớp tổng line, shipping và discount.', $this->orderTotalMismatch()),
            new DataQualityFinding('order_item.invalid_line', 'Commerce/Order', 'critical', 'Order item phải có quantity dương và line total khớp snapshot price.', $this->invalidOrderItems()),
            new DataQualityFinding('reservation.invalid_reference', 'Commerce/Inventory', 'critical', 'Reservation phải trỏ đúng Order item/Variant/Balance và có quantity dương.', $this->invalidReservations()),
            new DataQualityFinding('reservation.balance_mismatch', 'Commerce/Inventory', 'critical', 'Tổng reservation active không được vượt inventory reserved.', $this->reservationBalanceMismatch()),
            new DataQualityFinding('reservation.cancelled_active', 'Commerce/Inventory', 'critical', 'Order đã hủy không được còn reservation active.', $this->cancelledActiveReservations()),
            new DataQualityFinding('branch_assignment.cross_tenant', 'HRM/Branch', 'critical', 'Phân công chi nhánh vận hành phải cùng công ty với Employee và Branch.', $this->crossTenantBranchAssignments()),
            new DataQualityFinding('branch_assignment.overlap', 'HRM/Branch', 'critical', 'Employee không được có hai primary Branch assignment chồng thời gian.', $this->overlappingBranchAssignments()),
            new DataQualityFinding('branch_bonus.invalid_scope', 'Payroll/Branch', 'critical', 'Branch bonus phải thuộc đúng Employee, Branch access và company.', $this->invalidBranchBonusScope()),
        ];
        $rows = array_map(static fn (DataQualityFinding $finding): array => $finding->toArray(), $findings);
        $failed = count(array_filter($rows, static fn (array $row): bool => $row['count'] > 0 && $row['severity'] === 'critical'));
        $warnings = count(array_filter($rows, static fn (array $row): bool => $row['count'] > 0 && $row['severity'] === 'warning'));
        $checksum = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));

        return [
            'rules' => count($rows), 'passed' => count($rows) - $failed - $warnings, 'failed' => $failed, 'warnings' => $warnings,
            'duplicates' => $rows[0]['count'] + $rows[6]['count'],
            'orphans' => $rows[1]['count'] + $rows[8]['count'] + $rows[10]['count'],
            'cross_tenant' => $rows[4]['count'], 'manager_issues' => $rows[3]['count'], 'invalid_career_levels' => $rows[7]['count'],
            'attendance_issues' => $rows[8]['count'] + $rows[9]['count'], 'payroll_issues' => $rows[10]['count'] + $rows[11]['count'] + $rows[12]['count'], 'compensation_issues' => $rows[13]['count'] + $rows[14]['count'] + $rows[15]['count'],
            'commerce_issues' => array_sum(array_column(array_slice($rows, 16, 4), 'count')) + array_sum(array_column(array_slice($rows, 24), 'count')),
            'identity_issues' => $rows[20]['count'] + $rows[21]['count'] + $rows[22]['count'] + $rows[23]['count'],
            'score' => max(0, 100 - ($failed * 10) - ($warnings * 3)), 'checksum' => $checksum, 'findings' => $rows,
        ];
    }

    private function duplicateEmployeeCodes(): int
    {
        $duplicates = DB::table('employees')->select(['company_id', 'employee_code'])->whereNull('deleted_at')->groupBy('company_id', 'employee_code')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_employee_codes')->count();
    }

    private function orphanEmployeeUsers(): int
    {
        return DB::table('employees as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->whereNotNull('e.user_id')->whereNull('u.id')->count();
    }

    private function missingCompanyAccess(): int
    {
        return DB::table('employees as e')->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
            $join->on('cua.company_id', '=', 'e.company_id')->on('cua.user_id', '=', 'e.user_id');
        })->whereNotNull('e.user_id')->whereNull('cua.user_id')->count();
    }

    private function invalidManagers(): int
    {
        return DB::table('employees as e')->leftJoin('employees as m', 'm.id', '=', 'e.manager_id')->whereNotNull('e.manager_id')->where(static function (Builder $query): void {
            $query->whereColumn('e.id', 'e.manager_id')->orWhereNull('m.id')->orWhereColumn('m.company_id', '<>', 'e.company_id');
        })->count();
    }

    private function crossTenantAssignments(): int
    {
        return DB::table('employee_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('organization_units as o', 'o.id', '=', 'a.organization_unit_id')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->where(static function (Builder $query): void {
            $query->whereColumn('e.company_id', '<>', 'o.company_id')->orWhereColumn('e.company_id', '<>', 'p.company_id');
        })->count();
    }

    private function duplicatePrimaryAssignments(): int
    {
        $duplicates = DB::table('employee_assignments')->select('employee_id')->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->groupBy('employee_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_primary_assignments')->count();
    }

    private function positionUnitMismatch(): int
    {
        return DB::table('employee_assignments as a')->join('job_positions as p', 'p.id', '=', 'a.job_position_id')->whereNotNull('p.organization_unit_id')->whereColumn('a.organization_unit_id', '<>', 'p.organization_unit_id')->count();
    }

    private function invalidCareerLevels(): int
    {
        return DB::table('job_positions')->where(static fn (Builder $query): Builder => $query->whereNull('career_level')->orWhere('career_level', '<', 1)->orWhere('career_level', '>', 6))->count();
    }

    private function orphanAttendance(): int
    {
        return DB::table('attendance_records as a')->leftJoin('employees as e', 'e.id', '=', 'a.employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'a.company_id'))->count();
    }

    private function invalidAttendanceTimes(): int
    {
        return DB::table('attendance_records')->whereNotNull('check_in_at')->whereNotNull('check_out_at')->whereColumn('check_out_at', '<', 'check_in_at')->count();
    }

    private function orphanPayroll(): int
    {
        return DB::table('payroll_items as p')->leftJoin('employees as e', 'e.id', '=', 'p.employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'p.company_id'))->count();
    }

    private function payrollSnapshotCountMismatch(): int
    {
        $counts = DB::table('payroll_employee_inputs')->selectRaw('payroll_input_snapshot_id, COUNT(*) AS input_count')->groupBy('payroll_input_snapshot_id');

        return DB::table('payroll_input_snapshots as s')->leftJoinSub($counts, 'inputs', 'inputs.payroll_input_snapshot_id', '=', 's.id')->whereRaw('s.employee_count <> COALESCE(inputs.input_count, 0)')->count();
    }

    private function payrollRunCountMismatch(): int
    {
        $counts = DB::table('payroll_items')->selectRaw('payroll_run_id, COUNT(*) AS item_count')->groupBy('payroll_run_id');

        return DB::table('payroll_runs as r')->leftJoinSub($counts, 'items', 'items.payroll_run_id', '=', 'r.id')->where('r.calculation_version', '>', 0)->whereRaw('r.employee_count <> COALESCE(items.item_count, 0)')->count();
    }

    private function invalidCompensationMoney(): int
    {
        $snapshotQuantityKeys = ['weekday_overtime_pay', 'weekend_overtime_pay', 'holiday_overtime_pay', 'unpaid_leave_deduction', 'late_time_deduction'];

        return DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('c.value_type', 'money')->where(static function (Builder $query) use ($snapshotQuantityKeys): void {
            $query->where(static fn (Builder $fixed): Builder => $fixed->where('c.calculation_type', 'fixed')->where(static fn (Builder $amount): Builder => $amount->whereNull('a.amount_minor')->orWhere('a.amount_minor', '<', 0)))
                ->orWhere(static fn (Builder $percentage): Builder => $percentage->where('c.calculation_type', 'percentage')->where(static fn (Builder $rate): Builder => $rate->whereNull('a.percentage_basis_points')->orWhere('a.percentage_basis_points', '<', 0)))
                ->orWhere(static fn (Builder $quantity): Builder => $quantity->where('c.calculation_type', 'quantity_rate')->where(static function (Builder $invalid) use ($snapshotQuantityKeys): void {
                    $invalid->whereNull('a.rate_minor')->orWhere('a.rate_minor', '<', 0)->orWhere(static fn (Builder $missing): Builder => $missing->whereNull('a.quantity_basis_points')->whereNotIn('c.system_key', $snapshotQuantityKeys));
                }));
        })->count();
    }

    private function invalidCompensationCurrency(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('c.value_type', 'money')->where(static fn (Builder $query): Builder => $query->whereNull('a.currency_code')->orWhereRaw("a.currency_code NOT REGEXP '^[A-Z]{3}$'"))->count();
    }

    private function overlappingCompensation(): int
    {
        return DB::table('employee_salary_component_assignments as a')->join('employee_salary_component_assignments as b', static function (JoinClause $join): void {
            $join->on('a.company_id', '=', 'b.company_id')->on('a.employee_id', '=', 'b.employee_id')->on('a.salary_component_id', '=', 'b.salary_component_id')->whereColumn('a.id', '<', 'b.id');
        })->where('a.status', 'active')->where('b.status', 'active')->whereRaw('a.effective_from <= COALESCE(b.effective_to, \'9999-12-31\')')->whereRaw('b.effective_from <= COALESCE(a.effective_to, \'9999-12-31\')')->count();
    }

    private function crossCompanyBranchAccess(): int
    {
        return DB::table('branch_user_access as bua')->join('branches as b', 'b.id', '=', 'bua.branch_id')->leftJoin('company_user_access as cua', static function (JoinClause $join): void {
            $join->on('cua.company_id', '=', 'b.company_id')->on('cua.user_id', '=', 'bua.user_id');
        })->whereNull('cua.user_id')->count();
    }

    private function orphanSellers(): int
    {
        return DB::table('seller_profiles as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')->whereNull('u.id')->count();
    }

    private function orphanStores(): int
    {
        return DB::table('stores as s')->leftJoin('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')->whereNull('sp.id')->count();
    }

    private function orphanMedia(): int
    {
        $owners = ['article' => 'articles', 'store' => 'stores', 'employee' => 'employees', 'user' => 'users', 'seller' => 'seller_profiles', 'product' => 'products'];
        $count = DB::table('media_assets')->whereNotIn('owner_type', array_keys($owners))->count();
        foreach ($owners as $type => $table) {
            $count += DB::table('media_assets as m')->leftJoin($table.' as o', 'o.id', '=', 'm.owner_id')->where('m.owner_type', $type)->whereNull('o.id')->count();
        }

        return $count;
    }

    private function orphanExternalIdentities(): int
    {
        return DB::table('user_external_identities as i')->leftJoin('users as u', 'u.id', '=', 'i.user_id')->whereNull('u.id')->count();
    }

    private function duplicateProviderIdentities(): int
    {
        $duplicates = DB::table('user_external_identities')->select(['provider', 'provider_user_id'])->groupBy('provider', 'provider_user_id')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_external_identities')->count();
    }

    private function duplicateVerifiedPhones(): int
    {
        $duplicates = DB::table('users')->select('phone_e164')->whereNotNull('phone_verified_at')->whereNotNull('phone_e164')->groupBy('phone_e164')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_verified_phones')->count();
    }

    private function invalidVerifiedPhones(): int
    {
        return DB::table('users')->whereNotNull('phone_verified_at')->where(static fn (Builder $query): Builder => $query->whereNull('phone_e164')->orWhereRaw("phone_e164 NOT REGEXP '^\\+84[1-9][0-9]{8,10}$'"))->count();
    }

    private function invalidBranchLeaders(): int
    {
        return DB::table('branches as b')->leftJoin('employees as e', 'e.id', '=', 'b.leader_employee_id')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->leftJoin('employee_assignments as a', static function (JoinClause $join): void {
            $join->on('a.employee_id', '=', 'e.id')->where('a.assignment_type', 'primary')->whereNull('a.effective_to');
        })->whereNotNull('b.leader_employee_id')->where(static fn (Builder $query): Builder => $query->whereNull('e.id')->orWhereColumn('e.company_id', '<>', 'b.company_id')->orWhereNull('u.id')->orWhere('u.access_enabled', false)->orWhereNull('a.id'))->count();
    }

    private function branchLeadersMissingAccess(): int
    {
        return DB::table('branches as b')->join('employees as e', 'e.id', '=', 'b.leader_employee_id')->leftJoin('branch_user_access as bua', static function (JoinClause $join): void {
            $join->on('bua.branch_id', '=', 'b.id')->on('bua.user_id', '=', 'e.user_id');
        })->whereNotNull('b.leader_employee_id')->whereNull('bua.user_id')->count();
    }

    private function invalidProductOwnership(): int
    {
        $products = DB::table('products as p')
            ->leftJoin('stores as s', 's.id', '=', 'p.store_id')
            ->leftJoin('seller_profiles as sp', 'sp.id', '=', 's.seller_profile_id')
            ->where(static fn (Builder $query): Builder => $query->whereNull('s.id')->orWhereNull('sp.id'))
            ->count();
        $variants = DB::table('product_variants as v')->leftJoin('products as p', 'p.id', '=', 'v.product_id')->whereNull('p.id')->count();

        return $products + $variants;
    }

    private function invalidInventoryBalances(): int
    {
        return DB::table('inventory_balances')->where(static fn (Builder $query): Builder => $query
            ->where('on_hand', '<', 0)
            ->orWhere('reserved', '<', 0)
            ->orWhereColumn('reserved', '>', 'on_hand'))
            ->count();
    }

    private function invalidCartItems(): int
    {
        return DB::table('cart_items as ci')
            ->leftJoin('products as p', 'p.id', '=', 'ci.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'ci.product_variant_id')
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('p.id')
                ->orWhereNull('v.id')
                ->orWhereColumn('v.product_id', '<>', 'ci.product_id')
                ->orWhere('ci.quantity', '<', 1)
                ->orWhere('ci.unit_price_minor', '<', 0))
            ->count();
    }

    private function invalidWishlistItems(): int
    {
        return DB::table('wishlist_items as wi')->leftJoin('products as p', 'p.id', '=', 'wi.product_id')->whereNull('p.id')->count();
    }

    private function invalidAddressDefaults(): int
    {
        $invalid = DB::table('buyer_addresses')->select('user_id')->groupBy('user_id')->havingRaw('SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) <> 1');

        return DB::query()->fromSub($invalid, 'invalid_address_defaults')->count();
    }

    private function duplicateCheckoutIdempotency(): int
    {
        $duplicates = DB::table('checkout_sessions')->select(['buyer_user_id', 'idempotency_key'])->groupBy('buyer_user_id', 'idempotency_key')->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($duplicates, 'duplicate_checkout_keys')->count();
    }

    private function invalidOrderOwnership(): int
    {
        return DB::table('orders as o')->leftJoin('users as u', 'u.id', '=', 'o.buyer_user_id')->leftJoin('stores as s', 's.id', '=', 'o.store_id')->leftJoin('seller_profiles as sp', 'sp.id', '=', 'o.seller_profile_id')->where(static fn (Builder $query): Builder => $query->whereNull('u.id')->orWhereNull('s.id')->orWhereNull('sp.id')->orWhereColumn('s.seller_profile_id', '<>', 'o.seller_profile_id'))->count();
    }

    private function orderTotalMismatch(): int
    {
        $lines = DB::table('order_items')->selectRaw('order_id, SUM(line_total_minor) AS line_total')->groupBy('order_id');

        return DB::table('orders as o')->leftJoinSub($lines, 'lines', 'lines.order_id', '=', 'o.id')->whereRaw('o.subtotal_minor <> COALESCE(lines.line_total, 0) OR o.total_minor <> o.subtotal_minor + o.shipping_minor - o.discount_minor')->count();
    }

    private function invalidOrderItems(): int
    {
        return DB::table('order_items as oi')->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')->where(static fn (Builder $query): Builder => $query->whereNull('o.id')->orWhere('oi.quantity', '<', 1)->orWhereRaw('oi.line_total_minor <> oi.unit_price_minor * oi.quantity'))->count();
    }

    private function invalidReservations(): int
    {
        return DB::table('inventory_reservations as r')->leftJoin('order_items as oi', 'oi.id', '=', 'r.order_item_id')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')->leftJoin('inventory_balances as ib', 'ib.id', '=', 'r.inventory_balance_id')->where(static fn (Builder $query): Builder => $query->whereNull('oi.id')->orWhereNull('o.id')->orWhereNull('ib.id')->orWhere('r.quantity', '<', 1)->orWhereColumn('oi.order_id', '<>', 'r.order_id')->orWhereColumn('oi.product_variant_id', '<>', 'r.product_variant_id')->orWhereColumn('ib.product_variant_id', '<>', 'r.product_variant_id'))->count();
    }

    private function reservationBalanceMismatch(): int
    {
        $reserved = DB::table('inventory_reservations')->selectRaw('inventory_balance_id, SUM(quantity) AS quantity')->where('status', 'reserved')->groupBy('inventory_balance_id');

        return DB::table('inventory_balances as ib')->joinSub($reserved, 'r', 'r.inventory_balance_id', '=', 'ib.id')->whereColumn('r.quantity', '>', 'ib.reserved')->count();
    }

    private function cancelledActiveReservations(): int
    {
        return DB::table('inventory_reservations as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('o.status', 'cancelled')->where('r.status', 'reserved')->count();
    }

    private function crossTenantBranchAssignments(): int
    {
        return DB::table('employee_branch_assignments as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('branches as b', 'b.id', '=', 'a.branch_id')->where(static fn (Builder $query): Builder => $query->whereColumn('a.company_id', '<>', 'e.company_id')->orWhereColumn('a.company_id', '<>', 'b.company_id'))->count();
    }

    private function overlappingBranchAssignments(): int
    {
        return DB::table('employee_branch_assignments as a')->join('employee_branch_assignments as b', static function (JoinClause $join): void {
            $join->on('a.company_id', '=', 'b.company_id')->on('a.employee_id', '=', 'b.employee_id')->on('a.assignment_type', '=', 'b.assignment_type')->whereColumn('a.id', '<', 'b.id');
        })->where('a.assignment_type', 'primary')->whereRaw("a.effective_from <= COALESCE(b.effective_to, '9999-12-31')")->whereRaw("b.effective_from <= COALESCE(a.effective_to, '9999-12-31')")->count();
    }

    private function invalidBranchBonusScope(): int
    {
        return DB::table('branch_bonus_proposals as p')->join('branches as b', 'b.id', '=', 'p.branch_id')->join('employees as e', 'e.id', '=', 'p.employee_id')->leftJoin('employee_branch_assignments as a', static function (JoinClause $join): void {
            $join->on('a.branch_id', '=', 'p.branch_id')->on('a.employee_id', '=', 'p.employee_id')->on('a.company_id', '=', 'p.company_id')->where('a.assignment_type', 'primary')->whereColumn('a.effective_from', '<=', 'p.effective_period')->where(static fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereColumn('a.effective_to', '>=', 'p.effective_period'));
        })->where(static fn (Builder $query): Builder => $query->whereColumn('p.company_id', '<>', 'b.company_id')->orWhereColumn('p.company_id', '<>', 'e.company_id')->orWhereNull('a.id'))->count();
    }
}

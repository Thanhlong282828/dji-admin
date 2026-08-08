<?php

namespace Modules\Employee\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\DataConsent;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Models\EmployeeCredential;
use Modules\Employee\Domain\Models\EmployeeDocument;
use Modules\Employee\Domain\Models\EmployeeHistory;
use Modules\Employee\Domain\Models\EmploymentContract;
use Modules\Employee\Domain\Services\EmployeeIdentityService;
use Modules\Organization\Domain\Models\JobPosition;

final class EmployeeDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'SCHEMA-TECH-VN')->first() ?? Company::query()->firstOrFail();
        $profiles = [
            ['E0001', 'Nguyễn Minh Anh', 'Engineering Manager', 'DEPT-MGR', 'nguyen.minh.anh@schema.demo'],
            ['E0002', 'Trần Hoàng Nam', 'Backend Developer', 'BE-DEV', 'tran.hoang.nam@schema.demo'],
            ['E0003', 'Lê Thu Trang', 'Frontend Developer', 'FE-DEV', 'le.thu.trang@schema.demo'],
            ['E0004', 'Phạm Quốc Huy', 'Mobile Developer', 'MOB-DEV', 'pham.quoc.huy@schema.demo'],
            ['E0005', 'Võ Thanh Hà', 'DevOps Engineer', 'DEVOPS-ENG', 'vo.thanh.ha@schema.demo'],
            ['E0006', 'Đặng Ngọc Lan', 'HR Specialist', 'HR-SPEC', 'dang.ngoc.lan@schema.demo'],
            ['E0007', 'Bùi Văn Phúc', 'Accountant', 'ACCOUNTANT', 'bui.van.phuc@schema.demo'],
            ['E0008', 'Hoàng Gia Bảo', 'Sales Executive', 'SALES-EXEC', 'hoang.gia.bao@schema.demo'],
            ['E0009', 'Nguyễn Mỹ Linh', 'Marketing Specialist', 'MKT-SPEC', 'nguyen.my.linh@schema.demo'],
        ];
        $manager = null;
        foreach ($profiles as $index => [$code,$name,$title,$positionCode,$email]) {
            $identity = 'DEMO-ID-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $code],
                ['company_id' => $company->id, 'full_name' => $name, 'work_email' => $email, 'nationality' => 'VN', 'join_date' => today()->subYears(2)->addMonths($index), 'official_date' => today()->subYears(2)->addMonths($index)->addMonths(2), 'employment_status' => 'active', 'employment_type' => 'full_time', 'manager_id' => $index === 0 ? null : $manager?->id, 'identity_number' => $identity, 'identity_number_hash' => app(EmployeeIdentityService::class)->hash($identity)]
            );
            if ($index === 0) {
                $manager = $employee;
            }
            $position = JobPosition::query()->where('company_id', $company->id)->where('code', $positionCode)->first();
            if ($position) {
                EmployeeAssignment::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'assignment_type' => 'primary', 'effective_from' => $employee->join_date],
                    ['organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'manager_id' => $index === 0 ? null : $manager?->id, 'allocation_percent' => 100, 'work_location' => 'TP. Hồ Chí Minh']
                );
            }
            EmployeeCredential::query()->updateOrCreate(['employee_id' => $employee->id, 'type' => 'certificate', 'name' => 'Schema Professional Excellence'], ['issuer' => 'Schema Learning Hub', 'issued_at' => today()->subYear(), 'expires_at' => today()->addYears(2)]);
            EmploymentContract::query()->updateOrCreate(['contract_number' => 'SCHEMA-'.date('Y').'-'.$code], ['employee_id' => $employee->id, 'type' => 'indefinite', 'starts_at' => $employee->official_date, 'base_salary' => 20000000 + ($index * 1000000), 'currency' => 'VND', 'terms' => ['demo' => true, 'notice_period_days' => 30], 'status' => 'active']);
            DataConsent::query()->updateOrCreate(['employee_id' => $employee->id, 'purpose' => 'HR operations', 'policy_version' => '2026.1'], ['granted' => true, 'decided_at' => now(), 'expires_at' => now()->addYears(3)]);
            EmployeeHistory::query()->updateOrCreate(['employee_id' => $employee->id, 'event_type' => 'status_change', 'effective_at' => $employee->join_date], ['before' => null, 'after' => ['employment_status' => 'active'], 'reason' => 'Hoàn tất tiếp nhận nhân sự demo']);
            EmployeeDocument::query()->updateOrCreate(['employee_id' => $employee->id, 'type' => 'profile', 'name' => 'Hồ sơ nhân sự', 'version' => 1], ['storage_key' => 'demo/employee/'.$employee->uuid.'/profile.pdf', 'checksum' => hash('sha256', $employee->uuid.'-profile'), 'access_policy' => ['private' => true, 'roles' => ['HR Admin']]]);
        }
        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser && $manager && Employee::query()->where('user_id', $firstUser)->doesntExist()) {
            $manager->update(['user_id' => $firstUser]);
        }
    }
}

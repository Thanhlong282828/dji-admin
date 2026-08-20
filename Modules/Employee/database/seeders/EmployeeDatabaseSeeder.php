<?php

namespace Modules\Employee\Database\Seeders;

use Carbon\CarbonImmutable;
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
        $additionalNames = ['Đỗ Minh Khang', 'Hồ Ngọc Ánh', 'Dương Quốc Việt', 'Vũ Thanh Tâm', 'Mai Hoàng Long', 'Ngô Thu Hương', 'Lý Đức Minh', 'Tạ Phương Thảo', 'Trịnh Gia Hân', 'Cao Tuấn Kiệt', 'Đinh Bảo Ngọc', 'Huỳnh Anh Khoa', 'Phan Quỳnh Chi', 'Lâm Nhật Nam', 'Quách Minh Thư', 'Châu Hoàng Phúc', 'Tô Khánh An', 'Kiều Gia Linh', 'Hà Quốc Trung', 'La Mỹ Duyên', 'Chu Minh Đức', 'Lương Thanh Vân', 'Thái Anh Tú', 'Ninh Thuỳ Dương', 'Tăng Gia Huy', 'Mạc Bảo Trâm', 'Lưu Đức Thịnh'];
        $positionCycle = [['Backend Developer', 'BE-DEV'], ['Frontend Developer', 'FE-DEV'], ['Mobile Developer', 'MOB-DEV'], ['QA Engineer', 'BE-DEV'], ['DevOps Engineer', 'DEVOPS-ENG'], ['HR Specialist', 'HR-SPEC'], ['Accountant', 'ACCOUNTANT'], ['Sales Executive', 'SALES-EXEC'], ['Marketing Specialist', 'MKT-SPEC']];
        foreach ($additionalNames as $offset => $name) {
            [$title, $position] = $positionCycle[$offset % count($positionCycle)];
            $number = $offset + 10;
            $profiles[] = ['E'.str_pad((string) $number, 4, '0', STR_PAD_LEFT), $name, $title, $position, 'employee.'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'@example.test'];
        }
        $manager = null;
        foreach ($profiles as $index => [$code,$name,$title,$positionCode,$email]) {
            $identity = 'DEMO-ID-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $status = match ($index % 12) {
                9 => 'probation',
                10 => 'leave',
                11 => 'terminated',
                default => 'active',
            };
            $joinDate = CarbonImmutable::parse('2023-01-15')->addMonths($index % 24);
            $officialDate = $joinDate->copy()->addMonths(2);
            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $code],
                ['company_id' => $company->id, 'full_name' => $name, 'work_email' => $email, 'nationality' => 'VN', 'join_date' => $joinDate, 'official_date' => $officialDate, 'termination_date' => $status === 'terminated' ? today()->subMonths(2) : null, 'employment_status' => $status, 'employment_type' => $index % 15 === 14 ? 'part_time' : 'full_time', 'manager_id' => $index === 0 ? null : $manager?->id, 'identity_number' => $identity, 'identity_number_hash' => app(EmployeeIdentityService::class)->hash($identity)]
            );
            if ($index === 0) {
                $manager = $employee;
            }
            $position = JobPosition::query()->where('company_id', $company->id)->where('code', $positionCode)->first();
            if ($position) {
                $canonicalAssignment = EmployeeAssignment::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'assignment_type' => 'primary', 'effective_from' => $joinDate->toDateString()],
                    ['organization_unit_id' => $position->organization_unit_id, 'job_position_id' => $position->id, 'manager_id' => $index === 0 ? null : $manager?->id, 'allocation_percent' => 100, 'work_location' => 'TP. Hồ Chí Minh']
                );
                // The local demo previously derived join dates from today(), which
                // created overlapping primary rows on each yearly bootstrap. Keep
                // referenced history but close only synthetic employee overlaps.
                EmployeeAssignment::query()->where('employee_id', $employee->id)->where('assignment_type', 'primary')->whereKeyNot($canonicalAssignment->id)->where(static fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $joinDate->toDateString()))->update(['effective_to' => $joinDate->subDay()->toDateString()]);
            }
            EmployeeCredential::query()->updateOrCreate(['employee_id' => $employee->id, 'type' => 'certificate', 'name' => 'Schema Professional Excellence'], ['issuer' => 'Schema Learning Hub', 'issued_at' => today()->subYear(), 'expires_at' => today()->addYears(2)]);
            $contractType = $status === 'probation' ? 'probation' : ($index % 4 === 0 ? 'fixed_term' : 'indefinite');
            $baseSalary = match ($title) {
                'Engineering Manager' => 65000000,
                'DevOps Engineer' => 42000000,
                'Backend Developer', 'Frontend Developer', 'Mobile Developer', 'QA Engineer' => 18000000 + (($index % 4) * 7000000),
                default => 16000000 + (($index % 5) * 3000000),
            };
            EmploymentContract::query()->updateOrCreate(['contract_number' => 'SCHEMA-'.date('Y').'-'.$code], ['employee_id' => $employee->id, 'type' => $contractType, 'starts_at' => $contractType === 'probation' ? $employee->join_date : $employee->official_date, 'ends_at' => $contractType === 'fixed_term' ? $officialDate->copy()->addYear() : ($status === 'terminated' ? today()->subMonths(2) : null), 'base_salary' => $baseSalary, 'currency' => 'VND', 'terms' => ['demo' => true, 'notice_period_days' => 30], 'status' => $status === 'terminated' ? 'terminated' : 'active']);
            DataConsent::query()->updateOrCreate(['employee_id' => $employee->id, 'purpose' => 'HR operations', 'policy_version' => '2026.1'], ['granted' => true, 'decided_at' => now(), 'expires_at' => now()->addYears(3)]);
            EmployeeHistory::query()->updateOrCreate(['employee_id' => $employee->id, 'event_type' => 'status_change', 'effective_at' => $employee->join_date], ['before' => null, 'after' => ['employment_status' => $status], 'reason' => 'Sự kiện vòng đời nhân sự synthetic demo']);
            EmployeeDocument::query()->updateOrCreate(['employee_id' => $employee->id, 'type' => 'profile', 'name' => 'Hồ sơ nhân sự', 'version' => 1], ['storage_key' => 'demo/employee/'.$employee->uuid.'/profile.pdf', 'checksum' => hash('sha256', $employee->uuid.'-profile'), 'access_policy' => ['private' => true, 'roles' => ['HR Admin']]]);
        }
        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser && $manager && Employee::query()->where('user_id', $firstUser)->doesntExist()) {
            $manager->update(['user_id' => $firstUser]);
        }
    }
}

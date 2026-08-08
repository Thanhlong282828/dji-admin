<?php

namespace Modules\Organization\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Models\Company;
use Modules\Organization\Domain\Models\CostCenter;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;

final class OrganizationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'SCHEMA-TECH-VN')->firstOrFail();
        foreach (User::query()->pluck('id') as $userId) {
            DB::table('company_user_access')->updateOrInsert(['company_id' => $company->id, 'user_id' => $userId], ['updated_at' => now(), 'created_at' => now()]);
        }$centers = [];
        foreach ([['OPS', 'Vận hành', 800000000], ['TECH', 'Công nghệ', 2500000000], ['HR', 'Nhân sự', 600000000], ['SALES', 'Kinh doanh', 1800000000], ['FIN', 'Tài chính', 500000000]] as [$code,$name,$budget]) {
            $centers[$code] = CostCenter::query()->updateOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $name, 'annual_budget' => $budget, 'currency' => 'VND']);
        }$root = $this->unit($company->id, null, $centers['OPS']->id, 'HQ', 'Trụ sở chính', 'company', 200);
        $tech = $this->unit($company->id, $root->id, $centers['TECH']->id, 'TECH-BLOCK', 'Khối Công nghệ', 'division', 90);
        $ops = $this->unit($company->id, $root->id, $centers['OPS']->id, 'OPS-BLOCK', 'Khối Vận hành', 'division', 45);
        $sales = $this->unit($company->id, $root->id, $centers['SALES']->id, 'SALES-BLOCK', 'Khối Kinh doanh', 'division', 55);
        $units = [];
        foreach ([[$tech, 'BACKEND', 'Phòng Backend', 'TECH'], [$tech, 'FRONTEND', 'Phòng Frontend', 'TECH'], [$tech, 'MOBILE', 'Phòng Mobile', 'TECH'], [$tech, 'DEVOPS', 'Phòng DevOps', 'TECH'], [$ops, 'HR-DEPT', 'Phòng Nhân sự', 'HR'], [$ops, 'FIN-DEPT', 'Phòng Tài chính', 'FIN'], [$sales, 'SALES-DEPT', 'Phòng Sales', 'SALES'], [$sales, 'MARKETING', 'Phòng Marketing', 'SALES']] as [$parent,$code,$name,$center]) {
            $units[$code] = $this->unit($company->id, $parent->id, $centers[$center]->id, $code, $name, 'department', 20);
        }foreach ([['BE-DEV', 'Backend Developer', 'BACKEND', 12, 450000000, false], ['FE-DEV', 'Frontend Developer', 'FRONTEND', 10, 380000000, false], ['MOB-DEV', 'Mobile Developer', 'MOBILE', 8, 340000000, false], ['DEVOPS-ENG', 'DevOps Engineer', 'DEVOPS', 6, 300000000, true], ['HR-SPEC', 'HR Specialist', 'HR-DEPT', 5, 150000000, false], ['ACCOUNTANT', 'Accountant', 'FIN-DEPT', 5, 160000000, false], ['SALES-EXEC', 'Sales Executive', 'SALES-DEPT', 15, 420000000, false], ['MKT-SPEC', 'Marketing Specialist', 'MARKETING', 8, 260000000, false], ['DEPT-MGR', 'Department Manager', 'BACKEND', 4, 280000000, true]] as [$code,$title,$unit,$headcount,$cost,$critical]) {
            JobPosition::query()->updateOrCreate(['company_id' => $company->id, 'code' => $code], ['organization_unit_id' => $units[$unit]->id, 'title' => $title, 'job_grade' => $critical ? 'M2' : 'S2', 'approved_headcount' => $headcount, 'budgeted_cost' => $cost, 'competency_requirements' => [['name' => 'Năng lực chuyên môn', 'level' => $critical ? 5 : 3]], 'is_critical' => $critical]);
        }
    }

    private function unit(int $companyId, ?int $parentId, int $costCenterId, string $code, string $name, string $type, int $limit): OrganizationUnit
    {
        return OrganizationUnit::query()->updateOrCreate(['company_id' => $companyId, 'code' => $code], ['parent_id' => $parentId, 'cost_center_id' => $costCenterId, 'name' => $name, 'type' => $type, 'headcount_limit' => $limit, 'effective_from' => '2026-01-01', 'effective_to' => null, 'metadata' => ['demo_data' => true]]);
    }
}

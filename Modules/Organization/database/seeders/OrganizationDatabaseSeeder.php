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
        }
        $centers = $this->centers($company->id);
        $root = $this->unit($company->id, null, $centers['OPS']->id, 'HQ', 'Trụ sở chính', 'company', 200);
        $tech = $this->unit($company->id, $root->id, $centers['TECH']->id, 'TECH-BLOCK', 'Khối Công nghệ', 'division', 90);
        $ops = $this->unit($company->id, $root->id, $centers['OPS']->id, 'OPS-BLOCK', 'Khối Vận hành', 'division', 45);
        $sales = $this->unit($company->id, $root->id, $centers['SALES']->id, 'SALES-BLOCK', 'Khối Kinh doanh', 'division', 55);
        $units = $this->departments($company->id, $tech, $ops, $sales, $centers);
        foreach ([['BE-DEV', 'Backend Developer', 'BACKEND', 12, 450000000, false, 'S2', 3], ['FE-DEV', 'Frontend Developer', 'FRONTEND', 10, 380000000, false, 'S2', 3], ['MOB-DEV', 'Mobile Developer', 'MOBILE', 8, 340000000, false, 'S2', 3], ['DEVOPS-ENG', 'DevOps Engineer', 'DEVOPS', 6, 300000000, true, 'M2', 5], ['HR-SPEC', 'HR Specialist', 'HR-DEPT', 5, 150000000, false, 'S2', 3], ['ACCOUNTANT', 'Accountant', 'FIN-DEPT', 5, 160000000, false, 'S2', 3], ['SALES-EXEC', 'Sales Executive', 'SALES-DEPT', 15, 420000000, false, 'S2', 3], ['MKT-SPEC', 'Marketing Specialist', 'MARKETING', 8, 260000000, false, 'S2', 3], ['DEPT-MGR', 'Department Manager', 'BACKEND', 4, 280000000, true, 'M2', 5]] as [$code,$title,$unit,$headcount,$cost,$critical,$grade,$level]) {
            JobPosition::query()->updateOrCreate(['company_id' => $company->id, 'code' => $code], ['organization_unit_id' => $units[$unit]->id, 'title' => $title, 'job_grade' => $grade, 'approved_headcount' => $headcount, 'budgeted_cost' => $cost, 'competency_requirements' => [['name' => 'Năng lực chuyên môn', 'level' => $level]], 'is_critical' => $critical]);
        }
    }

    /** @return array{OPS:CostCenter,TECH:CostCenter,HR:CostCenter,SALES:CostCenter,FIN:CostCenter} */
    private function centers(int $companyId): array
    {
        return [
            'OPS' => CostCenter::query()->updateOrCreate(['company_id' => $companyId, 'code' => 'OPS'], ['name' => 'Vận hành', 'annual_budget' => 800000000, 'currency' => 'VND']),
            'TECH' => CostCenter::query()->updateOrCreate(['company_id' => $companyId, 'code' => 'TECH'], ['name' => 'Công nghệ', 'annual_budget' => 2500000000, 'currency' => 'VND']),
            'HR' => CostCenter::query()->updateOrCreate(['company_id' => $companyId, 'code' => 'HR'], ['name' => 'Nhân sự', 'annual_budget' => 600000000, 'currency' => 'VND']),
            'SALES' => CostCenter::query()->updateOrCreate(['company_id' => $companyId, 'code' => 'SALES'], ['name' => 'Kinh doanh', 'annual_budget' => 1800000000, 'currency' => 'VND']),
            'FIN' => CostCenter::query()->updateOrCreate(['company_id' => $companyId, 'code' => 'FIN'], ['name' => 'Tài chính', 'annual_budget' => 500000000, 'currency' => 'VND']),
        ];
    }

    /**
     * @param  array{OPS:CostCenter,TECH:CostCenter,HR:CostCenter,SALES:CostCenter,FIN:CostCenter}  $centers
     * @return array{BACKEND:OrganizationUnit,FRONTEND:OrganizationUnit,MOBILE:OrganizationUnit,DEVOPS:OrganizationUnit,HR-DEPT:OrganizationUnit,FIN-DEPT:OrganizationUnit,SALES-DEPT:OrganizationUnit,MARKETING:OrganizationUnit}
     */
    private function departments(int $companyId, OrganizationUnit $tech, OrganizationUnit $ops, OrganizationUnit $sales, array $centers): array
    {
        return [
            'BACKEND' => $this->unit($companyId, $tech->id, $centers['TECH']->id, 'BACKEND', 'Phòng Backend', 'department', 20),
            'FRONTEND' => $this->unit($companyId, $tech->id, $centers['TECH']->id, 'FRONTEND', 'Phòng Frontend', 'department', 20),
            'MOBILE' => $this->unit($companyId, $tech->id, $centers['TECH']->id, 'MOBILE', 'Phòng Mobile', 'department', 20),
            'DEVOPS' => $this->unit($companyId, $tech->id, $centers['TECH']->id, 'DEVOPS', 'Phòng DevOps', 'department', 20),
            'HR-DEPT' => $this->unit($companyId, $ops->id, $centers['HR']->id, 'HR-DEPT', 'Phòng Nhân sự', 'department', 20),
            'FIN-DEPT' => $this->unit($companyId, $ops->id, $centers['FIN']->id, 'FIN-DEPT', 'Phòng Tài chính', 'department', 20),
            'SALES-DEPT' => $this->unit($companyId, $sales->id, $centers['SALES']->id, 'SALES-DEPT', 'Phòng Sales', 'department', 20),
            'MARKETING' => $this->unit($companyId, $sales->id, $centers['SALES']->id, 'MARKETING', 'Phòng Marketing', 'department', 20),
        ];
    }

    private function unit(int $companyId, ?int $parentId, int $costCenterId, string $code, string $name, string $type, int $limit): OrganizationUnit
    {
        return OrganizationUnit::query()->updateOrCreate(['company_id' => $companyId, 'code' => $code], ['parent_id' => $parentId, 'cost_center_id' => $costCenterId, 'name' => $name, 'type' => $type, 'headcount_limit' => $limit, 'effective_from' => '2026-01-01', 'effective_to' => null, 'metadata' => ['demo_data' => true]]);
    }
}

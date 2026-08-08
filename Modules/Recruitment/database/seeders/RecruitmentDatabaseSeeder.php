<?php

namespace Modules\Recruitment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;

final class RecruitmentDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $requester = Employee::query()->where('employee_code', 'E0001')->firstOrFail();
        $requests = [['BE-DEV', 2, 600000000], ['MOB-DEV', 2, 520000000], ['DEVOPS-ENG', 1, 360000000], ['HR-SPEC', 1, 240000000], ['SALES-EXEC', 3, 450000000]];
        $requisitions = [];
        foreach ($requests as [$code,$vacancies,$budget]) {
            $position = JobPosition::query()->where('company_id', $requester->company_id)->where('code', $code)->firstOrFail();
            $requisitions[] = RecruitmentRequisition::query()->updateOrCreate(['job_position_id' => $position->id, 'requested_by' => $requester->id], ['vacancies' => $vacancies, 'budget' => $budget, 'status' => 'open', 'approval_context' => ['demo' => true, 'approved_by' => 'System Admin'], 'target_date' => today()->addMonths(2)]);
        }
        $names = [['Nguyễn Anh Tuấn', 'nguyen.anh.tuan@example.test', 'LinkedIn'], ['Trần Minh Châu', 'tran.minh.chau@example.test', 'Referral'], ['Lê Quốc Bảo', 'le.quoc.bao@example.test', 'Website'], ['Phạm Thảo Vy', 'pham.thao.vy@example.test', 'LinkedIn'], ['Võ Thành Công', 'vo.thanh.cong@example.test', 'Community'], ['Đặng Ngọc Mai', 'dang.ngoc.mai@example.test', 'Referral'], ['Bùi Gia Hưng', 'bui.gia.hung@example.test', 'Website'], ['Hoàng Khánh Linh', 'hoang.khanh.linh@example.test', 'LinkedIn']];
        $stages = ['received', 'screening', 'test', 'interview', 'reference', 'offer', 'hired', 'rejected'];
        foreach ($names as $i => [$name,$email,$source]) {
            $candidate = Candidate::query()->updateOrCreate(['email' => $email], ['full_name' => $name, 'phone' => '+8490000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'source' => $source, 'profile_fingerprint' => hash('sha256', $email), 'profile' => ['demo' => true, 'headline' => 'Ứng viên tiềm năng Schema'], 'consent' => true]);
            Application::query()->updateOrCreate(['recruitment_requisition_id' => $requisitions[$i % count($requisitions)]->id, 'candidate_id' => $candidate->id], ['stage' => $stages[$i], 'score' => 70 + $i * 3, 'evaluations' => [['type' => 'screening', 'overall_score' => 70 + $i * 3, 'recommendation' => $i === 7 ? 'reject' : 'proceed']], 'stage_changed_at' => now()->subDays(8 - $i)]);
        }
    }
}

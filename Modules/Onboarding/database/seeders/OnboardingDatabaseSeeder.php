<?php

namespace Modules\Onboarding\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Employee\Domain\Models\Employee;
use Modules\Onboarding\Domain\Models\OnboardingCase;

final class OnboardingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()->orderBy('id')->limit(4)->get();
        $statuses = ['preboarding', 'in_progress', 'probation_review', 'completed'];
        $buddy = Employee::query()->where('employee_code', 'E0001')->first();
        foreach ($employees as $i => $employee) {
            OnboardingCase::query()->updateOrCreate(['employee_id' => $employee->id, 'start_date' => $employee->join_date], ['buddy_id' => $buddy && $buddy->id !== $employee->id ? $buddy->id : null, 'probation_review_date' => $employee->join_date->copy()->addMonths(2), 'checklist' => [['key' => 'documents', 'label' => 'Hoàn thiện hồ sơ', 'category' => 'hr', 'required' => true, 'status' => $i === 0 ? 'pending' : 'completed', 'due_at' => $employee->join_date->toDateString()], ['key' => 'orientation', 'label' => 'Đào tạo hội nhập', 'category' => 'learning', 'required' => true, 'status' => $i < 2 ? 'pending' : 'completed', 'due_at' => $employee->join_date->copy()->addDays(7)->toDateString()]], 'provisioning' => [['key' => 'email', 'label' => 'Email doanh nghiệp', 'type' => 'account', 'status' => $i === 0 ? 'pending' : 'completed'], ['key' => 'laptop', 'label' => 'Thiết bị làm việc', 'type' => 'asset', 'status' => $i < 2 ? 'pending' : 'completed']], 'status' => $statuses[$i]]);
        }
    }
}

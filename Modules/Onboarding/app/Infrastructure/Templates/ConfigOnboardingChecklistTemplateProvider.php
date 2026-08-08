<?php

namespace Modules\Onboarding\Infrastructure\Templates;

use Modules\Onboarding\Application\DTOs\OnboardingChecklistTemplateItem;
use Modules\Onboarding\Domain\Services\OnboardingChecklistTemplateProviderInterface;

final class ConfigOnboardingChecklistTemplateProvider implements OnboardingChecklistTemplateProviderInterface
{
    public function items(?string $templateCode, string $employmentType): array
    {
        return [
            new OnboardingChecklistTemplateItem('verify_personal_information', 'Xác minh thông tin cá nhân', null, 'hr', 'hr', -7, true, 10),
            new OnboardingChecklistTemplateItem('collect_required_documents', 'Thu thập hồ sơ bắt buộc', null, 'hr', 'employee', -5, true, 20, ['verify_personal_information']),
            new OnboardingChecklistTemplateItem('sign_employment_contract', 'Ký hợp đồng lao động', null, 'contract', 'hr', -1, true, 30),
            new OnboardingChecklistTemplateItem('create_user_account', 'Tạo tài khoản người dùng', null, 'it', 'it', -3, true, 40),
            new OnboardingChecklistTemplateItem('assign_equipment', 'Bàn giao thiết bị', null, 'facilities', 'it', 0, true, 50),
            new OnboardingChecklistTemplateItem('orientation_schedule', 'Lịch đào tạo hội nhập', null, 'learning', 'hr', 1, true, 60),
            new OnboardingChecklistTemplateItem('manager_welcome', 'Quản lý chào đón nhân viên', null, 'manager', 'manager', 0, true, 70),
            new OnboardingChecklistTemplateItem('policy_acknowledgement', 'Xác nhận chính sách', null, 'compliance', 'employee', 3, true, 80),
            new OnboardingChecklistTemplateItem('payroll_information', 'Cập nhật thông tin trả lương', null, 'finance', 'finance', 2, true, 90),
            new OnboardingChecklistTemplateItem('emergency_contact', 'Cập nhật liên hệ khẩn cấp', null, 'hr', 'employee', 3, false, 100),
        ];
    }
}

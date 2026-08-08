<?php

namespace Modules\Onboarding\Infrastructure\Templates;

use Modules\Onboarding\Application\DTOs\OnboardingProvisioningTemplateItem;
use Modules\Onboarding\Domain\Services\OnboardingProvisioningTemplateProviderInterface;

final class ConfigOnboardingProvisioningTemplateProvider implements OnboardingProvisioningTemplateProviderInterface
{
    public function items(?string $templateCode, string $employmentType): array
    {
        return [
            new OnboardingProvisioningTemplateItem('user_account', 'Tài khoản HRM', 'account', 'internal', 'internal', 'it', -3, true),
            new OnboardingProvisioningTemplateItem('email_account', 'Email doanh nghiệp', 'email', 'external', 'not_connected', 'it', -3, true),
            new OnboardingProvisioningTemplateItem('hrm_access', 'Quyền truy cập HRM', 'access', 'internal', 'internal', 'it', -1, true),
            new OnboardingProvisioningTemplateItem('laptop', 'Máy tính làm việc', 'equipment', 'manual', 'manual', 'it', 0, true),
            new OnboardingProvisioningTemplateItem('vpn', 'VPN', 'access', 'external', 'not_connected', 'it', 0, false),
            new OnboardingProvisioningTemplateItem('office_access', 'Quyền ra vào văn phòng', 'access', 'manual', 'manual', 'facilities', 0, true),
            new OnboardingProvisioningTemplateItem('payroll_profile', 'Hồ sơ trả lương', 'finance', 'internal', 'internal', 'finance', 2, true),
            new OnboardingProvisioningTemplateItem('benefit_enrollment', 'Hồ sơ phúc lợi', 'benefit', 'manual', 'manual', 'hr', 5, false),
        ];
    }
}

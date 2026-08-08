<?php

namespace Modules\Onboarding\Domain\Services;

use Modules\Onboarding\Application\DTOs\OnboardingCaseData;
use Modules\Onboarding\Application\DTOs\OnboardingCaseResult;
use Modules\Onboarding\Domain\Models\OnboardingCase;

final readonly class OnboardingCaseCreationService
{
    public function __construct(
        private OnboardingChecklistTemplateProviderInterface $checklists,
        private OnboardingProvisioningTemplateProviderInterface $provisioning,
    ) {}

    public function create(OnboardingCaseData $data): OnboardingCaseResult
    {
        $case = OnboardingCase::query()->create([
            'company_id' => $data->companyId,
            'employee_id' => $data->employeeId,
            'application_id' => $data->applicationId,
            'offer_id' => $data->offerId,
            'requisition_id' => $data->requisitionId,
            'job_position_id' => $data->jobPositionId,
            'organization_unit_id' => $data->organizationUnitId,
            'owner_user_id' => $data->ownerUserId,
            'start_date' => $data->startDate->toDateString(),
            'status' => 'preboarding',
            'checklist' => [],
            'provisioning' => [],
            'template_code' => $data->templateCode ?? 'default',
            'created_by' => $data->actorId,
            'version' => 1,
        ]);
        $checklistCount = 0;
        foreach ($this->checklists->items($data->templateCode, $data->employmentType) as $item) {
            $case->checklistItems()->create([
                'company_id' => $data->companyId,
                'category' => $item->category,
                'code' => $item->code,
                'title' => $item->title,
                'description' => $item->description,
                'is_required' => $item->required,
                'due_at' => $data->startDate->addDays($item->dueOffsetDays),
                'status' => 'pending',
                'sort_order' => $item->sortOrder,
                'metadata' => ['owner_type' => $item->ownerType, 'dependency_codes' => $item->dependencies],
            ]);
            $checklistCount++;
        }
        $provisioningCount = 0;
        foreach ($this->provisioning->items($data->templateCode, $data->employmentType) as $item) {
            $case->provisioningItems()->create([
                'company_id' => $data->companyId,
                'code' => $item->code,
                'type' => $item->type,
                'title' => $item->title,
                'provider' => $item->provider,
                'provider_status' => $item->providerStatus,
                'is_required' => $item->required,
                'status' => 'pending',
                'due_at' => $data->startDate->addDays($item->dueOffsetDays),
                'metadata' => ['owner_type' => $item->ownerType],
            ]);
            $provisioningCount++;
        }

        return new OnboardingCaseResult((int) $case->id, $checklistCount, $provisioningCount, 'preboarding');
    }
}

<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Recruitment\Application\DTOs\HireCandidateData;
use Modules\Recruitment\Domain\Enums\ApplicationStage;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;

final class HireCandidateEligibilityValidator
{
    public function validate(
        int $companyId,
        CandidateOffer $offer,
        Application $application,
        RecruitmentRequisition $requisition,
        JobPosition $position,
        OrganizationUnit $unit,
        HireCandidateData $data,
    ): void {
        if ($offer->status !== CandidateOfferStatus::Accepted || $offer->accepted_at === null || $offer->reservation_status !== 'reserved') {
            throw ValidationException::withMessages(['offer' => 'Chỉ offer đã được chấp nhận và đang giữ reservation mới có thể hire.']);
        }
        if ($offer->version !== $data->expectedOfferVersion || $application->version !== $data->expectedApplicationVersion) {
            throw ValidationException::withMessages(['version' => 'Offer hoặc application đã thay đổi. Vui lòng tải lại trước khi hire.']);
        }
        if ($application->stage !== ApplicationStage::Offer) {
            throw ValidationException::withMessages(['application' => 'Application phải ở giai đoạn offer trước khi hire.']);
        }
        if ((int) $offer->application_id !== (int) $application->id || (int) $application->recruitment_requisition_id !== (int) $requisition->id) {
            throw ValidationException::withMessages(['offer' => 'Chuỗi offer/application/requisition không hợp lệ.']);
        }
        if ((int) $offer->company_id !== $companyId || (int) $position->company_id !== $companyId || (int) $unit->company_id !== $companyId) {
            throw ValidationException::withMessages(['company_id' => 'Dữ liệu hire không thuộc company hiện tại.']);
        }
        if ((int) $offer->job_position_id !== (int) $position->id || (int) $offer->organization_unit_id !== (int) $unit->id || (int) $position->organization_unit_id !== (int) $unit->id) {
            throw ValidationException::withMessages(['job_position_id' => 'Vị trí và đơn vị tổ chức không khớp với offer.']);
        }
        if ($data->jobPositionId !== (int) $position->id || $data->organizationUnitId !== (int) $unit->id) {
            throw ValidationException::withMessages(['job_position_id' => 'Không được thay đổi vị trí hoặc đơn vị đã duyệt trên offer.']);
        }
        if ($data->employmentType->value !== $offer->employment_type) {
            throw ValidationException::withMessages(['employment_type' => 'Loại hình làm việc phải khớp offer đã duyệt.']);
        }
        if ($requisition->filled_hires >= $requisition->vacancies) {
            throw ValidationException::withMessages(['requisition' => 'Requisition không còn vacancy để hoàn tất hire.']);
        }
        if ($data->contractEndDate !== null && $data->contractEndDate->isBefore($data->startDate)) {
            throw ValidationException::withMessages(['contract_end_date' => 'Ngày kết thúc hợp đồng không được trước ngày bắt đầu.']);
        }
    }
}

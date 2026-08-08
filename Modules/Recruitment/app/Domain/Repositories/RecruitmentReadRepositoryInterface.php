<?php

namespace Modules\Recruitment\Domain\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;

interface RecruitmentReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RecruitmentRequisition>
     */
    public function requisitions(int $companyId, array $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Candidate>
     */
    public function candidates(int $companyId, array $filters): LengthAwarePaginator;

    /** @return array<string, list<array<string, mixed>>> */
    public function pipeline(int $companyId, ?int $requisitionId): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   open_requisitions: int,
     *   pending_approval: int,
     *   total_vacancies: int,
     *   applications: int,
     *   screening: int,
     *   interview: int,
     *   offers: int,
     *   hired: int,
     *   rejected: int,
     *   stage_counts: array<string, int>
     * }
     */
    public function dashboard(int $companyId, array $filters): array;
}

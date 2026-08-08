<?php

namespace Modules\Recruitment\Domain\Repositories;

interface RecruitmentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createRequisition(int $companyId, array $data): array;

    /** @return array<string, mixed> */
    public function transitionRequisition(int $companyId, int $id, string $status, int $actorId, ?string $reason): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCandidate(int $companyId, array $data): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createApplication(int $companyId, array $data, int $actorId): array;

    /** @return array<string, mixed> */
    public function moveApplication(int $companyId, int $id, string $stage, int $actorId, ?int $expectedVersion, ?string $reason): array;
}

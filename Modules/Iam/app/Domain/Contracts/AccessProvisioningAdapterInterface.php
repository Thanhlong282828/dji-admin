<?php

namespace Modules\Iam\Domain\Contracts;

interface AccessProvisioningAdapterInterface
{
    public function provision(int $companyId, int $userId, string $type, int $entitlementId): void;

    public function revoke(int $companyId, int $userId, string $type, int $entitlementId): void;
}

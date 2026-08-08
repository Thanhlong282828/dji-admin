<?php

namespace Modules\Workflow\Domain\Contracts;

interface WorkflowActorResolverInterface
{
    /**
     * @param  array<string, scalar|null>  $context
     * @return list<int>
     */
    public function resolve(int $companyId, string $type, string $value, string $subjectType, int $subjectId, array $context): array;
}

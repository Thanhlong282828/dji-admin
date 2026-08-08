<?php

namespace Modules\Workflow\Application\DTOs;

final readonly class WorkflowDefinitionData
{
    /** @param list<array{key:string,name:string,mode:string,resolver_type:string,resolver_value:string,condition?:array<string,scalar|null>,due_after_minutes?:int|null,escalation_after_minutes?:int|null,fallback_resolver_type?:string|null,fallback_resolver_value?:string|null}> $steps */
    public function __construct(public int $companyId, public int $actorId, public string $code, public string $name, public ?string $description, public string $routingType, public array $steps) {}
}

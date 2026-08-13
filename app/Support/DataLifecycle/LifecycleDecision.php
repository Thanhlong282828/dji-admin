<?php

namespace App\Support\DataLifecycle;

final readonly class LifecycleDecision
{
    public function __construct(public RetentionDecision $decision, public string $reason, public string $policyKey, public int $policyVersion) {}

    /** @return array{decision:string,reason:string,policy_key:string,policy_version:int} */
    public function toArray(): array
    {
        return ['decision' => $this->decision->value, 'reason' => $this->reason, 'policy_key' => $this->policyKey, 'policy_version' => $this->policyVersion];
    }
}

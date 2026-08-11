<?php

namespace App\Support\RuntimeConfiguration;

final readonly class RuntimeConfigurationEntry
{
    public function __construct(
        public FeatureFlag $flag,
        public bool $enabled,
        public string $source,
        public string $owner,
        public string $risk,
        public string $failureMode,
        public string $removalCondition,
        public string $driftStatus = 'clean',
    ) {}

    /** @return array{key:string,enabled:bool,source:string,owner:string,risk:string,failure_mode:string,removal_condition:string,drift_status:string} */
    public function toArray(): array
    {
        return ['key' => $this->flag->value, 'enabled' => $this->enabled, 'source' => $this->source, 'owner' => $this->owner, 'risk' => $this->risk, 'failure_mode' => $this->failureMode, 'removal_condition' => $this->removalCondition, 'drift_status' => $this->driftStatus];
    }
}

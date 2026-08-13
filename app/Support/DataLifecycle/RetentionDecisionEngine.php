<?php

namespace App\Support\DataLifecycle;

use DateTimeImmutable;

final class RetentionDecisionEngine
{
    public function decide(LifecycleResource $resource, RetentionPolicy $policy, ?LegalHold $hold, DateTimeImmutable $at): LifecycleDecision
    {
        if ($hold !== null && $policy->legalHoldEligible && $hold->activeAt($at)) {
            return new LifecycleDecision(RetentionDecision::Hold, 'Active legal hold overrides retention and disposal.', $policy->key, $policy->version);
        }
        if ($resource->state === LifecycleState::Active) {
            return new LifecycleDecision(RetentionDecision::KeepActive, 'Resource remains in active business use.', $policy->key, $policy->version);
        }
        if ($resource->activeDependency) {
            return new LifecycleDecision(RetentionDecision::Blocked, 'An active business or referential dependency blocks disposal.', $policy->key, $policy->version);
        }
        if ($policy->retentionDays === null) {
            return new LifecycleDecision(RetentionDecision::Blocked, 'No approved retention duration is configured.', $policy->key, $policy->version);
        }
        $eligibleAt = $resource->referenceDate->modify('+'.$policy->retentionDays.' days');
        if ($eligibleAt > $at) {
            return new LifecycleDecision(RetentionDecision::KeepActive, 'Configured retention window has not elapsed.', $policy->key, $policy->version);
        }
        if ($resource->integrityHistoryRequired || $policy->archiveBehavior !== 'none') {
            return new LifecycleDecision(RetentionDecision::ArchiveEligible, 'Retention elapsed; archive/integrity evidence is required before disposal.', $policy->key, $policy->version);
        }
        if (! $resource->ownedAdapterAvailable) {
            return new LifecycleDecision(RetentionDecision::Blocked, 'Owning-domain disposal adapter is unavailable.', $policy->key, $policy->version);
        }

        return new LifecycleDecision(RetentionDecision::DisposalEligible, 'Retention elapsed with no hold or dependency; dry-run only.', $policy->key, $policy->version);
    }
}

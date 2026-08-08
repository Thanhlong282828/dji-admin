<?php

namespace Modules\Employee\Application\DTOs;

final readonly class HRGovernanceDashboardViewModel
{
    /**
     * @param  list<array<string, scalar|null>>  $issues
     * @param  list<array<string, scalar|null>>  $audits
     * @param  list<array<string, scalar|null>>  $consents
     * @param  list<array<string, scalar|null>>  $documents
     */
    public function __construct(
        public int $qualityScore,
        public int $openIssues,
        public int $blockingIssues,
        public int $consentCompliance,
        public int $documentCompliance,
        public array $issues,
        public array $audits,
        public array $consents,
        public array $documents,
    ) {}
}

<?php

namespace App\Support\Release;

final readonly class ReleaseCertificateData
{
    /**
     * @param  array<string, bool|int|float|string>  $gateResults
     * @param  array<string, bool|int|float|string>  $healthResults
     */
    public function __construct(
        public string $releaseId,
        public string $manifestChecksum,
        public string $gitCommit,
        public string $imageDigest,
        public int $migrationCount,
        public string $migrationInventoryChecksum,
        public string $environment,
        public string $startedAt,
        public string $completedAt,
        public array $gateResults,
        public array $healthResults,
        public string $rollbackTarget,
        public bool $rollbackCompatible,
        public string $qualityReference,
        public string $browserResult,
    ) {}
}

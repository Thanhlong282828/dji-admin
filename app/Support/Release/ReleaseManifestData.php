<?php

namespace App\Support\Release;

final readonly class ReleaseManifestData
{
    /** @param array<string, string> $qualityGates */
    public function __construct(
        public string $releaseId,
        public string $gitCommit,
        public string $gitTag,
        public string $imageReference,
        public string $imageDigest,
        public string $createdAt,
        public string $environment,
        public int $migrationCount,
        public string $applicationVersion,
        public string $phpVersion,
        public string $laravelVersion,
        public array $qualityGates,
        public string $approvalReference,
        public string $rollbackTarget,
    ) {}
}

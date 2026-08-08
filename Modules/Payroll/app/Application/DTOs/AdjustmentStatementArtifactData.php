<?php

namespace Modules\Payroll\Application\DTOs;

use DateTimeImmutable;

final readonly class AdjustmentStatementArtifactData
{
    public function __construct(
        public int $statementId,
        public ?string $disk,
        public ?string $path,
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $fileSize,
        public ?string $checksum,
        public ?string $snapshotChecksum,
        public int $generationVersion,
        public ?int $generatedBy,
        public ?DateTimeImmutable $generatedAt,
    ) {}
}

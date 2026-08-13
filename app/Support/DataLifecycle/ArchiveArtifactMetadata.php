<?php

namespace App\Support\DataLifecycle;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ArchiveArtifactMetadata
{
    public function __construct(public string $reference, public string $policyKey, public string $sourceRange, public string $checksum, public int $size, public string $contentType, public DateTimeImmutable $createdAt)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1 || $size < 0 || ! str_contains($contentType, '/')) {
            throw new InvalidArgumentException('Invalid archive integrity metadata.');
        }
    }

    /** @return array{reference:string,policy_key:string,source_range:string,checksum:string,size:int,content_type:string,created_at:string} */
    public function evidence(): array
    {
        return ['reference' => $this->reference, 'policy_key' => $this->policyKey, 'source_range' => $this->sourceRange, 'checksum' => $this->checksum, 'size' => $this->size, 'content_type' => $this->contentType, 'created_at' => $this->createdAt->format(DATE_ATOM)];
    }
}

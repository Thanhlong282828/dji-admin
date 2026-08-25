<?php

namespace App\Support\Media;

final readonly class StoredMedia
{
    public function __construct(
        public string $provider,
        public string $assetId,
        public ?string $deliveryUrl,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksum,
    ) {}
}

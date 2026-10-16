<?php

namespace App\Support\DataQuality;

interface StagingTrustEvidenceProvider
{
    /**
     * @return array{
     *   approval:array<string,mixed>,approval_sha256:string,
     *   release:array<string,mixed>,release_identity_sha256:string,
     *   restore:array<string,mixed>,restore_receipt_sha256:string
     * }
     */
    public function evidence(): array;
}

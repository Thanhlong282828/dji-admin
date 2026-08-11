<?php

namespace Modules\Audit\Domain\Services;

use App\Support\DataProtection\DataFieldRegistry;
use App\Support\DataProtection\ProtectedPayloadSanitizer;

final class AuditRedactionService
{
    public function __construct(private ?ProtectedPayloadSanitizer $sanitizer = null) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        /** @var array<string, mixed> $safe */
        $safe = ($this->sanitizer ?? new ProtectedPayloadSanitizer(new DataFieldRegistry))->sanitize($payload);

        return $safe;
    }
}

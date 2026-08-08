<?php

namespace Modules\Iam\Domain\Services;

final class IamChecksum
{
    /** @param array<string,mixed> $payload */
    public static function make(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

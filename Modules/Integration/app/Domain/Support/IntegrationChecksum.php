<?php

namespace Modules\Integration\Domain\Support;

final class IntegrationChecksum
{
    /** @param array<string,mixed> $value */
    public static function make(array $value): string
    {
        ksort($value);

        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}

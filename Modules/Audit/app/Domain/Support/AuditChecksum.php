<?php

namespace Modules\Audit\Domain\Support;

final class AuditChecksum
{
    /** @param array<string, mixed> $payload */
    public static function make(array $payload): string
    {
        return hash('sha256', self::canonical($payload));
    }

    /** @param array<string, mixed> $payload */
    private static function canonical(array $payload): string
    {
        ksort($payload);
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::normalize($value);
            }
        }

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}

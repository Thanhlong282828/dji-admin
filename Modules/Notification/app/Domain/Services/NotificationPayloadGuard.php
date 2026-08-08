<?php

namespace Modules\Notification\Domain\Services;

use Illuminate\Validation\ValidationException;

final class NotificationPayloadGuard
{
    /** @param array<string, scalar|null> $payload */
    public function assertSafe(array $payload): void
    {
        $forbidden = ['salary', 'tax', 'bank', 'identity', 'cccd', 'passport', 'storage_path', 'signed_url', 'token', 'medical', 'private_note'];
        foreach (array_keys($payload) as $key) {
            foreach ($forbidden as $needle) {
                if (str_contains(strtolower($key), $needle)) {
                    throw ValidationException::withMessages(['payload' => "Restricted notification field: {$key}"]);
                }
            }
        }
    }
}

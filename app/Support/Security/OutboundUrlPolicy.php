<?php

namespace App\Support\Security;

use InvalidArgumentException;

final class OutboundUrlPolicy
{
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if ($scheme !== 'https' || ! is_string($host) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            $this->reject();
        }

        $normalizedHost = trim(strtolower(rtrim($host, '.')), '[]');
        if ($normalizedHost === 'localhost' || str_ends_with($normalizedHost, '.localhost') || str_ends_with($normalizedHost, '.local')) {
            $this->reject();
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false && filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $this->reject();
        }
    }

    private function reject(): never
    {
        throw new InvalidArgumentException('The endpoint must use HTTPS and target an approved public network address.');
    }
}

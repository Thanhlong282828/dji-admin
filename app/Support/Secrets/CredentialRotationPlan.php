<?php

namespace App\Support\Secrets;

use LogicException;

final class CredentialRotationPlan
{
    private string $state = 'active';

    public function begin(): void
    {
        $this->transition('active', 'rotating');
    }

    public function verifyCandidate(): void
    {
        $this->transition('rotating', 'rotated');
    }

    public function retirePrevious(): void
    {
        $this->transition('rotated', 'retired');
    }

    public function revoke(): void
    {
        $this->state = 'revoked';
    }

    public function state(): string
    {
        return $this->state;
    }

    private function transition(string $from, string $to): void
    {
        if ($this->state !== $from) {
            throw new LogicException('Invalid credential rotation transition.');
        }
        $this->state = $to;
    }
}

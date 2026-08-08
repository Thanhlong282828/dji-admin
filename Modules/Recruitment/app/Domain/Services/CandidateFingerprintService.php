<?php

namespace Modules\Recruitment\Domain\Services;

final class CandidateFingerprintService
{
    public function make(string $name, string $email, ?string $phone): string
    {
        return hash('sha256', mb_strtolower(trim($name)).'|'.mb_strtolower(trim($email)).'|'.preg_replace('/\D+/', '', $phone ?? ''));
    }
}

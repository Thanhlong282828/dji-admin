<?php

namespace App\Support\DataProtection;

final readonly class DataFieldDefinition
{
    public function __construct(
        public string $domain,
        public string $field,
        public DataClassification $classification,
        public string $owner,
        public ProtectionDecision $displayPolicy,
        public ProtectionDecision $loggingPolicy,
        public ProtectionDecision $exportPolicy,
        public string $maskPolicy,
        public string $encryptionExpectation,
        public string $retentionExpectation,
    ) {}
}

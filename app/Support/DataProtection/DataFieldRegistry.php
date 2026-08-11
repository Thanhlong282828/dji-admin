<?php

namespace App\Support\DataProtection;

final class DataFieldRegistry
{
    /** @return list<DataFieldDefinition> */
    public function entries(): array
    {
        return [
            $this->confidential('Employee', 'email', 'Employee Core', 'email'),
            $this->confidential('Employee', 'phone', 'Employee Core', 'phone'),
            $this->confidential('Employee', 'address', 'Employee Core', 'generic'),
            $this->confidential('Employee', 'birthday', 'Employee Core', 'generic'),
            $this->restricted('Employee', 'government_id', 'Employee Core', 'last4', 'application encryption'),
            $this->restricted('Employee', 'national_id', 'Employee Core', 'last4', 'application encryption'),
            $this->restricted('Employee', 'identity_document', 'Employee Core', 'deny', 'private encrypted storage'),
            $this->restricted('Employee', 'storage_path', 'Document Governance', 'deny', 'private storage reference'),
            $this->restricted('Payroll', 'salary', 'Payroll', 'deny', 'domain-controlled encryption'),
            $this->restricted('Payroll', 'tax_number', 'Payroll', 'last4', 'domain-controlled encryption'),
            $this->restricted('Payroll', 'bank_account', 'Payroll', 'last4', 'application encryption'),
            $this->restricted('Payroll', 'account_number', 'Payroll', 'last4', 'application encryption'),
            $this->restricted('Payroll', 'insurance_number', 'Payroll', 'last4', 'domain-controlled encryption'),
            $this->restricted('Integration', 'credential', 'Integration Hub', 'deny', 'application encryption'),
            $this->restricted('Security', 'token', 'Owning security domain', 'deny', 'hash or provider storage'),
            $this->restricted('Security', 'private_key', 'Secrets Governance', 'deny', 'external secret provider'),
            $this->restricted('Security', 'password', 'Authentication', 'deny', 'one-way password hash'),
            $this->restricted('Security', 'authorization', 'Authentication', 'deny', 'request-bound only'),
            $this->restricted('Security', 'cookie', 'Authentication', 'deny', 'encrypted transport/session storage'),
            $this->restricted('Security', 'session_id', 'Authentication', 'deny', 'session store'),
            $this->restricted('Security', 'api_key', 'Secrets Governance', 'deny', 'external secret provider'),
            $this->restricted('Security', 'secret', 'Secrets Governance', 'deny', 'external secret provider'),
            $this->restricted('Document', 'private_path', 'Document Governance', 'deny', 'private storage reference'),
        ];
    }

    public function find(string $field): ?DataFieldDefinition
    {
        $normalized = $this->normalize($field);
        foreach ($this->entries() as $entry) {
            if ($normalized === $entry->field || str_ends_with($normalized, '_'.$entry->field)) {
                return $entry;
            }
        }

        return null;
    }

    public function checksum(): string
    {
        $entries = array_map(static fn (DataFieldDefinition $entry): array => [
            'domain' => $entry->domain,
            'field' => $entry->field,
            'classification' => $entry->classification->value,
            'owner' => $entry->owner,
            'display' => $entry->displayPolicy->value,
            'logging' => $entry->loggingPolicy->value,
            'export' => $entry->exportPolicy->value,
            'mask' => $entry->maskPolicy,
            'encryption' => $entry->encryptionExpectation,
            'retention' => $entry->retentionExpectation,
        ], $this->entries());

        return hash('sha256', json_encode($entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function confidential(string $domain, string $field, string $owner, string $mask): DataFieldDefinition
    {
        return new DataFieldDefinition($domain, $field, DataClassification::Confidential, $owner, ProtectionDecision::Mask, ProtectionDecision::Redact, ProtectionDecision::Deny, $mask, 'access control', 'owner policy required');
    }

    private function restricted(string $domain, string $field, string $owner, string $mask, string $encryption): DataFieldDefinition
    {
        return new DataFieldDefinition($domain, $field, DataClassification::Restricted, $owner, ProtectionDecision::Deny, ProtectionDecision::Redact, ProtectionDecision::Deny, $mask, $encryption, 'legal/owner policy required');
    }

    private function normalize(string $field): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace(['-', '.'], '_', $field)));
    }
}

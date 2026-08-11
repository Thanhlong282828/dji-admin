<?php

namespace App\Support\DataProtection;

final class DataProtectionPolicyService
{
    public function decide(DataFieldDefinition $field, DataSurface $surface, bool $authorized = false, bool $purposeAllowed = false): ProtectionDecision
    {
        if (in_array($surface, [DataSurface::Log, DataSurface::Audit], true)) {
            return ProtectionDecision::Redact;
        }
        if (in_array($surface, [DataSurface::Integration, DataSurface::Notification], true)) {
            return $authorized && $purposeAllowed ? ProtectionDecision::Allow : ProtectionDecision::Deny;
        }
        if ($surface === DataSurface::Export) {
            return $authorized && $purposeAllowed ? $field->exportPolicy : ProtectionDecision::Deny;
        }
        if ($field->classification === DataClassification::Restricted) {
            return $authorized && $purposeAllowed ? ProtectionDecision::Allow : ProtectionDecision::Deny;
        }

        return $authorized && $purposeAllowed ? ProtectionDecision::Allow : $field->displayPolicy;
    }
}

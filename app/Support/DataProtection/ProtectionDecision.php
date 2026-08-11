<?php

namespace App\Support\DataProtection;

enum ProtectionDecision: string
{
    case Allow = 'allow';
    case Mask = 'mask';
    case Redact = 'redact';
    case Deny = 'deny';
}

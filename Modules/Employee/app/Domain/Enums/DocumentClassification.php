<?php

namespace Modules\Employee\Domain\Enums;

enum DocumentClassification: string
{
    case Identity = 'identity';
    case Contract = 'contract';
    case Certificate = 'certificate';
    case Tax = 'tax';
    case Bank = 'bank';
    case Medical = 'medical';
    case Consent = 'consent';
    case Performance = 'performance';
    case Other = 'other';
}

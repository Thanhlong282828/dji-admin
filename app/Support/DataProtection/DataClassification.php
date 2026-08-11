<?php

namespace App\Support\DataProtection;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
}

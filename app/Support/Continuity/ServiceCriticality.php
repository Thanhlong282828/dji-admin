<?php

namespace App\Support\Continuity;

enum ServiceCriticality: string
{
    case Tier0 = 'tier_0_critical';
    case Tier1 = 'tier_1_operational';
    case Tier2 = 'tier_2_degradable';
}

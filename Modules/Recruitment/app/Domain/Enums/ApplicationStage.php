<?php

namespace Modules\Recruitment\Domain\Enums;

enum ApplicationStage: string
{
    case Received = 'received';
    case Screening = 'screening';
    case Test = 'test';
    case Interview = 'interview';
    case Reference = 'reference';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
}

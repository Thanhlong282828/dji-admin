<?php

namespace Modules\Employee\Domain\Enums;

enum CredentialType: string
{
    case Identity = 'identity';
    case Degree = 'degree';
    case ProfessionalQualification = 'professional_qualification';
    case Other = 'other';
    case Education = 'education';
    case Certificate = 'certificate';
    case Language = 'language';
    case Skill = 'skill';
    case License = 'license';
    case Passport = 'passport';
    case WorkPermit = 'work_permit';
    case Award = 'award';
}

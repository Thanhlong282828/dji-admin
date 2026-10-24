<?php

namespace App\Support\DataQuality;

interface StagingDatabaseIdentityProvider
{
    /** @return array{server_uuid:string,grants:list<string>} */
    public function identity(): array;
}

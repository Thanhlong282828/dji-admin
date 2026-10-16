<?php

namespace App\Support\DataQuality;

interface StagingRuntimeIdentityProvider
{
    /** @return array{project_id:string,instance_id:string,networks:list<string>,subnets:list<string>,public_ips:list<string>} */
    public function identity(): array;
}

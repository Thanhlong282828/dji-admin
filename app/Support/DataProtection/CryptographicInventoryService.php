<?php

namespace App\Support\DataProtection;

final class CryptographicInventoryService
{
    /** @return list<array{purpose:string,mechanism:string,data:string,key_dependency:string,rotation_impact:string}> */
    public function entries(): array
    {
        return [
            ['purpose' => 'User authentication', 'mechanism' => 'Laravel password hashing', 'data' => 'password verifier', 'key_dependency' => 'none', 'rotation_impact' => 'rehash on login'],
            ['purpose' => 'Payload integrity', 'mechanism' => 'SHA-256 canonical checksum', 'data' => 'immutable metadata', 'key_dependency' => 'none', 'rotation_impact' => 'none'],
            ['purpose' => 'Webhook integrity', 'mechanism' => 'HMAC-SHA-256', 'data' => 'webhook payload', 'key_dependency' => 'provider secret reference', 'rotation_impact' => 'dual-secret validation window'],
            ['purpose' => 'Credential protection', 'mechanism' => 'Laravel encryption', 'data' => 'integration credential', 'key_dependency' => 'APP_KEY', 'rotation_impact' => 'old-key decrypt then re-encrypt before retirement'],
            ['purpose' => 'Private downloads', 'mechanism' => 'authorization plus SHA-256 artifact checksum', 'data' => 'private artifact', 'key_dependency' => 'application authorization', 'rotation_impact' => 'verify access and artifact integrity'],
        ];
    }
}

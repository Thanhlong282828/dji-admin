<?php

namespace App\Support\DataQuality;

use Illuminate\Database\DatabaseManager;
use RuntimeException;

final readonly class PdoStagingDatabaseIdentityProvider implements StagingDatabaseIdentityProvider
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{server_uuid:string,grants:list<string>} */
    public function identity(): array
    {
        $pdo = $this->database->connection()->getPdo();
        $uuidStatement = $pdo->query('SELECT @@server_uuid');
        $serverUuid = $uuidStatement === false ? false : $uuidStatement->fetchColumn();
        if (! is_string($serverUuid) || trim($serverUuid) === '') {
            throw new RuntimeException('Actual MySQL server UUID is unavailable.');
        }

        $grantStatement = $pdo->query('SHOW GRANTS FOR CURRENT_USER()');
        if ($grantStatement === false) {
            throw new RuntimeException('Current database grants are unavailable.');
        }
        $grants = [];
        while (($grant = $grantStatement->fetchColumn()) !== false) {
            if (is_string($grant)) {
                $grants[] = $grant;
            }
        }

        return ['server_uuid' => strtolower(trim($serverUuid)), 'grants' => $grants];
    }
}

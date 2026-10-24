<?php

namespace Tests\Unit\Operations;

use App\Support\DataQuality\RootOwnedStagingTrustEvidenceProvider;
use App\Support\DataQuality\StagingDatabaseIdentityProvider;
use App\Support\DataQuality\StagingRestoreAuditAuthorizer;
use App\Support\DataQuality\StagingRuntimeIdentityProvider;
use App\Support\DataQuality\StagingTrustEvidenceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class StagingRestoreAuditAuthorizerTest extends TestCase
{
    private const STAGING_UUID = 'd51f8476-c8ca-11f1-90af-0242ac120002';

    private const PRODUCTION_UUID = 'baf57f7f-b152-11f1-aa55-42010a400002';

    private const RELEASE_SHA = '43f297f9facea706ccb77d16f7bf4724c33a3356';

    private const BACKUP_SHA = '26b6a22cfa8e0c8c32df5fe52b7377ccbcb5a2c8a76fb1a4932d36d488e1f6b8';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('data-quality.staging_restore.database', 'laraveldji_staging_restore');
    }

    public function test_trusted_evidence_authorizes_only_the_bound_read_only_staging_runtime(): void
    {
        $result = $this->authorizer()->authorize($this->scope(), 'laraveldji_staging_restore');

        self::assertSame(self::STAGING_UUID, $result['server_uuid']);
        self::assertSame(self::RELEASE_SHA, $result['release_sha']);
        self::assertTrue($result['public_ip_absent']);
        self::assertTrue($result['database_read_only']);
    }

    /** @return iterable<string,array{array<string,mixed>,string}> */
    public static function scopeFailures(): iterable
    {
        yield 'production environment rejected' => [['environment' => 'production'], 'APP_ENV=staging'];
        yield 'wrong actual database rejected' => [['actual_database' => 'schema_admin'], 'identities must match'];
        yield 'database mismatch rejected' => [['database_matches' => false], 'identities must match'];
        yield 'unknown purpose rejected' => [['database_purpose' => 'UNKNOWN'], 'identities must match'];
    }

    /** @param array<string,mixed> $override */
    #[DataProvider('scopeFailures')]
    public function test_scope_mismatch_is_rejected(array $override, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->authorizer()->authorize(array_replace($this->scope(), $override), 'laraveldji_staging_restore');
    }

    public function test_server_uuid_mismatch_and_production_uuid_are_rejected(): void
    {
        foreach (['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', self::PRODUCTION_UUID] as $uuid) {
            try {
                $this->authorizer(database: ['server_uuid' => $uuid, 'grants' => $this->readOnlyGrants()])
                    ->authorize($this->scope(), 'laraveldji_staging_restore');
                self::fail('Unsafe server UUID must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('UUID mismatch or production UUID', $exception->getMessage());
            }
        }
    }

    public function test_expired_approval_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expired');

        $this->authorizer(evidence: $this->evidence(approval: ['expires_at' => '2020-01-01T00:00:00Z']))
            ->authorize($this->scope(), 'laraveldji_staging_restore');
    }

    public function test_network_mismatch_is_rejected(): void
    {
        $runtime = $this->runtime();
        $runtime['networks'] = ['projects/wrong/global/networks/default'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('network');

        $this->authorizer(runtime: $runtime)->authorize($this->scope(), 'laraveldji_staging_restore');
    }

    public function test_any_public_ip_across_multiple_interfaces_is_rejected(): void
    {
        $runtime = $this->runtime();
        $runtime['networks'][] = 'projects/1/global/networks/secondary';
        $runtime['subnets'][] = 'projects/1/regions/asia-southeast1/subnetworks/secondary';
        sort($runtime['networks']);
        sort($runtime['subnets']);
        $runtime['public_ips'] = ['203.0.113.10'];
        $evidence = $this->evidence(approval: ['gcp' => [
            'networks' => $runtime['networks'],
            'subnets' => $runtime['subnets'],
        ]]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no public IP');

        $this->authorizer(runtime: $runtime, evidence: $evidence)
            ->authorize($this->scope(), 'laraveldji_staging_restore');
    }

    public function test_write_capable_database_grant_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('read-only grants');

        $this->authorizer(database: [
            'server_uuid' => self::STAGING_UUID,
            'grants' => ['GRANT SELECT, INSERT ON `laraveldji_staging_restore`.* TO `audit`@`%`'],
        ])->authorize($this->scope(), 'laraveldji_staging_restore');
    }

    public function test_approval_cannot_spoof_release_or_backup_evidence(): void
    {
        foreach ([['release_sha' => str_repeat('a', 40)], ['backup_artifact_sha256' => str_repeat('b', 64)]] as $override) {
            try {
                $this->authorizer(evidence: $this->evidence(approval: $override))
                    ->authorize($this->scope(), 'laraveldji_staging_restore');
                self::fail('Spoofed approval evidence must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('does not match', $exception->getMessage());
            }
        }
    }

    public function test_unverified_release_and_restore_receipts_are_rejected(): void
    {
        foreach ([
            $this->evidence(release: ['immutable' => false]),
            $this->evidence(restore: ['restore_verified' => false]),
        ] as $evidence) {
            try {
                $this->authorizer(evidence: $evidence)->authorize($this->scope(), 'laraveldji_staging_restore');
                self::fail('Unverified independent evidence must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('does not match', $exception->getMessage());
            }
        }
    }

    public function test_wrong_or_application_owned_trust_anchor_is_rejected(): void
    {
        $directory = sys_get_temp_dir().'/dq-untrusted-'.bin2hex(random_bytes(5));
        self::assertTrue(mkdir($directory, 0700));
        $paths = [];
        foreach (['approval', 'release', 'restore'] as $name) {
            $path = $directory.'/'.$name.'.json';
            file_put_contents($path, '{}');
            $paths[] = $path;
        }
        $provider = new RootOwnedStagingTrustEvidenceProvider(...$paths);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('root-owned');

        $provider->evidence();
    }

    public function test_parent_directory_symlink_is_rejected(): void
    {
        $base = sys_get_temp_dir().'/dq-parent-link-'.bin2hex(random_bytes(5));
        $actual = $base.'/actual';
        self::assertTrue(mkdir($actual, 0700, true));
        file_put_contents($actual.'/approval.json', '{}');
        self::assertTrue(symlink($actual, $base.'/linked'));
        $provider = new RootOwnedStagingTrustEvidenceProvider($base.'/linked/approval.json', $actual.'/approval.json', $actual.'/approval.json');

        try {
            $provider->evidence();
            self::fail('A lexical parent symlink must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('parent path', $exception->getMessage());
        }
    }

    public function test_final_file_symlink_is_rejected(): void
    {
        $base = sys_get_temp_dir().'/dq-file-link-'.bin2hex(random_bytes(5));
        self::assertTrue(mkdir($base, 0700));
        file_put_contents($base.'/target.json', '{}');
        self::assertTrue(symlink($base.'/target.json', $base.'/approval.json'));
        $provider = new RootOwnedStagingTrustEvidenceProvider($base.'/approval.json', $base.'/target.json', $base.'/target.json');

        try {
            $provider->evidence();
            self::fail('A final-file symlink must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('regular non-symlink file', $exception->getMessage());
        }
    }

    public function test_unsafe_parent_permissions_are_rejected(): void
    {
        $base = sys_get_temp_dir().'/dq-unsafe-parent-'.bin2hex(random_bytes(5));
        self::assertTrue(mkdir($base, 0777));
        chmod($base, 0777);
        file_put_contents($base.'/approval.json', '{}');
        $provider = new RootOwnedStagingTrustEvidenceProvider($base.'/approval.json', $base.'/approval.json', $base.'/approval.json');

        try {
            $provider->evidence();
            self::fail('An unsafe parent directory must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('parent path', $exception->getMessage());
        }
    }

    public function test_path_handle_identity_mismatch_detects_a_replacement_race(): void
    {
        $provider = new RootOwnedStagingTrustEvidenceProvider;
        $method = new ReflectionMethod($provider, 'assertSameFileIdentity');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('path/handle identity changed');

        $method->invoke($provider, ['dev' => 1, 'ino' => 10], ['dev' => 1, 'ino' => 11], 'approval receipt');
    }

    /** @return array<string,mixed> */
    private function scope(): array
    {
        return [
            'environment' => 'staging',
            'connection' => 'mysql',
            'configured_database' => 'laraveldji_staging_restore',
            'actual_database' => 'laraveldji_staging_restore',
            'database_purpose' => 'STAGING_RESTORE',
            'database_matches' => true,
        ];
    }

    /**
     * @return array{
     *     project_id: string,
     *     instance_id: string,
     *     networks: list<string>,
     *     subnets: list<string>,
     *     public_ips: list<string>
     * }
     */
    private function runtime(): array
    {
        return [
            'project_id' => 'project-06b7a0c7-35a4-4282-b81',
            'instance_id' => '4026030371236572795',
            'networks' => ['projects/379534698416/global/networks/laraveldji-staging-net'],
            'subnets' => ['projects/379534698416/regions/asia-southeast1/subnetworks/laraveldji-staging-asia-se1'],
            'public_ips' => [],
        ];
    }

    /** @return list<string> */
    private function readOnlyGrants(): array
    {
        return [
            'GRANT USAGE ON *.* TO `audit`@`%`',
            'GRANT SELECT, SHOW VIEW ON `laraveldji_staging_restore`.* TO `audit`@`%`',
        ];
    }

    /**
     * @param  array{project_id:string,instance_id:string,networks:list<string>,subnets:list<string>,public_ips:list<string>}|null  $runtime
     * @param  array{server_uuid:string,grants:list<string>}|null  $database
     * @param  array{approval:array<string,mixed>,approval_sha256:string,release:array<string,mixed>,release_identity_sha256:string,restore:array<string,mixed>,restore_receipt_sha256:string}|null  $evidence
     */
    private function authorizer(?array $runtime = null, ?array $database = null, ?array $evidence = null): StagingRestoreAuditAuthorizer
    {
        $runtimeIdentity = new class($runtime ?? $this->runtime()) implements StagingRuntimeIdentityProvider
        {
            /** @param array{project_id:string,instance_id:string,networks:list<string>,subnets:list<string>,public_ips:list<string>} $value */
            public function __construct(private array $value) {}

            public function identity(): array
            {
                return $this->value;
            }
        };
        $databaseIdentity = new class($database ?? ['server_uuid' => self::STAGING_UUID, 'grants' => $this->readOnlyGrants()]) implements StagingDatabaseIdentityProvider
        {
            /** @param array{server_uuid:string,grants:list<string>} $value */
            public function __construct(private array $value) {}

            public function identity(): array
            {
                return $this->value;
            }
        };
        $trustEvidence = new class($evidence ?? $this->evidence()) implements StagingTrustEvidenceProvider
        {
            /** @param array{approval:array<string,mixed>,approval_sha256:string,release:array<string,mixed>,release_identity_sha256:string,restore:array<string,mixed>,restore_receipt_sha256:string} $value */
            public function __construct(private array $value) {}

            public function evidence(): array
            {
                return $this->value;
            }
        };

        return new StagingRestoreAuditAuthorizer($runtimeIdentity, $databaseIdentity, $trustEvidence);
    }

    /**
     * @param  array<string,mixed>  $approval
     * @param  array<string,mixed>  $release
     * @param  array<string,mixed>  $restore
     * @return array{
     *     approval: array<string,mixed>,
     *     approval_sha256: string,
     *     release: array<string,mixed>,
     *     release_identity_sha256: string,
     *     restore: array<string,mixed>,
     *     restore_receipt_sha256: string
     * }
     */
    private function evidence(array $approval = [], array $release = [], array $restore = []): array
    {
        $approval = array_replace_recursive([
            'schema_version' => 1,
            'mode' => 'DRY_RUN_READ_ONLY',
            'expires_at' => '2099-01-01T00:00:00Z',
            'release_sha' => self::RELEASE_SHA,
            'backup_artifact_sha256' => self::BACKUP_SHA,
            'database' => ['name' => 'laraveldji_staging_restore', 'server_uuid' => self::STAGING_UUID, 'production_server_uuid' => self::PRODUCTION_UUID, 'read_only' => true],
            'gcp' => [
                'project_id' => 'project-06b7a0c7-35a4-4282-b81',
                'instance_id' => '4026030371236572795',
                'networks' => $this->runtime()['networks'],
                'subnets' => $this->runtime()['subnets'],
                'public_ip_absent' => true,
            ],
        ], $approval);
        /** @var array<string,mixed> $approval */

        return [
            'approval' => $approval,
            'approval_sha256' => hash('sha256', json_encode($approval, JSON_THROW_ON_ERROR)),
            'release' => array_replace(['git_sha' => self::RELEASE_SHA, 'immutable' => true], $release),
            'release_identity_sha256' => str_repeat('c', 64),
            'restore' => array_replace(['backup_artifact_sha256' => self::BACKUP_SHA, 'production_server_uuid' => self::PRODUCTION_UUID, 'restore_verified' => true], $restore),
            'restore_receipt_sha256' => str_repeat('d', 64),
        ];
    }
}

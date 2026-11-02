<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class EncryptedBackupToolingTest extends TestCase
{
    private string $bin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bin = sys_get_temp_dir().'/laraveldji-backup-tools-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->bin, 0700, true));
        foreach (['mysql', 'mysqldump'] as $executable) {
            $this->writeExecutable($executable, "#!/usr/bin/env bash\necho 'mysql  Ver 8.4.13 for Linux on x86_64'\n");
        }
        foreach (['gzip', 'age'] as $executable) {
            $this->writeExecutable($executable, "#!/usr/bin/env bash\nexit 0\n");
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->bin.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->bin);
        parent::tearDown();
    }

    public function test_backup_validate_only_accepts_pinned_tools_without_connection_or_write(): void
    {
        $process = $this->runScript('scripts/operations/backup-database-encrypted.sh', [
            'APP_ENV' => 'production',
            'DB_DATABASE' => 'schema_admin',
            'BACKUP_DIRECTORY' => '/protected/backups',
            'BACKUP_AGE_RECIPIENT' => 'age1testrecipient',
        ]);

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('no connection or write occurred', $process->getOutput());
    }

    public function test_backup_refuses_wrong_environment_before_any_database_action(): void
    {
        $process = $this->runScript('scripts/operations/backup-database-encrypted.sh', [
            'APP_ENV' => 'staging',
            'DB_DATABASE' => 'schema_admin',
            'BACKUP_DIRECTORY' => '/protected/backups',
            'BACKUP_AGE_RECIPIENT' => 'age1testrecipient',
        ]);

        self::assertSame(64, $process->getExitCode());
        self::assertStringContainsString('APP_ENV=production', $process->getErrorOutput());
    }

    public function test_restore_validate_only_accepts_only_canonical_staging_restore_target(): void
    {
        $accepted = $this->runScript('scripts/operations/restore-encrypted-to-staging.sh', [
            'APP_ENV' => 'staging',
            'DB_DATABASE' => 'laraveldji_staging_restore',
        ]);
        self::assertSame(0, $accepted->getExitCode());
        self::assertStringContainsString('no database connection or write occurred', $accepted->getOutput());

        $refused = $this->runScript('scripts/operations/restore-encrypted-to-staging.sh', [
            'APP_ENV' => 'staging',
            'DB_DATABASE' => 'schema_admin',
        ]);
        self::assertSame(64, $refused->getExitCode());
        self::assertStringContainsString('must end in _staging_restore', $refused->getErrorOutput());
    }

    /** @param array<string, string> $environment */
    private function runScript(string $script, array $environment): Process
    {
        $process = new Process(['bash', dirname(__DIR__, 3).'/'.$script, '--validate-only'], null, [
            ...$environment,
            'PATH' => $this->bin.':'.(getenv('PATH') ?: '/usr/bin:/bin'),
        ]);
        $process->run();

        return $process;
    }

    private function writeExecutable(string $name, string $contents): void
    {
        $path = $this->bin.'/'.$name;
        self::assertNotFalse(file_put_contents($path, $contents));
        self::assertTrue(chmod($path, 0700));
    }
}

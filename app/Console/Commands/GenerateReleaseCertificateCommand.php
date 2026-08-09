<?php

namespace App\Console\Commands;

use App\Support\Release\ReleaseCertificateData;
use App\Support\Release\ReleaseCertificateGenerator;
use Illuminate\Console\Command;
use RuntimeException;

final class GenerateReleaseCertificateCommand extends Command
{
    protected $signature = 'operations:release-certificate
        {--manifest= : Verified release manifest JSON}
        {--gate-evidence= : JSON object of certification gates}
        {--health-evidence= : JSON object of health results}
        {--environment=testing-production-like : Certification environment label}
        {--started-at= : ISO-8601 start}
        {--completed-at= : ISO-8601 completion}
        {--quality-reference= : CI/quality evidence reference}
        {--browser-result=pass : pass|fail}
        {--rollback-compatible : Confirm tested application rollback compatibility}
        {--output= : Certificate output path}';

    protected $description = 'Generate immutable production-like testing release certification evidence';

    public function handle(ReleaseCertificateGenerator $generator): int
    {
        try {
            $manifest = $this->verifiedManifest($this->textOption('manifest'));
            $migration = is_array($manifest['migration_inventory'] ?? null) ? $manifest['migration_inventory'] : [];
            $image = is_array($manifest['image'] ?? null) ? $manifest['image'] : [];
            $data = new ReleaseCertificateData(
                $this->text($manifest['release_id'] ?? null),
                $this->text($manifest['checksum'] ?? null),
                $this->text($manifest['git_commit'] ?? null),
                $this->text($image['digest'] ?? null),
                $this->integer($migration['count'] ?? null),
                $this->text($migration['checksum'] ?? null),
                $this->textOption('environment', 'testing-production-like'),
                $this->textOption('started-at'),
                $this->textOption('completed-at'),
                $this->evidence($this->textOption('gate-evidence')),
                $this->evidence($this->textOption('health-evidence')),
                $this->text($manifest['rollback_target'] ?? null),
                (bool) $this->option('rollback-compatible'),
                $this->textOption('quality-reference'),
                $this->textOption('browser-result', 'pass'),
            );
            $certificate = $generator->generate($data);
            $output = $this->textOption('output', storage_path('release-certificates/'.$data->releaseId.'.json'));
            $generator->writeImmutable($certificate, $output);
            $this->line(json_encode(['status' => $certificate['status'], 'release_id' => $data->releaseId, 'checksum' => $certificate['checksum'], 'path' => $output], JSON_THROW_ON_ERROR));

            return $certificate['status'] === 'certified' ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function verifiedManifest(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Manifest file is required.');
        }
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Manifest must be an object.');
        }
        $manifest = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $manifest[$key] = $value;
            }
        }
        $checksum = $manifest['checksum'] ?? null;
        unset($manifest['checksum']);
        $actual = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (! is_string($checksum) || ! hash_equals($checksum, $actual)) {
            throw new RuntimeException('Manifest integrity check failed.');
        }
        $manifest['checksum'] = $checksum;

        return $manifest;
    }

    /** @return array<string, bool|int|float|string> */
    private function evidence(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Evidence JSON file is required.');
        }
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Evidence must be an object.');
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_bool($value) || is_int($value) || is_float($value) || is_string($value))) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function textOption(string $name, string $fallback = ''): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}

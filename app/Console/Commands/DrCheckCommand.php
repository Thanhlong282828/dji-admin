<?php

namespace App\Console\Commands;

use App\Support\Continuity\DrCertificateGenerator;
use Illuminate\Console\Command;
use RuntimeException;

final class DrCheckCommand extends Command
{
    protected $signature = 'operations:dr-check
        {--exercise-id= : Stable testing exercise identifier}
        {--evidence= : Safe JSON evidence file}
        {--output= : Immutable certificate output path}
        {--print : Print certificate without writing}';

    protected $description = 'Generate a guarded testing-only disaster-recovery certificate';

    public function handle(DrCertificateGenerator $generator): int
    {
        try {
            $this->guard();
            $evidence = $this->evidence();
            $certificate = $generator->generate(
                $this->optionString('exercise-id'),
                'testing_dr_exercise',
                $this->text($evidence, 'source_git_sha'),
                $this->text($evidence, 'started_at'),
                $this->text($evidence, 'completed_at'),
                $this->section($evidence, 'backup'),
                $this->section($evidence, 'restore'),
                $this->section($evidence, 'recovery'),
                $this->section($evidence, 'rto'),
                $this->section($evidence, 'rpo'),
                $this->section($evidence, 'gate_results'),
            );
            if ($this->option('print')) {
                $this->line(json_encode($certificate, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return $certificate['status'] === 'certified' ? self::SUCCESS : self::FAILURE;
            }
            $output = $this->optionString('output', storage_path('dr-certificates/'.$certificate['exercise_id'].'.json'));
            $generator->writeImmutable($certificate, $output);
            $this->line(json_encode(['status' => $certificate['status'], 'scope' => $certificate['scope'], 'exercise_id' => $certificate['exercise_id'], 'checksum' => $certificate['checksum']], JSON_THROW_ON_ERROR));

            return $certificate['status'] === 'certified' ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function guard(): void
    {
        $environment = app()->environment();
        $database = config('database.connections.mysql.database');
        if ($environment !== 'testing' || ! is_string($database) || preg_match('/^schema_admin_testing_[a-z0-9_]*dr(?:_[a-z0-9_]+)?$/', $database) !== 1) {
            throw new RuntimeException('DR certification requires APP_ENV=testing and an isolated schema_admin_testing_*dr database.');
        }
    }

    /** @return array<string, mixed> */
    private function evidence(): array
    {
        $path = $this->optionString('evidence');
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('A safe DR evidence JSON file is required.');
        }
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('DR evidence must be a JSON object.');
        }

        $evidence = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $evidence[$key] = $value;
            }
        }

        return $evidence;
    }

    /** @param array<string, mixed> $evidence
     * @return array<string, bool|int|float|string>
     */
    private function section(array $evidence, string $key): array
    {
        $value = $evidence[$key] ?? null;
        if (! is_array($value)) {
            throw new RuntimeException('Missing DR evidence section: '.$key);
        }
        $safe = [];
        foreach ($value as $name => $item) {
            if (! is_string($name) || preg_match('/password|secret|token|salary|tax|bank|path/i', $name) === 1 || (! is_bool($item) && ! is_int($item) && ! is_float($item) && ! is_string($item))) {
                throw new RuntimeException('Unsafe DR evidence field in section '.$key.'.');
            }
            $safe[$name] = $item;
        }

        return $safe;
    }

    /** @param array<string, mixed> $evidence */
    private function text(array $evidence, string $key): string
    {
        $value = $evidence[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private function optionString(string $name, string $fallback = ''): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}

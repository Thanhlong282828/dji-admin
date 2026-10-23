<?php

declare(strict_types=1);

namespace App\Support\TestGovernance;

use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;

/**
 * @phpstan-type Metrics array{executable_lines:int,covered_lines:int,uncovered_lines:int,line_rate:float}
 * @phpstan-type FileMetrics array{path:string,context:string,executable_lines:int,covered_lines:int,uncovered_lines:int,line_rate:float}
 * @phpstan-type ClassMetrics array{class:string,path:string,context:string,executable_lines:int,covered_lines:int,uncovered_lines:int,line_rate:float}
 * @phpstan-type ContextMetrics array{context:string,executable_lines:int,covered_lines:int,uncovered_lines:int,line_rate:float}
 * @phpstan-type Inventory array{schema:string,source_scope:list<string>,repository:Metrics,contexts:list<ContextMetrics>,files:list<FileMetrics>,classes:list<ClassMetrics>}
 */
final class CoverageInventory
{
    public const SCHEMA = 'laraveldji.coverage-inventory.v1';

    /** @return Inventory */
    public static function fromClover(string $cloverPath, string $repositoryRoot): array
    {
        libxml_use_internal_errors(true);
        $clover = simplexml_load_file($cloverPath, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);

        if (! $clover instanceof SimpleXMLElement) {
            throw new RuntimeException('Unable to parse Clover coverage XML.');
        }

        /** @var list<FileMetrics> $files */
        $files = [];
        /** @var list<ClassMetrics> $classes */
        $classes = [];

        foreach ($clover->xpath('//file') ?: [] as $file) {
            $path = self::sourcePath((string) $file['name'], $repositoryRoot);
            $metricsNodes = $file->xpath('./metrics');
            if (! isset($metricsNodes[0])) {
                throw new RuntimeException('Clover file is missing metrics.');
            }

            $files[] = ['path' => $path, 'context' => self::context($path), ...self::metrics($metricsNodes[0])];

            foreach ($file->xpath('./class') ?: [] as $class) {
                $classMetricsNodes = $class->xpath('./metrics');
                if (! isset($classMetricsNodes[0])) {
                    throw new RuntimeException('Clover class is missing metrics.');
                }

                $classes[] = [
                    'class' => (string) $class['name'],
                    'path' => $path,
                    'context' => self::context($path),
                    ...self::metrics($classMetricsNodes[0]),
                ];
            }
        }

        usort($files, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        usort($classes, static fn (array $left, array $right): int => [$left['path'], $left['class']] <=> [$right['path'], $right['class']]);

        /** @var array<string, list<FileMetrics>> $contexts */
        $contexts = [];
        foreach ($files as $file) {
            $contexts[(string) $file['context']][] = $file;
        }

        /** @var list<ContextMetrics> $contextSummary */
        $contextSummary = [];
        foreach ($contexts as $context => $contextFiles) {
            $contextSummary[] = ['context' => $context, ...self::aggregate($contextFiles)];
        }
        usort($contextSummary, static fn (array $left, array $right): int => $left['context'] <=> $right['context']);

        return [
            'schema' => self::SCHEMA,
            'source_scope' => ['app', 'Modules'],
            'repository' => self::aggregate($files),
            'contexts' => $contextSummary,
            'files' => $files,
            'classes' => $classes,
        ];
    }

    /** @param list<string> $arguments */
    public static function run(array $arguments, string $repositoryRoot): int
    {
        $input = $arguments[1] ?? '';
        $output = $arguments[2] ?? '';
        $minimum = 20.0;

        foreach (array_slice($arguments, 3) as $argument) {
            if (str_starts_with($argument, '--min=')) {
                $value = filter_var(substr($argument, 6), FILTER_VALIDATE_FLOAT);
                if (! is_float($value) || $value < 0 || $value > 100) {
                    throw new InvalidArgumentException('Coverage minimum must be between 0 and 100.');
                }
                $minimum = $value;
            }
        }

        if ($input === '' || $output === '') {
            throw new InvalidArgumentException('Usage: summarize-coverage.php <clover.xml> <summary.json> [--min=20]');
        }

        $inventory = self::fromClover($input, $repositoryRoot);
        $json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        if (preg_match('/(BEGIN [A-Z ]*PRIVATE KEY|gh[pousr]_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{20,}|AKIA[0-9A-Z]{16})/', $json)) {
            throw new RuntimeException('Coverage summary contains a forbidden sensitive-data marker.');
        }

        $directory = dirname($output);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create coverage summary directory.');
        }

        if (file_put_contents($output, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write coverage summary.');
        }

        $rate = (float) $inventory['repository']['line_rate'];
        fwrite(STDOUT, sprintf("Coverage: %.4f%% (%d/%d executable lines); minimum: %.1f%%\n", $rate, $inventory['repository']['covered_lines'], $inventory['repository']['executable_lines'], $minimum));

        return $rate < $minimum ? 1 : 0;
    }

    /** @return Metrics */
    private static function metrics(SimpleXMLElement $metrics): array
    {
        $executable = filter_var((string) $metrics['statements'], FILTER_VALIDATE_INT);
        $covered = filter_var((string) $metrics['coveredstatements'], FILTER_VALIDATE_INT);

        if (! is_int($executable) || ! is_int($covered) || $executable < 0 || $covered < 0 || $covered > $executable) {
            throw new RuntimeException('Clover contains invalid statement metrics.');
        }

        return [
            'executable_lines' => $executable,
            'covered_lines' => $covered,
            'uncovered_lines' => $executable - $covered,
            'line_rate' => $executable === 0 ? 100.0 : round(($covered / $executable) * 100, 4),
        ];
    }

    private static function sourcePath(string $path, string $repositoryRoot): string
    {
        $normalized = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $repositoryRoot), '/').'/';

        if (str_starts_with($normalized, $root)) {
            $normalized = substr($normalized, strlen($root));
        } elseif (($modules = strpos($normalized, '/Modules/')) !== false) {
            $normalized = substr($normalized, $modules + 1);
        } elseif (($app = strpos($normalized, '/app/')) !== false) {
            $normalized = substr($normalized, $app + 1);
        }

        if (! preg_match('#^(app|Modules/[A-Za-z0-9_-]+)/#', $normalized)) {
            throw new RuntimeException('Clover contains a path outside the approved app + Modules source scope.');
        }
        if (str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            throw new RuntimeException('Clover contains an unsafe source path.');
        }

        return $normalized;
    }

    private static function context(string $path): string
    {
        if (str_starts_with($path, 'app/')) {
            return 'app';
        }
        $parts = explode('/', $path, 3);

        return $parts[0].'/'.$parts[1];
    }

    /**
     * @param  list<FileMetrics>  $items
     * @return Metrics
     */
    private static function aggregate(array $items): array
    {
        $executable = 0;
        $covered = 0;
        foreach ($items as $item) {
            $executable += (int) $item['executable_lines'];
            $covered += (int) $item['covered_lines'];
        }

        return [
            'executable_lines' => $executable,
            'covered_lines' => $covered,
            'uncovered_lines' => $executable - $covered,
            'line_rate' => $executable === 0 ? 100.0 : round(($covered / $executable) * 100, 4),
        ];
    }
}

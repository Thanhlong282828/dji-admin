<?php

namespace App\Support\Release;

use RuntimeException;

final class MigrationRiskScanner
{
    /** @return list<array{file:string,risk:'safe'|'caution'|'blocking',reasons:list<string>,manual_review:bool}> */
    public function scan(string $directory): array
    {
        if (! is_dir($directory)) {
            throw new RuntimeException('Migration directory does not exist.');
        }
        $files = glob(rtrim($directory, '/').'/*.php');
        if (! is_array($files)) {
            throw new RuntimeException('Unable to enumerate migration files.');
        }
        $reports = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                throw new RuntimeException('Unable to read migration file.');
            }
            $reports[] = $this->classify($file, $content);
        }
        usort($reports, static fn (array $left, array $right): int => $left['file'] <=> $right['file']);

        return $reports;
    }

    /** @return array{file:string,risk:'safe'|'caution'|'blocking',reasons:list<string>,manual_review:bool} */
    public function classify(string $file, string $content): array
    {
        $up = $this->upMethod($content);
        $blocking = $this->matches($up, ['dropColumn(', 'dropIfExists(', 'Schema::drop(', 'renameColumn(', 'DB::statement(', '->delete(', '->update(']);
        $caution = $this->matches($up, ['->index(', '->unique(', '->foreign(', '->change(', 'Schema::table(', 'chunkById(', 'cursor(']);
        if ($blocking !== []) {
            return ['file' => $file, 'risk' => 'blocking', 'reasons' => $blocking, 'manual_review' => true];
        }
        if ($caution !== []) {
            return ['file' => $file, 'risk' => 'caution', 'reasons' => $caution, 'manual_review' => true];
        }

        return ['file' => $file, 'risk' => 'safe', 'reasons' => ['additive schema operations only'], 'manual_review' => false];
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function matches(string $content, array $needles): array
    {
        $matches = [];
        foreach ($needles as $needle) {
            if (str_contains($content, $needle)) {
                $matches[] = $needle;
            }
        }

        return $matches;
    }

    private function upMethod(string $content): string
    {
        if (preg_match('/public\s+function\s+up\s*\([^)]*\)\s*:\s*void\s*\{(.*?)(?=public\s+function\s+down\s*\()/s', $content, $matches) === 1) {
            return $matches[1];
        }

        return $content;
    }
}

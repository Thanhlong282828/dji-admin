<?php

namespace App\Support\TestGovernance;

use InvalidArgumentException;

final readonly class CriticalInvariant
{
    /** @param non-empty-list<string> $testFiles */
    public function __construct(
        public string $id,
        public string $module,
        public string $description,
        public string $risk,
        public array $testFiles,
        public string $testType,
    ) {
        if (! in_array($risk, ['critical', 'high'], true)) {
            throw new InvalidArgumentException('Critical invariants must be classified critical or high.');
        }
    }

    /** @return array{id:string,module:string,description:string,risk:string,test_files:non-empty-list<string>,test_type:string,status:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'description' => $this->description,
            'risk' => $this->risk,
            'test_files' => $this->testFiles,
            'test_type' => $this->testType,
            'status' => array_all($this->testFiles, static fn (string $file): bool => is_file(base_path($file))) ? 'covered' : 'gap',
        ];
    }
}

<?php

namespace App\Support\Performance;

final readonly class IndexDefinition
{
    /** @param list<string> $columns */
    public function __construct(public string $table, public array $columns, public bool $unique = false, public string $type = 'BTREE') {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'table' => strtolower($this->table),
            'columns' => array_map(strtolower(...), $this->columns),
            'unique' => $this->unique,
            'type' => strtoupper($this->type),
        ], JSON_THROW_ON_ERROR));
    }

    public function logicallyEquals(self $other): bool
    {
        return hash_equals($this->fingerprint(), $other->fingerprint());
    }
}

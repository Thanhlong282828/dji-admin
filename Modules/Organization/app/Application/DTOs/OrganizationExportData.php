<?php

namespace Modules\Organization\Application\DTOs;

use Generator;

final readonly class OrganizationExportData
{
    /**
     * @param  list<string>  $headings
     * @param  Generator<int, list<bool|float|int|string|null>, mixed, void>  $rows
     */
    public function __construct(
        public string $filename,
        public array $headings,
        public Generator $rows,
    ) {}
}

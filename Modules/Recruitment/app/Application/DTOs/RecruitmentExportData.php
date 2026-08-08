<?php

namespace Modules\Recruitment\Application\DTOs;

use Generator;

final readonly class RecruitmentExportData
{
    /**
     * @param  list<string>  $headings
     * @param  Generator<int, array<int, bool|float|int|string|null>, mixed, void>  $rows
     */
    public function __construct(public string $filename, public array $headings, public Generator $rows) {}
}

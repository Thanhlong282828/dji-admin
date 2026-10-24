<?php

namespace Modules\Performance\Application\DTOs;

final readonly class EnterpriseReportViewModel
{
    /**
     * @param  array<string,mixed>  $sections
     * @param  list<array{key:string,title:string,category:string,description:string,formats:list<string>,classification:string}>  $catalog
     * @param  list<string>  $notes
     * @param  array{cost_centers:list<array{id:int,name:string}>,units:list<array{id:int,name:string}>,positions:list<array{id:int,name:string,level:int}>}  $options
     */
    public function __construct(
        public string $companyName,
        public string $periodLabel,
        public string $generatedAt,
        public int $schemaVersion,
        public bool $salaryAllowed,
        public int $qualityScore,
        public array $sections,
        public array $catalog,
        public array $notes,
        public array $options,
    ) {}
}

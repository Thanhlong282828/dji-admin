<?php

namespace Modules\Payroll\Domain\Adapters;

interface GovernmentDeclarationGeneratorInterface
{
    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $items
     */
    public function generate(array $header, array $items): string;
}

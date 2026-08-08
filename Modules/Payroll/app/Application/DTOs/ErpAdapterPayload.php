<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class ErpAdapterPayload
{
    /** @param list<array{line_number:int,account:string,description:string,debit_minor:int,credit_minor:int,currency:string,cost_center:string,organization_unit:string}> $lines */
    public function __construct(public string $reference, public string $journalNumber, public array $lines) {}
}

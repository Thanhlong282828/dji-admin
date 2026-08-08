<?php

namespace Modules\Payroll\Domain\Services;

use Barryvdh\DomPDF\Facade\Pdf;

final readonly class AdjustmentStatementPdfRenderer
{
    /** @param array<string, mixed> $statement */
    public function render(array $statement): string
    {
        return Pdf::setOption(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])
            ->loadView('payroll::pdf.adjustment-statement', ['statement' => $statement])
            ->setPaper('a4')
            ->output();
    }
}

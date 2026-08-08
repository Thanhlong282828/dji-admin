<?php

namespace Modules\Payroll\Domain\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Payroll\Application\DTOs\PayslipSnapshotData;

final readonly class PayslipPdfRenderer
{
    public function render(PayslipSnapshotData $snapshot, int $version, string $generatedAt): string
    {
        return Pdf::setOption(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])->loadView('payroll::pdf.payslip', ['snapshot' => $snapshot->toArray(), 'payslipVersion' => $version, 'generatedAt' => $generatedAt])->setPaper('a4')->output();
    }
}

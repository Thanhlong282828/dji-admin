<?php

namespace Modules\Payroll\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Payroll\Domain\Services\PayslipGenerationService;

final class GeneratePayslipPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $payslipId) {}

    public function handle(PayslipGenerationService $service): void
    {
        $service->generate($this->payslipId);
    }
}

<?php

namespace Modules\Performance\Application\DTOs;

use Illuminate\Http\Request;

final readonly class PlanningExecutionFilter
{
    public function __construct(
        public PlanningFilter $planning,
        public int $headcountDelta,
        public int $payrollAdjustmentBasisPoints,
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        $request->validate([
            'reforecast_headcount_delta' => ['nullable', 'integer', 'between:-1000,1000'],
            'reforecast_payroll_percent' => ['nullable', 'numeric', 'between:-50,50'],
        ]);

        return new self(
            PlanningFilter::fromRequest($request, $companyId),
            $request->integer('reforecast_headcount_delta'),
            (int) round($request->float('reforecast_payroll_percent') * 100),
        );
    }
}

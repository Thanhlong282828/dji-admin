<?php

namespace Modules\Recruitment\Application\DTOs;

use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Enums\InterviewRecommendation;

final readonly class InterviewEvaluationData
{
    /** @param list<InterviewScoreItem> $items */
    public function __construct(
        public array $items,
        public InterviewRecommendation $recommendation,
        public ?string $strengths,
        public ?string $concerns,
        public ?string $privateNotes,
        public ?int $expectedVersion,
    ) {
        $codes = array_map(static fn (InterviewScoreItem $item): string => $item->criterionCode, $items);
        if ($items === [] || count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['scorecard' => 'Scorecard phải có tiêu chí và không được trùng mã.']);
        }
    }

    public function overallScore(): string
    {
        $weightedBasisPoints = 0;
        $totalWeight = 0;
        foreach ($this->items as $item) {
            $weightedBasisPoints += intdiv($item->score * 10000, $item->maximumScore) * $item->weight;
            $totalWeight += $item->weight;
        }
        $basisPoints = $totalWeight > 0 ? intdiv($weightedBasisPoints, $totalWeight) : 0;

        return sprintf('%d.%02d', intdiv($basisPoints, 100), $basisPoints % 100);
    }

    /** @return list<array{criterion_code:string,criterion_label:string,score:int,maximum_score:int,weight:int,comment:string|null}> */
    public function scorecard(): array
    {
        return array_map(static fn (InterviewScoreItem $item): array => $item->toArray(), $this->items);
    }
}

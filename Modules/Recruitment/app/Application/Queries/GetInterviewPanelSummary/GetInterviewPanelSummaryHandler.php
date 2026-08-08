<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewPanelSummary;

use Carbon\CarbonImmutable;
use Modules\Recruitment\Application\DTOs\InterviewCriterionSummary;
use Modules\Recruitment\Application\DTOs\InterviewPanelSummary;
use Modules\Recruitment\Application\DTOs\InterviewRecommendationSummary;
use Modules\Recruitment\Domain\Enums\InterviewRecommendation;
use Modules\Recruitment\Domain\Models\ApplicationInterview;

final class GetInterviewPanelSummaryHandler
{
    public function handle(GetInterviewPanelSummaryQuery $query): InterviewPanelSummary
    {
        $interview = ApplicationInterview::query()
            ->with(['interviewers:id,application_interview_id,user_id', 'evaluations'])
            ->where('company_id', $query->companyId)
            ->findOrFail($query->interviewId);
        $assigned = $interview->interviewers->count();
        $submitted = $interview->evaluations->filter(static fn ($evaluation): bool => $evaluation->isSubmitted());
        $draftCount = $interview->evaluations->count() - $submitted->count();
        $pending = max(0, $assigned - $interview->evaluations->count());
        $scoreCents = [];
        $recommendations = [];
        $criterionAccumulator = [];
        $strengths = [];
        $concerns = [];
        $lastSubmitted = null;
        foreach ($submitted as $evaluation) {
            $recommendation = InterviewRecommendation::tryFrom($evaluation->recommendation);
            if ($recommendation !== null) {
                $recommendations[$recommendation->value] = ($recommendations[$recommendation->value] ?? 0) + 1;
            }
            $scoreCents[] = $this->decimalToCents($evaluation->overall_score);
            $this->aggregateScorecard($criterionAccumulator, $evaluation->scorecard);
            if (is_string($evaluation->strengths) && trim($evaluation->strengths) !== '') {
                $strengths[] = trim($evaluation->strengths);
            }
            if (is_string($evaluation->concerns) && trim($evaluation->concerns) !== '') {
                $concerns[] = trim($evaluation->concerns);
            }
            if ($evaluation->submitted_at instanceof CarbonImmutable && ($lastSubmitted === null || $evaluation->submitted_at->isAfter($lastSubmitted))) {
                $lastSubmitted = $evaluation->submitted_at;
            }
        }
        $criteria = [];
        foreach ($criterionAccumulator as $criterion) {
            $averageBasisPoints = $criterion['count'] > 0 ? intdiv($criterion['basis_points'], $criterion['count']) : 0;
            $criteria[] = new InterviewCriterionSummary(
                $criterion['code'],
                $criterion['label'],
                $this->decimal($averageBasisPoints),
                $this->decimal(intdiv($criterion['score_sum'] * 100, $criterion['count'])),
                $criterion['maximum_score'],
                $criterion['count'],
                intdiv($criterion['weight_sum'], $criterion['count']),
            );
        }
        $recommendationSummaries = [];
        foreach (InterviewRecommendation::cases() as $recommendation) {
            $count = $recommendations[$recommendation->value] ?? 0;
            if ($count > 0) {
                $recommendationSummaries[] = new InterviewRecommendationSummary(
                    $recommendation,
                    $count,
                    $submitted->isEmpty() ? '0.00' : $this->decimal(intdiv($count * 10000, $submitted->count())),
                );
            }
        }

        return new InterviewPanelSummary(
            $interview->id,
            $interview->company_id,
            $interview->application_id,
            $assigned,
            $submitted->count(),
            $draftCount,
            $pending,
            $assigned > 0 ? $this->decimal(intdiv($submitted->count() * 10000, $assigned)) : '0.00',
            $scoreCents === [] ? null : $this->decimal(intdiv(array_sum($scoreCents), count($scoreCents))),
            $scoreCents === [] ? null : $this->decimal(min($scoreCents)),
            $scoreCents === [] ? null : $this->decimal(max($scoreCents)),
            $recommendationSummaries,
            $criteria,
            array_slice(array_values(array_unique($strengths)), 0, 20),
            array_slice(array_values(array_unique($concerns)), 0, 20),
            $lastSubmitted,
            $interview->version,
        );
    }

    /**
     * @param  array<string, array{code:string,label:string,basis_points:int,score_sum:int,maximum_score:int,weight_sum:int,count:int}>  $accumulator
     */
    private function aggregateScorecard(array &$accumulator, mixed $scorecard): void
    {
        if (! is_array($scorecard)) {
            return;
        }
        foreach ($scorecard as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $code = $raw['criterion_code'] ?? null;
            $label = $raw['criterion_label'] ?? null;
            $score = $raw['score'] ?? null;
            $maximum = $raw['maximum_score'] ?? null;
            $weight = $raw['weight'] ?? 0;
            if (! is_string($code) || ! is_string($label) || ! is_numeric($score) || ! is_numeric($maximum) || (int) $maximum < 1) {
                continue;
            }
            $basisPoints = intdiv((int) $score * 10000, (int) $maximum);
            $current = $accumulator[$code] ?? ['code' => $code, 'label' => $label, 'basis_points' => 0, 'score_sum' => 0, 'maximum_score' => 0, 'weight_sum' => 0, 'count' => 0];
            $current['basis_points'] += $basisPoints;
            $current['score_sum'] += (int) $score;
            $current['maximum_score'] = max($current['maximum_score'], (int) $maximum);
            $current['weight_sum'] += is_numeric($weight) ? (int) $weight : 0;
            $current['count']++;
            $accumulator[$code] = $current;
        }
    }

    private function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), abs($cents % 100));
    }

    private function decimalToCents(string $value): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            return 0;
        }
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ((int) $matches[1] * 100) + (int) $fraction;
    }
}

<?php

namespace Modules\Recruitment\Application\DTOs;

use Illuminate\Validation\ValidationException;

final readonly class InterviewScoreItem
{
    public function __construct(
        public string $criterionCode,
        public string $criterionLabel,
        public int $score,
        public int $maximumScore,
        public int $weight,
        public ?string $comment,
    ) {
        if (! preg_match('/^[A-Z0-9_]{2,50}$/', $criterionCode) || $score < 0 || $maximumScore < 1 || $score > $maximumScore || $weight < 1 || $weight > 100) {
            throw ValidationException::withMessages(['scorecard' => 'Score item không hợp lệ.']);
        }
    }

    /** @return array{criterion_code:string,criterion_label:string,score:int,maximum_score:int,weight:int,comment:string|null} */
    public function toArray(): array
    {
        return ['criterion_code' => $this->criterionCode, 'criterion_label' => $this->criterionLabel, 'score' => $this->score, 'maximum_score' => $this->maximumScore, 'weight' => $this->weight, 'comment' => $this->comment];
    }
}

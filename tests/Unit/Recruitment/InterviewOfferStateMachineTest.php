<?php

namespace Tests\Unit\Recruitment;

use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Enums\InterviewStatus;
use Modules\Recruitment\Domain\Services\ApplicationInterviewStateMachine;
use Modules\Recruitment\Domain\Services\CandidateOfferStateMachine;
use Tests\TestCase;

final class InterviewOfferStateMachineTest extends TestCase
{
    public function test_interview_state_machine_allows_only_declared_transitions(): void
    {
        $machine = new ApplicationInterviewStateMachine;
        $machine->assert(InterviewStatus::Scheduled, InterviewStatus::Confirmed);
        $machine->assert(InterviewStatus::Confirmed, InterviewStatus::Completed);
        $this->expectException(ValidationException::class);
        $machine->assert(InterviewStatus::Completed, InterviewStatus::Scheduled);
    }

    public function test_offer_cannot_skip_approval(): void
    {
        $machine = new CandidateOfferStateMachine;
        $this->expectException(ValidationException::class);
        $machine->assert(CandidateOfferStatus::Draft, CandidateOfferStatus::Sent);
    }
}

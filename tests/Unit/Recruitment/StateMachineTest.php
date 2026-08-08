<?php

namespace Tests\Unit\Recruitment;

use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Services\ApplicationStageMachine;
use Modules\Recruitment\Domain\Services\RequisitionStateMachine;
use Tests\TestCase;

final class StateMachineTest extends TestCase
{
    public function test_valid_transitions_are_allowed(): void
    {
        (new RequisitionStateMachine)->assert('draft', 'approval');
        (new ApplicationStageMachine)->assert('received', 'screening');
        $this->addToAssertionCount(2);
    }

    public function test_invalid_application_transition_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new ApplicationStageMachine)->assert('received', 'hired');
    }
}

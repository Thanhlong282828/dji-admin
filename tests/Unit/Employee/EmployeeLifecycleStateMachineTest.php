<?php

namespace Tests\Unit\Employee;

use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Enums\EmployeeLifecycleState;
use Modules\Employee\Domain\Services\EmployeeLifecycleStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EmployeeLifecycleStateMachineTest extends TestCase
{
    /** @return iterable<string, array{EmployeeLifecycleState,EmployeeLifecycleState}> */
    public static function allowed(): iterable
    {
        yield 'hire' => [EmployeeLifecycleState::OfferCreated, EmployeeLifecycleState::Hired];
        yield 'onboarding' => [EmployeeLifecycleState::Hired, EmployeeLifecycleState::Onboarding];
        yield 'active' => [EmployeeLifecycleState::Onboarding, EmployeeLifecycleState::Active];
        yield 'terminate' => [EmployeeLifecycleState::Confirmed, EmployeeLifecycleState::Terminated];
        yield 'archive' => [EmployeeLifecycleState::Terminated, EmployeeLifecycleState::Archived];
    }

    #[DataProvider('allowed')]
    public function test_allowed_transitions(EmployeeLifecycleState $from, EmployeeLifecycleState $to): void
    {
        self::assertTrue((new EmployeeLifecycleStateMachine)->canTransition($from, $to));
    }

    public function test_forbidden_transition_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new EmployeeLifecycleStateMachine)->assertCanTransition(EmployeeLifecycleState::Archived, EmployeeLifecycleState::Active);
    }
}

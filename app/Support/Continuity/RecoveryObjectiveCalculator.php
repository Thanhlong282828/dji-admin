<?php

namespace App\Support\Continuity;

final class RecoveryObjectiveCalculator
{
    /** @return array{configured_seconds:int,observed_seconds:float,met:bool,scope:string} */
    public function evaluate(int $configuredSeconds, float $observedSeconds, string $scope = 'testing_dr_exercise'): array
    {
        return ['configured_seconds' => $configuredSeconds, 'observed_seconds' => round(max(0, $observedSeconds), 3), 'met' => $configuredSeconds > 0 && $observedSeconds <= $configuredSeconds, 'scope' => $scope];
    }
}

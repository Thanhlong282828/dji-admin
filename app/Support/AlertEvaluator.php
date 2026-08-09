<?php

namespace App\Support;

final class AlertEvaluator
{
    /**
     * @param  array<string, float|int>  $signals
     * @param  array<string, array{component:string,signal:string,operator:string,threshold:int|float,severity:string,runbook:string,window_minutes:int}>  $definitions
     * @return list<array{name:string,component:string,signal:string,value:float,threshold:float,severity:string,runbook:string,window_minutes:int}>
     */
    public function evaluate(array $signals, array $definitions): array
    {
        $alerts = [];
        foreach ($definitions as $name => $definition) {
            $signal = $definition['signal'];
            if (! array_key_exists($signal, $signals)) {
                continue;
            }

            $value = (float) $signals[$signal];
            $threshold = (float) $definition['threshold'];
            $triggered = match ($definition['operator']) {
                'gt' => $value > $threshold,
                'gte' => $value >= $threshold,
                'lt' => $value < $threshold,
                'lte' => $value <= $threshold,
                default => false,
            };

            if ($triggered) {
                $alerts[] = [
                    'name' => $name,
                    'component' => $definition['component'],
                    'signal' => $signal,
                    'value' => $value,
                    'threshold' => $threshold,
                    'severity' => $definition['severity'],
                    'runbook' => $definition['runbook'],
                    'window_minutes' => $definition['window_minutes'],
                ];
            }
        }

        return $alerts;
    }
}

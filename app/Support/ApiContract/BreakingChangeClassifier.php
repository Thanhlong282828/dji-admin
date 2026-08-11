<?php

namespace App\Support\ApiContract;

final class BreakingChangeClassifier
{
    /**
     * @param  array<string,array{operation_id:string,security:list<string>,required:list<string>,responses:list<string>}>  $baseline
     * @param  array<string,array{operation_id:string,security:list<string>,required:list<string>,responses:list<string>}>  $current
     * @return list<array{operation:string,classification:string,reason:string}>
     */
    public function compare(array $baseline, array $current): array
    {
        $findings = [];
        foreach ($baseline as $operation => $before) {
            if (! isset($current[$operation])) {
                $findings[] = ['operation' => $operation, 'classification' => 'BREAKING', 'reason' => 'Documented operation removed.'];

                continue;
            }
            $after = $current[$operation];
            if ($before['operation_id'] !== $after['operation_id']) {
                $findings[] = ['operation' => $operation, 'classification' => 'BREAKING', 'reason' => 'Stable operationId changed.'];
            }
            if ($before['security'] !== $after['security']) {
                $findings[] = ['operation' => $operation, 'classification' => 'BREAKING', 'reason' => 'Authentication contract changed.'];
            }
            $newRequired = array_values(array_diff($after['required'], $before['required']));
            if ($newRequired !== []) {
                $findings[] = ['operation' => $operation, 'classification' => 'BREAKING', 'reason' => 'Previously optional input became required: '.implode(', ', $newRequired).'.'];
            }
            $removedResponses = array_values(array_diff($before['responses'], $after['responses']));
            if ($removedResponses !== []) {
                $findings[] = ['operation' => $operation, 'classification' => 'POTENTIALLY_BREAKING', 'reason' => 'Documented response removed: '.implode(', ', $removedResponses).'.'];
            }
        }
        foreach (array_diff_key($current, $baseline) as $operation => $_) {
            $findings[] = ['operation' => $operation, 'classification' => 'ADDITIVE', 'reason' => 'New documented operation.'];
        }

        return $findings;
    }
}

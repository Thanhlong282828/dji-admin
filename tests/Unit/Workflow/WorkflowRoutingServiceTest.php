<?php

namespace Tests\Unit\Workflow;

use Modules\Workflow\Domain\Services\WorkflowRoutingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkflowRoutingServiceTest extends TestCase
{
    /** @return iterable<string,array{array<string,scalar|null>,array<string,scalar|null>,bool}> */
    public static function conditions(): iterable
    {
        yield 'empty condition' => [['amount' => 100], [], true];
        yield 'equals' => [['country' => 'VN'], ['field' => 'country', 'operator' => 'eq', 'value' => 'VN'], true];
        yield 'not equals' => [['country' => 'VN'], ['field' => 'country', 'operator' => 'neq', 'value' => 'US'], true];
        yield 'greater than' => [['amount' => 101], ['field' => 'amount', 'operator' => 'gt', 'value' => 100], true];
        yield 'missing field' => [[], ['field' => 'amount', 'operator' => 'gt', 'value' => 100], false];
    }

    /**
     * @param  array<string, scalar|null>  $context
     * @param  array<string, scalar|null>  $condition
     */
    #[DataProvider('conditions')]
    public function test_deterministic_condition_routing(array $context, array $condition, bool $expected): void
    {
        self::assertSame($expected, (new WorkflowRoutingService)->matches($context, $condition));
    }
}

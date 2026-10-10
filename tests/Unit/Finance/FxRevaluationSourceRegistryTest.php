<?php

namespace Tests\Unit\Finance;

use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\FxRevaluableSource;
use Modules\Finance\Application\Services\FxRevaluationSourceRegistry;
use Tests\Support\FxTestSourceAdapter;
use Tests\TestCase;

final class FxRevaluationSourceRegistryTest extends TestCase
{
    public function test_production_registry_fails_closed_for_unsupported_source(): void
    {
        $this->expectException(ValidationException::class);
        (new FxRevaluationSourceRegistry)->resolve('ar', 1, null, 10);
    }

    public function test_explicit_test_adapter_resolves_complete_evidence_only(): void
    {
        $source = new FxRevaluableSource('test_receivable', 10, 1, 2, 'USD', 100, 'VND', 2400000, 'asset', 99, str_repeat('a', 64));
        $registry = new FxRevaluationSourceRegistry([new FxTestSourceAdapter($source)]);
        self::assertSame($source, $registry->resolve('test_receivable', 1, 2, 10));
    }
}

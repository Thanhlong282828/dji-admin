<?php

namespace Tests\Unit\Tax;

use Illuminate\Validation\ValidationException;
use Modules\Tax\Application\Services\TaxPolicyResolver;
use Tests\TestCase;

final class TaxPolicyResolverTest extends TestCase
{
    public function test_calculation_uses_integer_minor_units_and_half_up_rounding(): void
    {
        $resolver = $this->app->make(TaxPolicyResolver::class);

        self::assertSame(1_000, $resolver->calculate(10_000, 1_000));
        self::assertSame(1, $resolver->calculate(5, 1_000));
        self::assertSame(0, $resolver->calculate(0, 1_000));
    }

    public function test_calculation_rejects_invalid_amount_or_rate(): void
    {
        $resolver = $this->app->make(TaxPolicyResolver::class);

        foreach ([[-1, 1_000], [10_000, -1], [10_000, 10_001]] as [$amount, $rate]) {
            try {
                $resolver->calculate($amount, $rate);
                self::fail('Invalid tax input must fail closed.');
            } catch (ValidationException $exception) {
                self::assertSame('TAX_CALCULATION_INPUT_INVALID', $exception->errors()['tax'][0]);
            }
        }
    }

    public function test_pending_migration_declares_canonical_tax_governance_contract(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_30_000117_create_vat_policy_foundation.php'));
        self::assertIsString($migration);

        foreach ([
            "Schema::create('tax_categories'",
            "Schema::create('vat_policies'",
            "Schema::create('tax_transaction_evidence'",
            "'company_id'",
            "'effective_from'",
            "'effective_to'",
            "'version'",
            "'accounting_posting_policy_id'",
            "'mapping_snapshot'",
            "'tax_evidence_source_unique'",
        ] as $contract) {
            self::assertStringContainsString($contract, $migration);
        }
    }
}

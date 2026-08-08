<?php

namespace Tests\Unit\Payroll;

use Modules\Payroll\Infrastructure\Adapters\GenericXmlDeclarationGenerator;
use PHPUnit\Framework\TestCase;

final class GenericXmlDeclarationGeneratorTest extends TestCase
{
    public function test_output_is_deterministic_and_contains_only_supplied_snapshot(): void
    {
        $generator = new GenericXmlDeclarationGenerator;
        $header = ['reference' => 'GTF-2026-1-V1', 'tax_year' => 2026, 'jurisdiction' => 'VN', 'filing_type' => 'annual_pit_employer_declaration', 'version' => 1, 'source_checksum' => str_repeat('a', 64)];
        $items = [['line_number' => 1, 'employee_identity_snapshot' => ['employee_code' => 'SCHEMA-001'], 'income_snapshot' => ['gross_income_minor' => 1000], 'deduction_snapshot' => [], 'tax_withheld_snapshot' => ['pit_withheld_minor' => 100], 'annual_result_snapshot' => ['result_type' => 'balanced'], 'item_checksum' => str_repeat('b', 64)]];
        self::assertSame($generator->generate($header, $items), $generator->generate($header, $items));
        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $generator->generate($header, $items));
        self::assertStringContainsString('GTF-2026-1-V1', $generator->generate($header, $items));
        $xml = $generator->generate($header, $items);
        self::assertLessThan(strpos($xml, '<TaxYear>'), strpos($xml, '<Reference>'));
        self::assertLessThan(strpos($xml, '<Jurisdiction>'), strpos($xml, '<TaxYear>'));
        self::assertSame(hash('sha256', $xml), hash('sha256', $generator->generate($header, $items)));
    }

    public function test_authoritative_source_change_changes_artifact_hash(): void
    {
        $generator = new GenericXmlDeclarationGenerator;
        $header = ['reference' => 'GTF-2026-1-V1', 'tax_year' => 2026, 'jurisdiction' => 'VN', 'filing_type' => 'annual_pit_employer_declaration', 'version' => 1, 'source_checksum' => str_repeat('a', 64)];
        $changed = [...$header, 'source_checksum' => str_repeat('c', 64)];
        $items = [['line_number' => 1, 'employee_identity_snapshot' => ['employee_code' => 'SCHEMA-001'], 'income_snapshot' => ['gross_income_minor' => 1000], 'deduction_snapshot' => [], 'tax_withheld_snapshot' => ['pit_withheld_minor' => 100], 'annual_result_snapshot' => ['result_type' => 'balanced'], 'item_checksum' => str_repeat('b', 64)]];

        self::assertNotSame(hash('sha256', $generator->generate($header, $items)), hash('sha256', $generator->generate($changed, $items)));
    }
}

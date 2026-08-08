<?php

namespace Modules\Payroll\Infrastructure\Adapters;

use Modules\Payroll\Domain\Adapters\GovernmentDeclarationGeneratorInterface;

final class GenericXmlDeclarationGenerator implements GovernmentDeclarationGeneratorInterface
{
    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $items
     */
    public function generate(array $header, array $items): string
    {
        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('GovernmentTaxDeclaration');
        $xml->writeAttribute('schemaVersion', 'generic-tax-v1');
        $this->node($xml, 'Reference', $header['reference'] ?? '');
        $this->node($xml, 'TaxYear', $header['tax_year'] ?? '');
        $this->node($xml, 'Jurisdiction', $header['jurisdiction'] ?? '');
        $this->node($xml, 'FilingType', $header['filing_type'] ?? '');
        $this->node($xml, 'Version', $header['version'] ?? '');
        $this->node($xml, 'SourceChecksum', $header['source_checksum'] ?? '');
        $xml->startElement('Items');
        foreach ($items as $item) {
            $xml->startElement('Item');
            $this->node($xml, 'LineNumber', $item['line_number'] ?? '');
            foreach (['employee_identity_snapshot', 'income_snapshot', 'deduction_snapshot', 'tax_withheld_snapshot', 'annual_result_snapshot'] as $field) {
                $this->node($xml, $this->element($field), $this->canonicalJson($item[$field] ?? []));
            }
            $this->node($xml, 'ItemChecksum', $item['item_checksum'] ?? '');
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function node(\XMLWriter $xml, string $name, mixed $value): void
    {
        $xml->writeElement($name, htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    private function element(string $field): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
    }

    private function canonicalJson(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }
}

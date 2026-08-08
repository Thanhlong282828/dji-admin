<?php

namespace Modules\Payroll\Infrastructure\Adapters;

use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Payroll\Application\DTOs\ErpAdapterPayload;
use Modules\Payroll\Application\DTOs\ErpArtifact;
use Modules\Payroll\Domain\Adapters\ErpAccountingAdapter;

final readonly class GenericCsvErpAdapter implements ErpAccountingAdapter
{
    public function __construct(private SpreadsheetFormulaSanitizer $sanitizer) {}

    public function provider(): string
    {
        return 'generic_csv';
    }

    public function export(ErpAdapterPayload $payload): ErpArtifact
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create ERP export stream.');
        }
        fputcsv($stream, ['Reference', 'Journal', 'Line', 'External Account', 'Description', 'Debit Minor', 'Credit Minor', 'Currency', 'Cost Center', 'Organization Unit'], ',', '"', '');
        foreach ($payload->lines as $line) {
            fputcsv($stream, [$this->sanitizer->sanitize($payload->reference), $this->sanitizer->sanitize($payload->journalNumber), (string) $line['line_number'], $this->sanitizer->sanitize($line['account']), $this->sanitizer->sanitize($line['description']), (string) $line['debit_minor'], (string) $line['credit_minor'], $line['currency'], $this->sanitizer->sanitize($line['cost_center']), $this->sanitizer->sanitize($line['organization_unit'])], ',', '"', '');
        }
        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($bytes)) {
            throw new \RuntimeException('Unable to read ERP export stream.');
        }

        return new ErpArtifact("\xEF\xBB\xBF".$bytes, 'text/csv', 'csv');
    }
}

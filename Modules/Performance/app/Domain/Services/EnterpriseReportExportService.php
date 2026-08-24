<?php

namespace Modules\Performance\Domain\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Performance\Application\DTOs\EnterpriseReportViewModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final readonly class EnterpriseReportExportService
{
    /** @return array{bytes:string,mime:string,extension:string,checksum:string} */
    public function generate(EnterpriseReportViewModel $report, string $format): array
    {
        $bytes = match ($format) {
            'csv' => $this->csv($report),
            'xlsx' => $this->xlsx($report),
            'pdf' => Pdf::loadView('performance::reports.board-pack-pdf', ['report' => $report])->setPaper('a4')->output(),
            default => throw new \InvalidArgumentException('Unsupported report format.'),
        };
        $mime = match ($format) {
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
        };

        return ['bytes' => $bytes, 'mime' => $mime, 'extension' => $format, 'checksum' => hash('sha256', $bytes)];
    }

    private function csv(EnterpriseReportViewModel $report): string
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create report stream.');
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Enterprise Board Pack', $report->companyName, $report->periodLabel], ',', '"', '');
        fputcsv($handle, ['Schema version', $report->schemaVersion, 'Generated at', $report->generatedAt, 'Data Quality', $report->qualityScore], ',', '"', '');
        fputcsv($handle, ['Section', 'Metric', 'Label', 'Value'], ',', '"', '');
        foreach ($this->rows($report) as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $bytes = stream_get_contents($handle);
        fclose($handle);
        if (! is_string($bytes)) {
            throw new \RuntimeException('Unable to read report stream.');
        }

        return $bytes;
    }

    private function xlsx(EnterpriseReportViewModel $report): string
    {
        $spreadsheet = new Spreadsheet;
        $first = true;
        foreach ($report->sections as $section => $data) {
            if (! is_array($data)) {
                continue;
            }
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $first = false;
            $sheet->setTitle(substr(ucwords(str_replace('_', ' ', $section)), 0, 31));
            $sheet->fromArray([['Metric', 'Label', 'Value']], null, 'A1');
            $rowNumber = 2;
            foreach ($this->sectionRows($section, $data) as $row) {
                $sheet->fromArray([array_slice($row, 1)], null, 'A'.$rowNumber++);
            }
            $sheet->freezePane('A2');
            $sheet->getStyle('A1:C1')->getFont()->setBold(true);
            foreach (range('A', 'C') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'enterprise-report-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate XLSX file.');
        }
        try {
            (new Xlsx($spreadsheet))->save($path);
            $bytes = file_get_contents($path);
        } finally {
            @unlink($path);
            $spreadsheet->disconnectWorksheets();
        }
        if (! is_string($bytes)) {
            throw new \RuntimeException('Unable to read XLSX output.');
        }

        return $bytes;
    }

    /** @return list<array{string,string,string,string}> */
    private function rows(EnterpriseReportViewModel $report): array
    {
        $rows = [];
        foreach ($report->sections as $section => $data) {
            if (is_array($data)) {
                array_push($rows, ...$this->sectionRows($section, $data));
            }
        }

        return $rows;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<array{string,string,string,string}>
     */
    private function sectionRows(string $section, array $data): array
    {
        $rows = [];
        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $label = $this->safe($this->scalar($item['label'] ?? ''));
                foreach ($item as $key => $cell) {
                    if ($key !== 'label' && is_scalar($cell)) {
                        $rows[] = [$section, $this->safe('row.'.$key), $label, $this->safe((string) $cell)];
                    }
                }
            }

            return $rows;
        }
        foreach ($data as $metric => $value) {
            if (is_scalar($value) || $value === null) {
                $rows[] = [$section, $this->safe((string) $metric), '', $this->safe((string) ($value ?? 'N/A'))];

                continue;
            }
            if (! is_array($value)) {
                continue;
            }
            foreach ($value as $item) {
                if (is_array($item)) {
                    $label = $this->safe($this->scalar($item['label'] ?? ''));
                    foreach ($item as $key => $cell) {
                        if ($key !== 'label' && is_scalar($cell)) {
                            $rows[] = [$section, $this->safe((string) $metric.'.'.$key), $label, $this->safe((string) $cell)];
                        }
                    }
                }
            }
        }

        return $rows;
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

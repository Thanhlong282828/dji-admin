<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StreamingCsvResponseFactory
{
    public function __construct(private CsvCellSanitizer $sanitizer) {}

    /**
     * @param  list<string>  $headings
     * @param  iterable<int,list<bool|float|int|string|null>>  $rows
     */
    public function make(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            } fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headings);
            foreach ($rows as $row) {
                $safe = [];
                foreach ($row as $cell) {
                    $safe[] = $this->sanitizer->sanitize($cell);
                }
                fputcsv($output, $safe);
            } fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}

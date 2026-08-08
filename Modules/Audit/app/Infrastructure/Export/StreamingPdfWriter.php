<?php

namespace Modules\Audit\Infrastructure\Export;

use RuntimeException;

final class StreamingPdfWriter
{
    /**
     * @param  resource  $stream
     * @param  iterable<array{uuid:string,occurred_at:string,action:string,resource:string,checksum:string}>  $rows
     */
    public function write($stream, iterable $rows): int
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('PDF target stream is invalid.');
        }
        fwrite($stream, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");
        $offsets = [];
        $this->object($stream, 3, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', $offsets);
        $pageIds = [];
        $lines = [];
        $count = 0;
        $nextObject = 4;
        foreach ($rows as $row) {
            $lines[] = sprintf('%s | %s | %s | %s | %s', $row['occurred_at'], $row['action'], $row['resource'], $row['uuid'], $row['checksum']);
            $count++;
            if (count($lines) === 36) {
                $pageIds[] = $this->page($stream, $nextObject, $lines, $offsets);
                $nextObject += 2;
                $lines = [];
            }
        }
        if ($lines !== [] || $pageIds === []) {
            $pageIds[] = $this->page($stream, $nextObject, $lines, $offsets);
            $nextObject += 2;
        }
        $kids = implode(' ', array_map(static fn (int $id): string => $id.' 0 R', $pageIds));
        $this->object($stream, 1, '<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>', $offsets);
        $this->object($stream, 2, '<< /Type /Catalog /Pages 1 0 R >>', $offsets);
        $xref = ftell($stream);
        if (! is_int($xref)) {
            throw new RuntimeException('Unable to determine PDF cross-reference position.');
        }
        $size = $nextObject;
        fwrite($stream, "xref\n0 {$size}\n0000000000 65535 f \n");
        for ($id = 1; $id < $size; $id++) {
            fwrite($stream, sprintf("%010d 00000 n \n", $offsets[$id] ?? 0));
        }
        fwrite($stream, "trailer\n<< /Size {$size} /Root 2 0 R >>\nstartxref\n{$xref}\n%%EOF\n");

        return $count;
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $lines
     * @param  array<int, int>  $offsets
     */
    private function page($stream, int $contentId, array $lines, array &$offsets): int
    {
        $pageId = $contentId + 1;
        $commands = ['BT /F1 8 Tf 36 806 Td 10 TL', '(Enterprise Audit Compliance Export) Tj T*'];
        foreach ($lines as $line) {
            $commands[] = '('.$this->escape($line).') Tj T*';
        }
        $commands[] = 'ET';
        $content = implode("\n", $commands);
        $this->object($stream, $contentId, '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream", $offsets);
        $this->object($stream, $pageId, "<< /Type /Page /Parent 1 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>", $offsets);

        return $pageId;
    }

    /**
     * @param  resource  $stream
     * @param  array<int, int>  $offsets
     */
    private function object($stream, int $id, string $body, array &$offsets): void
    {
        $position = ftell($stream);
        if (! is_int($position)) {
            throw new RuntimeException('Unable to determine PDF object position.');
        }
        $offsets[$id] = $position;
        fwrite($stream, "{$id} 0 obj\n{$body}\nendobj\n");
    }

    private function escape(string $value): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], substr($ascii, 0, 190));
    }
}

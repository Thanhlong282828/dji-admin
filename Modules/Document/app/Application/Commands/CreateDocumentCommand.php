<?php

namespace Modules\Document\Application\Commands;

use Illuminate\Http\UploadedFile;

final readonly class CreateDocumentCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data, public UploadedFile $file, public int $actorId) {}
}

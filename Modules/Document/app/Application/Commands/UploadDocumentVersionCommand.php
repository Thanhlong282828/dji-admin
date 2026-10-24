<?php

namespace Modules\Document\Application\Commands;

use Illuminate\Http\UploadedFile;

final readonly class UploadDocumentVersionCommand
{
    public function __construct(public int $companyId, public int $documentId, public UploadedFile $file, public int $actorId) {}
}

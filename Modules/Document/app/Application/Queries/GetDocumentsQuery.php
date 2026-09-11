<?php

namespace Modules\Document\Application\Queries;

final readonly class GetDocumentsQuery
{
    public function __construct(public int $companyId, public ?int $documentId = null) {}
}

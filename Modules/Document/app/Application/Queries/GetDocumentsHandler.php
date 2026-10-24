<?php

namespace Modules\Document\Application\Queries;

use Modules\Document\Application\Services\EnterpriseDocumentService;

final readonly class GetDocumentsHandler
{
    public function __construct(private EnterpriseDocumentService $service) {}

    /** @return array<string,mixed> */
    public function handle(GetDocumentsQuery $query): array
    {
        return $this->service->read($query->companyId, $query->documentId);
    }
}

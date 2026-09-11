<?php

namespace Modules\Article\Application\Commands;

use Modules\Article\Application\Services\ArticleWriteService;

final readonly class SaveArticleHandler
{
    public function __construct(private ArticleWriteService $service) {}

    public function handle(SaveArticleCommand $command): int
    {
        return $this->service->save($command->companyId, $command->articleId, $command->data, $command->featured, $command->staged, $command->removeMediaIds, $command->actor);
    }
}

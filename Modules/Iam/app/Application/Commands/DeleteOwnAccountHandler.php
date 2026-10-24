<?php

namespace Modules\Iam\Application\Commands;

final class DeleteOwnAccountHandler
{
    public function handle(DeleteOwnAccountCommand $command): int
    {
        $id = $command->user->id;
        $command->user->delete();

        return $id;
    }
}

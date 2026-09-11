<?php

namespace Modules\Iam\Application\Commands;

final class UpdateProfileHandler
{
    public function handle(UpdateProfileCommand $command): int
    {
        $command->user->fill($command->attributes);
        if ($command->user->isDirty('email')) {
            $command->user->email_verified_at = null;
        }
        $command->user->save();

        return $command->user->id;
    }
}

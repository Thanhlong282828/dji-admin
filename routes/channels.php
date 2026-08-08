<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Organization\Domain\Services\TenantAccessService;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('company.{companyId}', fn ($user, int $companyId): bool => app(TenantAccessService::class)->canAccess($user, $companyId));

<?php

namespace Modules\Iam\Application\DTOs;

final readonly class UserAccess360ViewModel
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $directPermissions
     * @param  list<array{id:int,type:string,entitlement_id:int,status:string,privileged:bool,effective_from:string,effective_to:string|null,effective:bool}>  $assignments
     * @param  list<array{action:string,occurred_at:string}>  $history
     */
    public function __construct(public int $userId, public string $name, public string $email, public bool $companyAccess, public array $roles, public array $directPermissions, public array $assignments, public int $openRequests, public int $openReviews, public array $history) {}
}

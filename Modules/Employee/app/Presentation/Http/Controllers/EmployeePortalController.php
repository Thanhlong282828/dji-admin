<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use App\Support\Employee\EmployeePortalReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class EmployeePortalController
{
    public function __construct(private EmployeePortalReadService $portal) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('employee-portal.index', $this->portal->forUser($user->id));
    }
}

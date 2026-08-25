<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Compensation\CompensationReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final readonly class CompensationReferenceController
{
    public function __construct(private CompensationReadService $compensation) {}

    public function __invoke(Request $request): View
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $user->can('payroll.assignment.view'), 403);
        abort_unless($user->can('organization.tenant-all') || DB::table('company_user_access')->where('company_id', $companyId)->where('user_id', $user->id)->exists(), 404);

        return view('compensation.reference', ['rows' => $this->compensation->positionReferences($companyId)]);
    }
}

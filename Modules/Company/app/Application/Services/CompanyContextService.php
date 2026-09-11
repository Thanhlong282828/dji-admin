<?php

namespace Modules\Company\Application\Services;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Modules\Company\Domain\Services\BranchAccessService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CompanyContextService
{
    public function __construct(private DatabaseManager $db, private BranchAccessService $access) {}

    public function companyId(Request $request, User $user): int
    {
        $companyId = $request->integer('company_id');
        if ($companyId < 1) {
            $value = $this->db->table('company_user_access')->where('user_id', $user->id)->orderBy('company_id')->value('company_id');
            $companyId = is_numeric($value) ? (int) $value : 0;
        }
        if ($companyId < 1 || ! $this->access->company($user, $companyId)) {
            throw new NotFoundHttpException;
        }

        return $companyId;
    }
}

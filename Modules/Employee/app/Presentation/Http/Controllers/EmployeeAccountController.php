<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Employee\Domain\Services\EmployeeAccountService;
use Modules\Employee\Policies\EmployeeUserLinkPolicy;
use Modules\Employee\Presentation\Http\Requests\ManageEmployeeAccountRequest;

final readonly class EmployeeAccountController
{
    public function __construct(private EmployeeAccountService $accounts, private EmployeeUserLinkPolicy $policy) {}

    public function save(ManageEmployeeAccountRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->link($user, $companyId), 403);
        $password = $request->validated('account_password');
        $email = $request->validated('account_email');
        $role = $request->validated('role');
        abort_unless(is_string($email) && is_string($role), 422);
        $branchIds = $this->integerList($request->validated('branch_ids', []));
        $data = ['email' => $email, 'role' => $role, 'access_enabled' => $request->boolean('access_enabled'), 'verified' => $request->boolean('verified'), 'branch_ids' => $branchIds];
        if (is_string($password) && $password !== '') {
            $data['password'] = $password;
        }
        $this->accounts->save($companyId, $employee, $data, $user->id);

        return back()->with('success', 'Đã lưu tài khoản đăng nhập và phạm vi công ty.');
    }

    public function access(Request $request, int $employee, string $state): RedirectResponse
    {
        abort_unless(in_array($state, ['enable', 'disable'], true), 404);
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->unlink($user, $companyId), 403);
        $this->accounts->setAccess($companyId, $employee, $state === 'enable', $user->id);

        return back()->with('success', $state === 'enable' ? 'Đã mở lại đăng nhập.' : 'Đã vô hiệu hóa đăng nhập; hồ sơ nhân viên được giữ nguyên.');
    }

    /** @return list<int> */
    private function integerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            if (is_int($id)) {
                $ids[] = $id;
            } elseif (is_string($id) && ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}

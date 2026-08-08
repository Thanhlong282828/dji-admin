<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Employee\Application\Commands\LinkEmployeeUser\LinkEmployeeUserCommand;
use Modules\Employee\Application\Commands\SendEmployeeUserInvitation\SendEmployeeUserInvitationCommand;
use Modules\Employee\Application\Commands\UnlinkEmployeeUser\UnlinkEmployeeUserCommand;
use Modules\Employee\Policies\EmployeeUserLinkPolicy;
use Modules\Employee\Presentation\Http\Requests\LinkEmployeeUserRequest;
use Modules\Employee\Presentation\Http\Requests\SendEmployeeUserInvitationRequest;

final readonly class EmployeeUserLinkController
{
    public function __construct(private CommandBusInterface $commands, private EmployeeUserLinkPolicy $policy) {}

    public function store(LinkEmployeeUserRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->link($user, $companyId), 403);
        $this->commands->dispatch(new LinkEmployeeUserCommand($companyId, $employee, $request->integer('user_id'), $user->id));

        return back()->with('success', 'Đã liên kết tài khoản.');
    }

    public function invite(SendEmployeeUserInvitationRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        $email = $request->validated('email');
        abort_unless($user instanceof User && $this->policy->invite($user, $companyId) && is_string($email), 403);
        $this->commands->dispatch(new SendEmployeeUserInvitationCommand($companyId, $employee, $email, $user->id));

        return back()->with('success', 'Đã xếp hàng gửi lời mời bảo mật.');
    }

    public function destroy(Request $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->unlink($user, $companyId), 403);
        $this->commands->dispatch(new UnlinkEmployeeUserCommand($companyId, $employee, $user->id));

        return back()->with('success', 'Đã hủy liên kết tài khoản; tài khoản người dùng không bị xóa.');
    }
}

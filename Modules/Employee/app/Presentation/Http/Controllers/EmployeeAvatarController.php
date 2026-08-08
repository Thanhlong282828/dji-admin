<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Employee\Application\Commands\RemoveEmployeeAvatar\RemoveEmployeeAvatarCommand;
use Modules\Employee\Application\Commands\UploadEmployeeAvatar\UploadEmployeeAvatarCommand;
use Modules\Employee\Presentation\Http\Requests\UploadEmployeeAvatarRequest;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class EmployeeAvatarController
{
    public function __construct(private CommandBusInterface $commands, private TenantAccessService $tenant) {}

    public function store(UploadEmployeeAvatarRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('employee.manage-avatar') && $this->tenant->canAccess($user, $companyId), 403);
        $file = $request->file('avatar');
        abort_unless($file instanceof UploadedFile, 422);
        $this->commands->dispatch(new UploadEmployeeAvatarCommand($companyId, $employee, $file, $user->id));

        return back()->with('success', 'Đã cập nhật ảnh đại diện.');
    }

    public function destroy(Request $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('employee.manage-avatar') && $this->tenant->canAccess($user, $companyId), 403);
        $this->commands->dispatch(new RemoveEmployeeAvatarCommand($companyId, $employee, $user->id));

        return back()->with('success', 'Đã gỡ ảnh đại diện.');
    }
}

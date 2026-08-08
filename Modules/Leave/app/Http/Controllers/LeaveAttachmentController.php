<?php

namespace Modules\Leave\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Leave\Application\Commands\ManageLeaveAttachmentCommand;
use Modules\Leave\Application\Queries\DownloadLeaveAttachmentQuery;
use Modules\Leave\Presentation\Http\Requests\LeaveAttachmentRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class LeaveAttachmentController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function upload(LeaveAttachmentRequest $request, int $leaveRequest, string $action): RedirectResponse
    {
        return $this->run($request, $leaveRequest, $action, null);
    }

    public function mutate(LeaveAttachmentRequest $request, int $leaveRequest, int $attachment, string $action): RedirectResponse
    {
        return $this->run($request, $leaveRequest, $action, $attachment);
    }

    private function run(LeaveAttachmentRequest $request, int $leaveRequest, string $action, ?int $attachment): RedirectResponse
    {
        $companyId = $this->company($request, 'leave.request');
        $arguments = $attachment === null ? [$companyId, $leaveRequest] : [$companyId, $leaveRequest, $attachment];
        Gate::authorize('leave-attachment.'.$action.'-record', $arguments);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->commands->dispatch(new ManageLeaveAttachmentCommand($action, $companyId, $leaveRequest, $attachment, $request->file('attachment'), $request->integer('version', 1), $request->string('idempotency_key')->toString(), $user->id));

        return back()->with('success', 'Leave attachment đã được cập nhật an toàn.');
    }

    public function download(Request $request, int $leaveRequest, int $attachment): StreamedResponse
    {
        $companyId = $this->company($request, 'leave.view');
        Gate::authorize('leave-attachment.download-record', [$companyId, $leaveRequest, $attachment]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $response = $this->queries->ask(new DownloadLeaveAttachmentQuery($companyId, $leaveRequest, $attachment, $user->id));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    private function company(Request $request, string $permission): int
    {
        $id = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::forUser($user)->authorize('workforce.company', [$permission, $id]);

        return $id;
    }
}

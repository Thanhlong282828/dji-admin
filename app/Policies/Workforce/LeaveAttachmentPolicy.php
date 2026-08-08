<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class LeaveAttachmentPolicy extends AbstractWorkforcePolicy
{
    public function upload(User $u, int $c, int $request): bool
    {
        return app(LeaveRequestPolicy::class)->update($u, $c, $request);
    }

    public function replace(User $u, int $c, int $request, int $attachment): bool
    {
        return $this->upload($u, $c, $request) && $this->record($u, 'leave.request', $c, 'leave_attachments', $attachment) && $this->status('leave_attachments', $attachment) === 'clean';
    }

    public function remove(User $u, int $c, int $request, int $attachment): bool
    {
        return $this->replace($u, $c, $request, $attachment);
    }

    public function download(User $u, int $c, int $request, int $attachment): bool
    {
        return app(LeaveRequestPolicy::class)->view($u, $c, $request) && $this->record($u, 'leave.view', $c, 'leave_attachments', $attachment) && $this->status('leave_attachments', $attachment) === 'clean' && $this->scan($attachment) === 'clean';
    }

    private function scan(int $id): string
    {
        $v = DB::table('leave_attachments')->where('id', $id)->value('scan_status');

        return is_string($v) ? $v : '';
    }
}

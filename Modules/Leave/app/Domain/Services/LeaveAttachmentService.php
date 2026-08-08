<?php

namespace Modules\Leave\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Leave\Application\Commands\ManageLeaveAttachmentCommand;
use Modules\Leave\Application\Queries\DownloadLeaveAttachmentQuery;
use Modules\Leave\Domain\Enums\MalwareScanStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class LeaveAttachmentService
{
    public function __construct(private ConnectionInterface $db, private MalwareScannerInterface $scanner) {}

    public function execute(ManageLeaveAttachmentCommand $command): int
    {
        return match ($command->action) {
            'upload', 'replace' => $this->store($command), 'remove' => $this->remove($command), default => throw ValidationException::withMessages(['action' => 'Attachment action không hợp lệ.'])
        };
    }

    private function store(ManageLeaveAttachmentCommand $command): int
    {
        $file = $command->file;
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages(['attachment' => 'File upload không hợp lệ.']);
        }
        $mime = $file->getMimeType();
        $allowed = config('leave.attachments.allowed_mimes', []);
        if (! is_string($mime) || ! is_array($allowed) || ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['attachment' => 'MIME không được phép.']);
        }
        $maximum = config('leave.attachments.max_size_bytes', 10485760);
        if (! is_int($maximum) || $file->getSize() > $maximum) {
            throw ValidationException::withMessages(['attachment' => 'File vượt giới hạn dung lượng.']);
        }
        $request = $this->leaveRequest($command->companyId, $command->leaveRequestId);
        if (! in_array($this->text($request, 'status'), ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages(['attachment' => 'Chỉ draft/submitted mới được thay attachment.']);
        }
        $existing = $this->db->table('leave_attachments')->where('company_id', $command->companyId)->where('idempotency_key', $command->idempotencyKey)->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }
        $disk = $this->disk();
        $quarantine = $this->configString('quarantine_path');
        $uuid = (string) Str::uuid();
        $extension = match ($mime) {
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'bin'
        };
        $key = $quarantine.'/'.$uuid.'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false || ! $disk->put($key, $stream)) {
            throw ValidationException::withMessages(['attachment' => 'Không thể lưu file private.']);
        } if (is_resource($stream)) {
            fclose($stream);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        if (! is_string($hash)) {
            $disk->delete($key);
            throw ValidationException::withMessages(['attachment' => 'Không thể tính SHA-256.']);
        }
        $result = $this->scanner->scan($disk->path($key));
        if ($result->status === MalwareScanStatus::Clean) {
            $cleanKey = $this->configString('clean_path').'/'.$uuid.'.'.$extension;
            if (! $disk->move($key, $cleanKey)) {
                throw ValidationException::withMessages(['attachment' => 'Không thể chuyển file sạch vào private storage.']);
            } $key = $cleanKey;
        }

        return $this->db->transaction(function () use ($command, $file, $mime, $hash, $key, $result): int {
            $old = null;
            if ($command->action === 'replace') {
                $old = $this->attachment($command->companyId, $command->leaveRequestId, (int) $command->attachmentId);
                $this->version($old, $command->expectedVersion);
            }
            $id = (int) $this->db->table('leave_attachments')->insertGetId(['company_id' => $command->companyId, 'leave_request_id' => $command->leaveRequestId, 'idempotency_key' => $command->idempotencyKey, 'storage_key' => $key, 'original_name' => $this->safeName($file->getClientOriginalName()), 'mime_type' => $mime, 'size_bytes' => $file->getSize(), 'sha256' => $hash, 'scan_status' => $result->status->value, 'status' => $result->status === MalwareScanStatus::Clean ? 'clean' : 'quarantined', 'version' => 1, 'uploaded_by' => $command->actorId, 'scanned_at' => $result->status === MalwareScanStatus::Pending ? null : now(), 'created_at' => now(), 'updated_at' => now()]);
            if ($old !== null) {
                $this->db->table('leave_attachments')->where('id', $this->int($old, 'id'))->update(['status' => 'replaced', 'replaced_by_attachment_id' => $id, 'version' => $command->expectedVersion + 1, 'updated_at' => now()]);
            }
            activity('leave-attachment')->causedBy($command->actorId)->event($command->action)->withProperties(['company_id' => $command->companyId, 'leave_request_id' => $command->leaveRequestId, 'attachment_id' => $id, 'scan_status' => $result->status->value])->log('Leave attachment '.$command->action);

            return $id;
        }, 3);
    }

    private function remove(ManageLeaveAttachmentCommand $command): int
    {
        return $this->db->transaction(function () use ($command): int {
            $request = $this->leaveRequest($command->companyId, $command->leaveRequestId);
            if ($this->text($request, 'status') === 'approved') {
                throw ValidationException::withMessages(['attachment' => 'Approved attachment removal bị chặn bởi retention policy.']);
            } $row = $this->attachment($command->companyId, $command->leaveRequestId, (int) $command->attachmentId);
            $this->version($row, $command->expectedVersion);
            $id = $this->int($row, 'id');
            $this->db->table('leave_attachments')->where('id', $id)->update(['status' => 'removed', 'removed_at' => now(), 'removed_by' => $command->actorId, 'version' => $command->expectedVersion + 1, 'updated_at' => now()]);
            activity('leave-attachment')->causedBy($command->actorId)->event('remove')->withProperties(['company_id' => $command->companyId, 'leave_request_id' => $command->leaveRequestId, 'attachment_id' => $id])->log('Leave attachment removed');

            return $id;
        }, 3);
    }

    public function download(DownloadLeaveAttachmentQuery $query): StreamedResponse
    {
        $this->leaveRequest($query->companyId, $query->leaveRequestId);
        $row = $this->attachment($query->companyId, $query->leaveRequestId, $query->attachmentId);
        if ($this->text($row, 'status') !== 'clean' || $this->text($row, 'scan_status') !== MalwareScanStatus::Clean->value || ($row->removed_at ?? null) !== null) {
            abort(404);
        }
        $key = $this->text($row, 'storage_key');
        $disk = $this->disk();
        abort_unless($disk->exists($key), 404);
        $response = $disk->download($key, $this->safeName($this->text($row, 'original_name')), ['Content-Type' => $this->text($row, 'mime_type'), 'X-Content-Type-Options' => 'nosniff']);
        activity('leave-attachment')->causedBy($query->actorId)->event('download')->withProperties(['company_id' => $query->companyId, 'leave_request_id' => $query->leaveRequestId, 'attachment_id' => $query->attachmentId])->log('Leave attachment downloaded');

        return $response;
    }

    private function disk(): FilesystemAdapter
    {
        $name = config('leave.attachments.disk', 'local');

        return Storage::disk(is_string($name) ? $name : 'local');
    }

    private function configString(string $key): string
    {
        $value = config('leave.attachments.'.$key);
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages(['attachment' => 'Attachment storage configuration không hợp lệ.']);
        }

        return trim($value, '/');
    }

    private function safeName(string $name): string
    {
        $safe = preg_replace('/[^\pL\pN._ -]+/u', '_', basename($name));

        return is_string($safe) && $safe !== '' ? $safe : 'attachment';
    }

    private function leaveRequest(int $companyId, int $id): object
    {
        $row = $this->db->table('leave_requests')->where('company_id', $companyId)->where('id', $id)->first();
        abort_if($row === null, 404);

        return $row;
    }

    private function attachment(int $companyId, int $requestId, int $id): object
    {
        $row = $this->db->table('leave_attachments')->where('company_id', $companyId)->where('leave_request_id', $requestId)->where('id', $id)->first();
        abort_if($row === null, 404);

        return $row;
    }

    private function version(object $row, int $expected): void
    {
        if ($this->int($row, 'version') !== $expected) {
            throw ValidationException::withMessages(['version' => 'Attachment đã thay đổi.']);
        }
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}

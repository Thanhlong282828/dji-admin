<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Employee\Domain\Enums\DocumentClassification;
use Modules\Employee\Domain\Services\EmployeeDocumentService;
use Modules\Employee\Policies\EmployeeDocumentPolicy;
use Modules\Employee\Presentation\Http\Requests\UploadEmployeeDocumentRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final readonly class EmployeeDocumentController
{
    public function __construct(private EmployeeDocumentService $documents, private EmployeeDocumentPolicy $policy) {}

    public function store(UploadEmployeeDocumentRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->upload($user, $companyId), 403);
        $file = $request->file('document');
        $type = $request->validated('type');
        $name = $request->validated('name');
        abort_unless($file instanceof UploadedFile && is_string($type) && is_string($name), 422);
        $this->documents->upload($companyId, $employee, DocumentClassification::from($type), $name, $file, (int) $user->id);

        return back()->with('success', 'Đã tải tài liệu riêng tư.');
    }

    public function download(Request $request, int $document): BinaryFileResponse|SymfonyRedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->download($user, $companyId), 403);
        $record = $this->documents->download($companyId, $document);
        activity('employee-document')->causedBy($user)->event('downloaded')->withProperties(['company_id' => $companyId, 'document_id' => $document])->log('Employee document downloaded');
        $location = $this->documents->downloadLocation($record);
        if ($location['temporary_url'] !== null) {
            return redirect()->away($location['temporary_url']);
        }

        return response()->download(Storage::disk($location['disk'])->path($location['path']), $record->name, ['Content-Type' => $record->mime_type ?? 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function archive(Request $request, int $document): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->archive($user, $companyId), 403);
        $this->documents->archive($companyId, $document);

        return back()->with('success', 'Đã lưu trữ tài liệu.');
    }
}

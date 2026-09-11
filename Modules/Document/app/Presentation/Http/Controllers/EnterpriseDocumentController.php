<?php

namespace Modules\Document\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Document\Application\Commands\ArchiveDocumentCommand;
use Modules\Document\Application\Commands\CreateDocumentCommand;
use Modules\Document\Application\Commands\UploadDocumentVersionCommand;
use Modules\Document\Application\Queries\GetDocumentsQuery;
use Modules\Document\Application\Services\EnterpriseDocumentService;
use Modules\Document\Presentation\Http\Requests\DocumentRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class EnterpriseDocumentController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly CommandBusInterface $commands, private readonly BranchAccessService $access, private readonly EnterpriseDocumentService $documents) {}

    public function index(Request $request): View
    {
        return $this->view($request, null);
    }

    public function show(Request $request, int $document): View
    {
        return $this->view($request, $document);
    }

    public function store(DocumentRequest $request): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);
        $id = $this->commands->dispatch(new CreateDocumentCommand($request->validated(), $file, (int) $user->id));

        return to_route('admin.documents.show', ['document' => $id, 'company_id' => $company])->with('success', 'Đã tạo tài liệu và phiên bản đầu tiên.');
    }

    public function upload(Request $request, int $document): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $request->validate(['document' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp']]);
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);
        $this->commands->dispatch(new UploadDocumentVersionCommand($company, $document, $file, (int) $user->id));

        return back()->with('success', 'Đã thêm phiên bản mới.');
    }

    public function archive(Request $request, int $document): RedirectResponse
    {
        [$user, $company] = $this->actor($request);
        $this->commands->dispatch(new ArchiveDocumentCommand($company, $document, (int) $user->id));

        return to_route('admin.documents.index', ['company_id' => $company])->with('success', 'Đã lưu trữ tài liệu.');
    }

    public function download(Request $request, int $version): BinaryFileResponse
    {
        [$user, $company] = $this->actor($request);
        $file = $this->documents->downloadPath($company, $version);
        activity('enterprise-document')->causedBy($user)->event('downloaded')->withProperties(['company_id' => $company, 'version_id' => $version])->log('Enterprise document downloaded');

        return response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']]);
    }

    private function view(Request $request, ?int $document): View
    {
        [, $company] = $this->actor($request);
        $data = $this->queries->ask(new GetDocumentsQuery($company, $document));

        return view($document === null ? 'document::index' : 'document::show', compact('company', 'data'));
    }

    /** @return array{User,int} */
    private function actor(Request $request): array
    {
        $company = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $company > 0 && $this->access->company($user, $company), 404);

        return [$user, $company];
    }
}

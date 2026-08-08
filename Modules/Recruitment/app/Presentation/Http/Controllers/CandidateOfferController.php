<?php

namespace Modules\Recruitment\Presentation\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Domain\Enums\ContractType;
use Modules\Employee\Domain\Enums\EmploymentType;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Application\Commands\CreateCandidateOffer\CreateCandidateOfferCommand;
use Modules\Recruitment\Application\Commands\HireCandidate\HireCandidateCommand;
use Modules\Recruitment\Application\Commands\RemoveCandidateOfferDocument\RemoveCandidateOfferDocumentCommand;
use Modules\Recruitment\Application\Commands\TransitionCandidateOffer\TransitionCandidateOfferCommand;
use Modules\Recruitment\Application\Commands\UploadCandidateOfferDocument\UploadCandidateOfferDocumentCommand;
use Modules\Recruitment\Application\DTOs\CandidateOfferData;
use Modules\Recruitment\Application\DTOs\HireCandidateData;
use Modules\Recruitment\Application\DTOs\HireCandidatePreview;
use Modules\Recruitment\Application\DTOs\HireCandidateResult;
use Modules\Recruitment\Application\Queries\GetCandidateOfferDetail\GetCandidateOfferDetailQuery;
use Modules\Recruitment\Application\Queries\GetCandidateOfferDocument\GetCandidateOfferDocumentQuery;
use Modules\Recruitment\Application\Queries\GetCandidateOfferList\GetCandidateOfferListQuery;
use Modules\Recruitment\Application\Queries\GetHireCandidatePreview\GetHireCandidatePreviewQuery;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Presentation\Http\Requests\HireCandidateRequest;
use Modules\Recruitment\Presentation\Http\Requests\StoreCandidateOfferRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class CandidateOfferController
{
    public function __construct(
        private CommandBusInterface $commands,
        private QueryBusInterface $queries,
        private TenantAccessService $tenant,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'candidate-offer.view');

        return view('recruitment::offers.index', ['items' => $this->queries->ask(new GetCandidateOfferListQuery($companyId)), 'companyId' => $companyId]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->company($request, 'candidate-offer.create');

        return view('recruitment::offers.create', ['companyId' => $companyId]);
    }

    public function store(StoreCandidateOfferRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate-offer.create');
        $terms = $request->input('terms', []);
        $normalizedTerms = [];
        if (is_array($terms)) {
            foreach ($terms as $key => $value) {
                if (is_string($key) && (is_string($value) || is_int($value) || is_bool($value) || $value === null)) {
                    $normalizedTerms[$key] = $value;
                }
            }
        }
        $data = new CandidateOfferData(
            $request->integer('application_id'),
            $request->string('employment_type')->toString(),
            $request->string('base_salary')->toString(),
            strtoupper($request->string('currency')->toString()),
            CarbonImmutable::parse($request->string('start_date')->toString()),
            $request->filled('probation_end_date') ? CarbonImmutable::parse($request->string('probation_end_date')->toString()) : null,
            $request->filled('expires_at') ? CarbonImmutable::parse($request->string('expires_at')->toString()) : null,
            $normalizedTerms,
        );
        $offer = $this->commands->dispatch(new CreateCandidateOfferCommand($companyId, $data, $this->actor($request)));
        abort_unless($offer instanceof CandidateOffer, 500);

        return redirect()->route('admin.candidate-offers.show', ['candidate_offer' => $offer->id, 'company_id' => $companyId])->with('success', 'Đã tạo offer draft.');
    }

    public function show(Request $request, int $candidateOffer): View
    {
        $companyId = $this->company($request, 'candidate-offer.view');
        $user = $request->user();

        return view('recruitment::offers.show', [
            'offer' => $this->queries->ask(new GetCandidateOfferDetailQuery($companyId, $candidateOffer, $user instanceof User && $user->can('candidate-offer.view-salary'))),
            'companyId' => $companyId,
        ]);
    }

    public function hireForm(Request $request, int $candidateOffer): View
    {
        $companyId = $this->company($request, 'candidate-offer.hire');
        $user = $request->user();
        $preview = $this->queries->ask(new GetHireCandidatePreviewQuery($companyId, $candidateOffer, $user instanceof User && $user->can('candidate-offer.view-salary')));
        abort_unless($preview instanceof HireCandidatePreview, 500);

        return view('recruitment::offers.hire', [
            'preview' => $preview,
            'companyId' => $companyId,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function hire(HireCandidateRequest $request, int $candidateOffer): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate-offer.hire');
        $data = new HireCandidateData(
            $request->integer('expected_offer_version'),
            $request->integer('expected_application_version'),
            $request->filled('employee_number') ? $request->string('employee_number')->toString() : null,
            CarbonImmutable::parse($request->string('start_date')->toString()),
            $request->integer('organization_unit_id'),
            $request->integer('job_position_id'),
            EmploymentType::from($request->string('employment_type')->toString()),
            $request->filled('work_location') ? $request->string('work_location')->toString() : null,
            $request->filled('manager_user_id') ? $request->integer('manager_user_id') : null,
            ContractType::from($request->string('contract_type')->toString()),
            $request->filled('contract_end_date') ? CarbonImmutable::parse($request->string('contract_end_date')->toString()) : null,
            $request->filled('onboarding_template_code') ? $request->string('onboarding_template_code')->toString() : null,
            $request->string('reason')->trim()->toString(),
            $request->string('idempotency_key')->toString(),
        );
        $result = $this->commands->dispatch(new HireCandidateCommand($companyId, $candidateOffer, $data, $this->actor($request)));
        abort_unless($result instanceof HireCandidateResult, 500);

        return redirect()->route('admin.candidate-offers.show', ['candidate_offer' => $result->offerId, 'company_id' => $companyId])
            ->with('success', $result->alreadyCompleted ? 'Candidate đã được hire trước đó; không tạo dữ liệu trùng.' : 'Đã hire candidate và khởi tạo onboarding thành công.');
    }

    public function transition(Request $request, int $candidateOffer, CandidateOfferStatus $target): RedirectResponse
    {
        $permission = match ($target) {
            CandidateOfferStatus::PendingApproval => 'candidate-offer.submit',
            CandidateOfferStatus::Approved => 'candidate-offer.approve',
            CandidateOfferStatus::Sent => 'candidate-offer.send',
            CandidateOfferStatus::Accepted => 'candidate-offer.accept',
            CandidateOfferStatus::Declined => 'candidate-offer.decline',
            CandidateOfferStatus::Withdrawn => 'candidate-offer.withdraw',
            CandidateOfferStatus::Expired => 'candidate-offer.withdraw',
            CandidateOfferStatus::Draft => 'candidate-offer.update',
        };
        $companyId = $this->company($request, $permission);
        $request->validate(['reason' => ['nullable', 'string', 'max:2000'], 'version' => ['nullable', 'integer', 'min:1']]);
        $reason = $request->string('reason')->trim()->toString();
        $this->commands->dispatch(new TransitionCandidateOfferCommand(
            $companyId,
            $candidateOffer,
            $target,
            $this->actor($request),
            $reason === '' ? null : $reason,
            $request->filled('version') ? $request->integer('version') : null,
        ));

        return back()->with('success', 'Đã cập nhật trạng thái offer.');
    }

    public function uploadDocument(Request $request, int $candidateOffer): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate-offer.update');
        $request->validate(['document' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf']]);
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);
        $this->commands->dispatch(new UploadCandidateOfferDocumentCommand($companyId, $candidateOffer, $file, $this->actor($request)));

        return back()->with('success', 'Đã lưu tài liệu Offer vào private storage.');
    }

    public function downloadDocument(Request $request, int $candidateOffer): BinaryFileResponse
    {
        $companyId = $this->company($request, 'candidate-offer.download');
        $offer = $this->queries->ask(new GetCandidateOfferDocumentQuery($companyId, $candidateOffer));
        abort_unless($offer instanceof CandidateOffer && is_string($offer->document_storage_key), 404);

        return response()->download(Storage::disk('local')->path($offer->document_storage_key), $offer->offer_number.'.pdf', ['Content-Type' => $offer->document_mime_type ?? 'application/pdf']);
    }

    public function removeDocument(Request $request, int $candidateOffer): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate-offer.update');
        $this->commands->dispatch(new RemoveCandidateOfferDocumentCommand($companyId, $candidateOffer, $this->actor($request)));

        return back()->with('success', 'Đã gỡ tài liệu Offer.');
    }

    private function company(Request $request, string $permission): int
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $user->can($permission) && $this->tenant->canAccess($user, $companyId), 403);

        return $companyId;
    }

    private function actor(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user->id;
    }
}

<?php

namespace Modules\Recruitment\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Application\DTOs\RecruitmentExportData;
use Modules\Recruitment\Application\Queries\GetCandidateOfferExport\GetCandidateOfferExportQuery;
use Modules\Recruitment\Application\Queries\GetInterviewExport\GetInterviewExportQuery;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class RecruitmentExportController
{
    public function __construct(private QueryBusInterface $queries, private TenantAccessService $tenant, private SpreadsheetFormulaSanitizer $sanitizer) {}

    public function interviews(Request $request): StreamedResponse
    {
        [$companyId, $user] = $this->context($request, 'interview.view');
        $data = $this->queries->ask(new GetInterviewExportQuery($companyId));
        abort_unless($data instanceof RecruitmentExportData, 500);

        return $this->stream($data, $user, $companyId, 'interviews.exported');
    }

    public function offers(Request $request): StreamedResponse
    {
        [$companyId, $user] = $this->context($request, 'candidate-offer.view');
        $data = $this->queries->ask(new GetCandidateOfferExportQuery($companyId, $user->can('candidate-offer.view-salary')));
        abort_unless($data instanceof RecruitmentExportData, 500);

        return $this->stream($data, $user, $companyId, 'candidate-offers.exported');
    }

    private function stream(RecruitmentExportData $data, User $user, int $companyId, string $event): StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $user, $companyId, $event): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open recruitment export stream.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map($this->sanitizer->sanitize(...), $data->headings));
            $count = 0;
            foreach ($data->rows as $row) {
                fputcsv($stream, array_map($this->sanitizer->sanitize(...), $row));
                $count++;
            }
            fclose($stream);
            activity('recruitment-export')->causedBy($user)->event($event)->withProperties(['company_id' => $companyId, 'format' => 'csv', 'row_count' => $count])->log('Recruitment data exported');
        }, $data->filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{int, User} */
    private function context(Request $request, string $permission): array
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $user->can($permission) && $this->tenant->canAccess($user, $companyId), 403);

        return [$companyId, $user];
    }
}

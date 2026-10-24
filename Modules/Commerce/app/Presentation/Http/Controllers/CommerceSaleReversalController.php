<?php

namespace Modules\Commerce\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Application\Commands\ReverseCompletedSaleCommand;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;

final readonly class CommerceSaleReversalController
{
    public function __construct(private BranchAccessService $access, private CommandBusInterface $commands) {}

    public function __invoke(Request $request, string $source, int $sale): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        abort_unless(in_array($source, ['marketplace', 'pos'], true), 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($actor, $companyId), 404);
        $this->commands->dispatch(new ReverseCompletedSaleCommand($actor, $companyId, $source, $sale, $request->string('reason')->toString()));

        return back()->with('success', 'Reversal đã hoàn tất; chứng từ gốc được giữ nguyên và kho đã hoàn trả.');
    }
}

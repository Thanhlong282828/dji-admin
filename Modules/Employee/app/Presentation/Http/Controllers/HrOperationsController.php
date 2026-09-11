<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\MutateHrOperationsCommand;
use Modules\Employee\Application\Queries\GetHrOperationsQuery;

final readonly class HrOperationsController
{
    public function __construct(private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        $company = $this->scope($request);

        return view('admin.hr.operations', ['companyId' => $company, 'data' => $this->queries->ask(new GetHrOperationsQuery($company))]);
    }

    public function mutate(Request $request): RedirectResponse
    {
        $company = $this->scope($request);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'action' => ['required', 'string', 'max:60'], 'id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'], 'previous_contract_id' => ['nullable', 'integer'], 'reference' => ['nullable', 'string', 'max:120'], 'effective_date' => ['nullable', 'date'], 'new_ends_at' => ['nullable', 'date'], 'new_contract_type' => ['nullable', 'in:probation,fixed_term,indefinite,contractor'], 'new_base_salary' => ['nullable', 'numeric', 'min:0'], 'last_working_date' => ['nullable', 'date'], 'approved_last_working_date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:1000'], 'evidence_reference' => ['nullable', 'string', 'max:500']]);
        abort_unless(is_array($validated), 422);
        $action = $request->string('action')->toString();
        $permission = str_contains($action, 'approve') || str_contains($action, 'reject') || $action === 'offboarding.complete' ? 'employment-contract.approve' : 'employment-contract.create';
        abort_unless($user->can($permission), 403);
        $this->commands->dispatch(new MutateHrOperationsCommand($action, $company, $user, $this->stringKeys($validated)));

        return back()->with('success', 'Nghiệp vụ HR đã được cập nhật.');
    }

    private function scope(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($user->hasRole('Super Admin') || DB::table('company_user_access')->where(['company_id' => $company, 'user_id' => $user->id])->exists(), 404);

        return $company;
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @return array<string,mixed>
     */
    private function stringKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}

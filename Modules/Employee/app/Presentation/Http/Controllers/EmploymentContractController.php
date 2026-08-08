<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Employee\Application\Commands\CreateEmploymentContract\CreateEmploymentContractCommand;
use Modules\Employee\Application\Commands\TransitionEmploymentContract\TransitionEmploymentContractCommand;
use Modules\Employee\Application\DTOs\EmploymentContractData;
use Modules\Employee\Domain\Enums\ContractStatus;
use Modules\Employee\Policies\EmploymentContractPolicy;
use Modules\Employee\Presentation\Http\Requests\StoreEmploymentContractRequest;

final readonly class EmploymentContractController
{
    public function __construct(private CommandBusInterface $commands, private EmploymentContractPolicy $policy) {}

    public function store(StoreEmploymentContractRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->create($user, $companyId), 403);
        $this->commands->dispatch(new CreateEmploymentContractCommand($companyId, $employee, EmploymentContractData::fromArray($this->payload($request)), $user->id));

        return back()->with('success', 'Đã tạo hợp đồng nháp.');
    }

    public function submit(Request $request, int $contract): RedirectResponse
    {
        return $this->transition($request, $contract, ContractStatus::Approval, 'employment-contract.submit');
    }

    public function approve(Request $request, int $contract): RedirectResponse
    {
        return $this->transition($request, $contract, ContractStatus::Approved, 'employment-contract.approve');
    }

    public function activate(Request $request, int $contract): RedirectResponse
    {
        return $this->transition($request, $contract, ContractStatus::Active, 'employment-contract.activate');
    }

    public function terminate(Request $request, int $contract): RedirectResponse
    {
        return $this->transition($request, $contract, ContractStatus::Terminated, 'employment-contract.terminate');
    }

    public function cancel(Request $request, int $contract): RedirectResponse
    {
        return $this->transition($request, $contract, ContractStatus::Cancelled, 'employment-contract.cancel');
    }

    public function return(Request $request, int $contract): RedirectResponse
    {
        return $this->transition($request, $contract, ContractStatus::Draft, 'employment-contract.approve');
    }

    private function transition(Request $request, int $contract, ContractStatus $target, string $permission): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->transition($user, $companyId, $permission), 403);
        $reason = $request->input('reason');
        $this->commands->dispatch(new TransitionEmploymentContractCommand($companyId, $contract, $target, $user->id, is_string($reason) ? $reason : null));

        return back()->with('success', 'Đã cập nhật trạng thái hợp đồng.');
    }

    /** @return array<string,mixed> */
    private function payload(StoreEmploymentContractRequest $request): array
    {
        $result = [];
        foreach ($request->safe()->except('company_id') as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Employee\Application\Commands\ArchiveEmployeeCredential\ArchiveEmployeeCredentialCommand;
use Modules\Employee\Application\Commands\CreateEmployeeCredential\CreateEmployeeCredentialCommand;
use Modules\Employee\Application\Commands\UpdateEmployeeCredential\UpdateEmployeeCredentialCommand;
use Modules\Employee\Application\DTOs\EmployeeCredentialData;
use Modules\Employee\Policies\EmployeeCredentialPolicy;
use Modules\Employee\Presentation\Http\Requests\StoreEmployeeCredentialRequest;

final readonly class EmployeeCredentialController
{
    public function __construct(private CommandBusInterface $commands, private EmployeeCredentialPolicy $policy) {}

    public function store(StoreEmployeeCredentialRequest $request, int $employee): RedirectResponse
    {
        [$companyId,$user] = $this->context($request, 'create');
        $this->commands->dispatch(new CreateEmployeeCredentialCommand($companyId, $employee, EmployeeCredentialData::fromArray($this->payload($request)), $user->id));

        return back()->with('success', 'Đã tạo chứng chỉ.');
    }

    public function update(StoreEmployeeCredentialRequest $request, int $credential): RedirectResponse
    {
        [$companyId,$user] = $this->context($request, 'update');
        $this->commands->dispatch(new UpdateEmployeeCredentialCommand($companyId, $credential, EmployeeCredentialData::fromArray($this->payload($request)), $user->id));

        return back()->with('success', 'Đã cập nhật chứng chỉ.');
    }

    public function archive(Request $request, int $credential): RedirectResponse
    {
        [$companyId,$user] = $this->context($request, 'archive');
        $this->commands->dispatch(new ArchiveEmployeeCredentialCommand($companyId, $credential, $user->id));

        return back()->with('success', 'Đã lưu trữ chứng chỉ.');
    }

    public function restore(Request $request, int $credential): RedirectResponse
    {
        [$companyId,$user] = $this->context($request, 'restore');
        $this->commands->dispatch(new ArchiveEmployeeCredentialCommand($companyId, $credential, $user->id, true));

        return back()->with('success', 'Đã khôi phục chứng chỉ.');
    }

    /** @return array{int,User} */
    private function context(Request $request, string $ability): array
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && match ($ability) {
            'create' => $this->policy->create($user, $companyId),'update' => $this->policy->update($user, $companyId),'archive' => $this->policy->archive($user, $companyId),'restore' => $this->policy->restore($user, $companyId),default => false
        }, 403);

        return [$companyId, $user];
    }

    /** @return array<string,mixed> */
    private function payload(StoreEmployeeCredentialRequest $request): array
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

<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Employee\Application\Commands\GrantDataConsent\GrantDataConsentCommand;
use Modules\Employee\Application\Commands\WithdrawDataConsent\WithdrawDataConsentCommand;
use Modules\Employee\Application\DTOs\DataConsentData;
use Modules\Employee\Policies\DataConsentPolicy;
use Modules\Employee\Presentation\Http\Requests\GrantDataConsentRequest;

final readonly class DataConsentController
{
    public function __construct(private CommandBusInterface $commands, private DataConsentPolicy $policy) {}

    public function grant(GrantDataConsentRequest $request, int $employee): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->grant($user, $companyId), 403);
        $this->commands->dispatch(new GrantDataConsentCommand($companyId, $employee, DataConsentData::fromArray($this->payload($request), true), $user->id));

        return back()->with('success', 'Đã ghi nhận quyết định đồng ý.');
    }

    public function withdraw(Request $request, int $consent): RedirectResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User && $this->policy->withdraw($user, $companyId), 403);
        $reason = $request->input('reason');
        abort_unless(is_string($reason) && trim($reason) !== '', 422);
        $this->commands->dispatch(new WithdrawDataConsentCommand($companyId, $consent, $reason, $user->id));

        return back()->with('success', 'Đã ghi nhận rút đồng ý bằng quyết định mới.');
    }

    /** @return array<string,mixed> */
    private function payload(GrantDataConsentRequest $request): array
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

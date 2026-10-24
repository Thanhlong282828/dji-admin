<?php

namespace Modules\Iam\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Iam\Application\Queries\GetAccountSecurityQuery;

final class AccountSecurityController
{
    public function __construct(private readonly QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $this->queries->ask(new GetAccountSecurityQuery($user));
        abort_unless(is_array($data), 500);

        return view('account.show', [
            'user' => $data['user'] ?? null,
            'employee' => $data['employee'] ?? null,
            'buyer' => $data['buyer'] ?? null,
            'sellers' => $data['sellers'] ?? null,
            'companies' => $data['companies'] ?? null,
            'branches' => $data['branches'] ?? null,
            'oauth' => $data['oauth'] ?? null,
        ]);
    }
}

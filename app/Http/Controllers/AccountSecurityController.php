<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccountSecurityController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $user->load('externalIdentities', 'roles');
        $employee = DB::table('employees')->where('user_id', $user->id)->first();
        $buyer = DB::table('buyer_profiles')->where('user_id', $user->id)->first();
        $seller = DB::table('seller_profiles')->where('user_id', $user->id)->first();
        $companies = DB::table('company_user_access as a')->join('companies as c', 'c.id', '=', 'a.company_id')->where('a.user_id', $user->id)->select('c.id', 'c.name')->get();
        $branches = DB::table('branch_user_access as a')->join('branches as b', 'b.id', '=', 'a.branch_id')->where('a.user_id', $user->id)->select('b.id', 'b.name')->get();
        $oauth = ['clients' => DB::table('oauth_clients')->where('owner_type', User::class)->where('owner_id', (string) $user->id)->where('revoked', false)->count(), 'tokens' => DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('revoked', false)->where('expires_at', '>', now())->count()];

        return view('account.show', compact('user', 'employee', 'buyer', 'seller', 'companies', 'branches', 'oauth'));
    }
}

<?php

namespace Modules\Iam\Application\Queries;

use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class GetAccountSecurityHandler
{
    /** @return array<string,mixed> */
    public function handle(GetAccountSecurityQuery $query): array
    {
        $user = $query->user;
        $user->load('externalIdentities', 'roles');
        $employee = DB::table('employees')->where('user_id', $user->id)->first();
        $buyer = DB::table('buyer_profiles')->where('user_id', $user->id)->first();
        $sellers = DB::table('seller_profiles as sp')->join('companies as c', 'c.id', '=', 'sp.company_id')->where('sp.user_id', $user->id)->orderBy('c.name')->get(['sp.id', 'sp.display_name', 'sp.status', 'c.id as company_id', 'c.name as company_name']);
        $companies = DB::table('company_user_access as a')->join('companies as c', 'c.id', '=', 'a.company_id')->where('a.user_id', $user->id)->select('c.id', 'c.name')->get();
        $branches = DB::table('branch_user_access as a')->join('branches as b', 'b.id', '=', 'a.branch_id')->leftJoin('employees as le', 'le.id', '=', 'b.leader_employee_id')->leftJoin('employee_assignments as ea', function (JoinClause $join): void {
            $join->on('ea.employee_id', '=', 'le.id')->where('ea.assignment_type', 'primary')->whereNull('ea.effective_to');
        })->leftJoin('job_positions as jp', 'jp.id', '=', 'ea.job_position_id')->where('a.user_id', $user->id)->select('b.id', 'b.name', 'b.address_line', 'b.city', 'le.user_id as leader_user_id', 'le.full_name as leader_name', 'jp.title as leader_position')->get();
        $oauth = ['clients' => DB::table('oauth_clients')->where('owner_type', User::class)->where('owner_id', (string) $user->id)->where('revoked', false)->count(), 'tokens' => DB::table('oauth_access_tokens')->where('user_id', $user->id)->where('revoked', false)->where('expires_at', '>', now())->count()];

        return compact('user', 'employee', 'buyer', 'sellers', 'companies', 'branches', 'oauth');
    }
}

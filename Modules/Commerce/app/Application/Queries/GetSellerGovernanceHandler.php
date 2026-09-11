<?php

namespace Modules\Commerce\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;

final readonly class GetSellerGovernanceHandler
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{companyName:string,sellers:LengthAwarePaginator<int,object>} */
    public function handle(GetSellerGovernanceQuery $query): array
    {
        $name = $this->db->table('companies')->where('id', $query->companyId)->value('name');
        abort_unless(is_string($name), 404);
        $sellers = $this->db->table('seller_profiles as sp')->join('users as u', 'u.id', '=', 'sp.user_id')->join('companies as c', 'c.id', '=', 'sp.company_id')->leftJoin('stores as s', static fn (JoinClause $join): JoinClause => $join->on('s.seller_profile_id', '=', 'sp.id')->whereNull('s.deleted_at'))->where('sp.company_id', $query->companyId)->select('sp.*', 'c.name as company_name', 'u.name as owner_name', 'u.email as owner_email')->selectRaw('COUNT(s.id) stores_count')->groupBy('sp.id', 'c.name', 'u.name', 'u.email')->latest('sp.created_at')->paginate(20)->withQueryString();

        return ['companyName' => $name, 'sellers' => $sellers];
    }
}

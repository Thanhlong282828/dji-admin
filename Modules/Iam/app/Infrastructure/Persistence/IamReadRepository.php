<?php

namespace Modules\Iam\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Iam\Application\DTOs\IamPageViewModel;
use Modules\Iam\Application\DTOs\UserAccess360ViewModel;
use Modules\Iam\Domain\Services\IamValue;

final class IamReadRepository
{
    public function dashboard(int $companyId): IamPageViewModel
    {
        return new IamPageViewModel([], ['pending_requests' => DB::table('iam_access_requests')->where('company_id', $companyId)->whereIn('status', ['requested', 'reviewed', 'approved'])->count(), 'privileged_assignments' => DB::table('iam_access_assignments')->where(['company_id' => $companyId, 'status' => 'active', 'privileged' => true])->count(), 'sod_violations' => DB::table('iam_sod_violations')->where(['company_id' => $companyId, 'status' => 'open'])->count(), 'expiring_access' => DB::table('iam_access_assignments')->where(['company_id' => $companyId, 'status' => 'active'])->whereBetween('effective_to', [now(), now()->addDays(30)])->count(), 'open_reviews' => DB::table('iam_access_reviews')->where('company_id', $companyId)->whereNot('status', 'completed')->count()]);
    }

    public function requests(int $companyId): IamPageViewModel
    {
        $rows = DB::table('iam_access_requests as r')->join('users as u', 'u.id', '=', 'r.subject_user_id')->where('r.company_id', $companyId)->select('r.*', 'u.name as subject_name', 'u.email as subject_email')->latest('r.id')->limit(100)->get()->all();

        return new IamPageViewModel($rows);
    }

    public function request(int $companyId, int $id): IamPageViewModel
    {
        $r = DB::table('iam_access_requests as r')->join('users as u', 'u.id', '=', 'r.subject_user_id')->where(['r.company_id' => $companyId, 'r.id' => $id])->select('r.*', 'u.name as subject_name', 'u.email as subject_email')->first();
        abort_unless($r instanceof \stdClass, 404);
        $items = DB::table('iam_access_request_items')->where('access_request_id', $id)->get()->all();

        return new IamPageViewModel($items, [], $r);
    }

    public function access360(int $companyId, int $userId): UserAccess360ViewModel
    {
        abort_unless(DB::table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $userId])->exists(), 404);
        $user = DB::table('users')->where('id', $userId)->select('id', 'name', 'email')->first();
        abort_unless($user instanceof \stdClass, 404);
        $roles = array_values(DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->where(['mr.model_type' => 'App\\Models\\User', 'mr.model_id' => $userId])->orderBy('r.name')->pluck('r.name')->filter(fn (mixed $value): bool => is_string($value))->values()->all());
        $permissions = array_values(DB::table('model_has_permissions as mp')->join('permissions as p', 'p.id', '=', 'mp.permission_id')->where(['mp.model_type' => 'App\\Models\\User', 'mp.model_id' => $userId])->orderBy('p.name')->pluck('p.name')->filter(fn (mixed $value): bool => is_string($value))->values()->all());
        $assignments = [];
        foreach (DB::table('iam_access_assignments')->where(['company_id' => $companyId, 'user_id' => $userId])->latest('id')->get() as $row) {
            $expiry = is_string($row->effective_to) ? $row->effective_to : null;
            $assignments[] = ['id' => IamValue::int($row->id), 'type' => IamValue::string($row->entitlement_type), 'entitlement_id' => IamValue::int($row->entitlement_id), 'status' => IamValue::string($row->status), 'privileged' => (bool) $row->privileged, 'effective_from' => IamValue::string($row->effective_from), 'effective_to' => $expiry, 'effective' => IamValue::string($row->status) === 'active' && ($expiry === null || now()->lte($expiry))];
        }
        $history = [];
        foreach (DB::table('iam_governance_histories')->where(['company_id' => $companyId, 'aggregate_type' => 'assignment'])->whereIn('aggregate_id', array_column($assignments, 'id'))->latest('id')->limit(100)->get() as $row) {
            $history[] = ['action' => IamValue::string($row->action), 'occurred_at' => IamValue::string($row->occurred_at)];
        }

        return new UserAccess360ViewModel($userId, IamValue::string($user->name), IamValue::string($user->email), true, $roles, $permissions, $assignments, DB::table('iam_access_requests')->where(['company_id' => $companyId, 'subject_user_id' => $userId])->whereIn('status', ['requested', 'reviewed', 'approved'])->count(), DB::table('iam_access_review_items as i')->join('iam_access_reviews as r', 'r.id', '=', 'i.access_review_id')->join('iam_access_assignments as a', 'a.id', '=', 'i.access_assignment_id')->where(['r.company_id' => $companyId, 'a.user_id' => $userId, 'i.decision' => 'pending'])->count(), $history);
    }

    public function catalog(int $companyId): IamPageViewModel
    {
        $rows = DB::table('roles as r')->leftJoin('iam_privileged_roles as p', fn (JoinClause $j): JoinClause => $j->on('p.role_id', '=', 'r.id')->where(fn (Builder $q): Builder => $q->whereNull('p.company_id')->orWhere('p.company_id', $companyId)))->select('r.id', 'r.name', 'p.risk_level', 'p.requires_two_step_approval', 'p.max_duration_hours')->orderBy('r.name')->get()->all();

        return new IamPageViewModel($rows);
    }

    public function privileged(int $companyId): IamPageViewModel
    {
        return new IamPageViewModel(DB::table('iam_access_assignments as a')->join('users as u', 'u.id', '=', 'a.user_id')->where(['a.company_id' => $companyId, 'a.privileged' => true])->select('a.*', 'u.name as user_name')->latest('a.id')->get()->all());
    }

    public function sod(int $companyId): IamPageViewModel
    {
        return new IamPageViewModel(DB::table('iam_sod_rules')->where(fn (Builder $q): Builder => $q->whereNull('company_id')->orWhere('company_id', $companyId))->orderBy('code')->get()->all(), ['violations' => DB::table('iam_sod_violations')->where('company_id', $companyId)->where('status', 'open')->count()]);
    }

    public function reviews(int $companyId): IamPageViewModel
    {
        return new IamPageViewModel(DB::table('iam_access_reviews')->where('company_id', $companyId)->latest('id')->get()->all());
    }

    public function review(int $companyId, int $reviewId): IamPageViewModel
    {
        $review = DB::table('iam_access_reviews')->where(['company_id' => $companyId, 'id' => $reviewId])->first();
        abort_unless($review instanceof \stdClass, 404);
        $items = DB::table('iam_access_review_items as i')->join('iam_access_assignments as a', 'a.id', '=', 'i.access_assignment_id')->join('users as u', 'u.id', '=', 'a.user_id')->where('i.access_review_id', $reviewId)->select('i.*', 'u.name as user_name', 'a.entitlement_type', 'a.entitlement_id', 'a.privileged')->orderBy('i.id')->get()->all();

        return new IamPageViewModel($items, [], $review);
    }

    public function history(int $companyId): IamPageViewModel
    {
        return new IamPageViewModel(DB::table('iam_governance_histories')->where('company_id', $companyId)->latest('id')->limit(200)->get()->all());
    }
}

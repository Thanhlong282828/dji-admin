<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SellerGovernanceController extends Controller
{
    public function index(): View
    {
        $sellers = DB::table('seller_profiles as sp')->join('users as u', 'u.id', '=', 'sp.user_id')->leftJoin('stores as s', fn (JoinClause $join): JoinClause => $join->on('s.seller_profile_id', '=', 'sp.id')->whereNull('s.deleted_at'))->select('sp.*', 'u.name as owner_name', 'u.email as owner_email', DB::raw('COUNT(s.id) as stores_count'))->groupBy('sp.id', 'u.name', 'u.email')->latest('sp.created_at')->paginate(20);

        return view('admin.seller.governance', compact('sellers'));
    }

    public function transition(Request $request, int $seller): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(['verified', 'active', 'suspended', 'banned'])]]);
        $target = $request->string('status')->toString();
        $from = DB::transaction(function () use ($seller, $target, $request): string {
            $profile = DB::table('seller_profiles')->where('id', $seller)->lockForUpdate()->firstOrFail();
            $current = $profile->status;
            abort_unless(is_string($current), 500, 'Seller status không hợp lệ.');
            $allowed = ['pending' => ['verified', 'banned'], 'verified' => ['active', 'suspended', 'banned'], 'active' => ['suspended', 'banned'], 'suspended' => ['active', 'banned'], 'banned' => []];
            abort_unless(in_array($target, $allowed[$current] ?? [], true), 422, 'Seller status transition không hợp lệ.');
            DB::table('seller_profiles')->where('id', $seller)->update(['status' => $target, 'verified_at' => $target === 'verified' ? now() : $profile->verified_at, 'verified_by' => $target === 'verified' ? $request->user()?->id : $profile->verified_by, 'updated_at' => now()]);

            return $current;
        }, 3);
        activity('commerce')->causedBy($request->user())->event('seller_status_changed')->withProperties(['seller_id' => $seller, 'from' => $from, 'to' => $target])->log('Seller governance status changed');

        return back()->with('success', 'Đã cập nhật trạng thái seller.');
    }
}

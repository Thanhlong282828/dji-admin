<?php

namespace Modules\Commerce\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SellerGovernanceService
{
    /** @var array<string,list<string>> */
    public const TRANSITIONS = [
        'pending' => ['verified', 'banned'],
        'verified' => ['active', 'suspended', 'banned'],
        'active' => ['suspended', 'banned'],
        'suspended' => ['active', 'banned'],
        'banned' => ['suspended'],
    ];

    /** @return array{from:string,to:string} */
    public function transition(int $companyId, int $sellerId, string $target, ?string $reason, User $actor): array
    {
        abort_if(DB::table('seller_profiles')->where(['id' => $sellerId, 'company_id' => $companyId, 'user_id' => $actor->id])->exists(), 403, 'Seller không thể tự duyệt hoặc thay đổi trạng thái governance của mình.');
        if (in_array($target, ['banned', 'suspended'], true) && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Phải nhập lý do khi đình chỉ hoặc cấm Seller.']);
        }

        $from = DB::transaction(function () use ($companyId, $sellerId, $target, $actor): string {
            $profile = DB::table('seller_profiles')->where(['id' => $sellerId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            $current = is_string($profile->status) ? $profile->status : '';
            abort_unless(in_array($target, self::TRANSITIONS[$current] ?? [], true), 422, "Không thể chuyển Seller từ {$current} sang {$target}.");
            DB::table('seller_profiles')->where('id', $sellerId)->update(['status' => $target, 'verified_at' => $target === 'verified' ? now() : $profile->verified_at, 'verified_by' => $target === 'verified' ? $actor->id : $profile->verified_by, 'updated_at' => now()]);

            return $current;
        }, 3);

        activity('commerce')->causedBy($actor)->event('seller_status_changed')->withProperties(['seller_id' => $sellerId, 'company_id' => $companyId, 'from' => $from, 'to' => $target, 'reason' => trim((string) $reason) ?: null])->log('Seller governance status changed');

        return ['from' => $from, 'to' => $target];
    }
}

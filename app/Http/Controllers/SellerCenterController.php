<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileRequest;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class SellerCenterController
{
    public function __construct(private MediaService $media) {}

    public function dashboard(Request $request): View
    {
        $seller = DB::table('seller_profiles')->where('user_id', $request->user()?->id)->first();
        $stores = $seller === null ? collect() : DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->get();

        return view('admin.seller.dashboard', compact('seller', 'stores'));
    }

    public function storeProfile(Request $request): View
    {
        $seller = DB::table('seller_profiles')->where('user_id', $request->user()?->id)->first();
        $store = $seller === null ? null : DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->first();
        $media = $store === null ? collect() : DB::table('media_assets')->where('owner_type', 'store')->where('owner_id', $store->id)->get()->keyBy('purpose');

        return view('admin.seller.store', compact('seller', 'store', 'media'));
    }

    public function saveStore(StoreProfileRequest $request): RedirectResponse
    {
        $seller = DB::table('seller_profiles')->where('user_id', $request->user()?->id)->firstOrFail();
        abort_unless(in_array($seller->status, ['verified', 'active'], true), 403);
        $staged = [];
        foreach (['logo' => MediaPurpose::StoreLogo, 'banner' => MediaPurpose::StoreBanner] as $field => $purpose) {
            if ($request->hasFile($field)) {
                $staged[$field] = $this->media->stage($request->file($field), $purpose);
            }
        }
        try {
            $storeId = DB::transaction(function () use ($request, $seller, $staged): int {
                $existing = DB::table('stores')->where('seller_profile_id', $seller->id)->whereNull('deleted_at')->lockForUpdate()->first();
                $values = ['name' => $request->string('name')->toString(), 'description' => $request->string('description')->toString(), 'updated_at' => now()];
                if ($existing === null) {
                    $id = $this->databaseId(DB::table('stores')->insertGetId([...$values, 'seller_profile_id' => $seller->id, 'slug' => Str::slug($values['name']).'-'.Str::lower(Str::random(6)), 'status' => 'active', 'created_at' => now()]));
                } else {
                    $id = $this->databaseId($existing->id);
                    DB::table('stores')->where('id', $id)->update($values);
                }
                foreach ($staged as $field => $stored) {
                    $purpose = $field === 'logo' ? MediaPurpose::StoreLogo : MediaPurpose::StoreBanner;
                    $oldIds = $this->integerIds(DB::table('media_assets')->where(['owner_type' => 'store', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                    $this->media->attach(null, 'store', $id, $purpose, $stored);
                    DB::afterCommit(fn () => $this->media->removeOwned('store', $id, $oldIds));
                }
                foreach (['logo' => MediaPurpose::StoreLogo, 'banner' => MediaPurpose::StoreBanner] as $field => $purpose) {
                    if ($request->boolean('remove_'.$field) && ! isset($staged[$field])) {
                        $ids = $this->integerIds(DB::table('media_assets')->where(['owner_type' => 'store', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                        DB::afterCommit(fn () => $this->media->removeOwned('store', $id, $ids));
                    }
                }

                return $id;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($staged as $stored) {
                $this->media->compensate($stored);
            }
            throw $exception;
        }
        activity('commerce.store')->causedBy($request->user())->withProperties(['store_id' => $storeId, 'seller_profile_id' => $this->databaseId($seller->id)])->log('store.saved');

        return to_route('admin.seller.store')->with('success', 'Đã cập nhật cửa hàng #'.$storeId.'.');
    }

    private function databaseId(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Database không trả về store ID hợp lệ.');
        }

        return (int) $value;
    }

    /** @param iterable<mixed> $values
     * @return list<int>
     */
    private function integerIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }
}

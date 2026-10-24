<?php

namespace Modules\Commerce\Application\Services;

use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Application\Commands\SaveSellerStoreCommand;
use RuntimeException;
use Throwable;

final readonly class SellerStoreWriteService
{
    public function __construct(private ConnectionInterface $db, private MediaService $media) {}

    public function save(SaveSellerStoreCommand $command): int
    {
        try {
            $storeId = $this->db->transaction(function () use ($command): int {
                $existing = $this->db->table('stores')->where('seller_profile_id', $command->sellerProfileId)->whereNull('deleted_at')->lockForUpdate()->first();
                $values = ['name' => $command->name, 'description' => $command->description, 'updated_at' => now()];
                if ($existing === null) {
                    $id = $this->id($this->db->table('stores')->insertGetId([...$values, 'seller_profile_id' => $command->sellerProfileId, 'slug' => Str::slug($command->name).'-'.Str::lower(Str::random(6)), 'status' => 'active', 'created_at' => now()]));
                } else {
                    $id = $this->id($existing->id);
                    $this->db->table('stores')->where('id', $id)->update($values);
                }
                foreach ($command->staged as $field => $stored) {
                    $purpose = $field === 'logo' ? MediaPurpose::StoreLogo : MediaPurpose::StoreBanner;
                    $oldIds = $this->integerIds($this->db->table('media_assets')->where(['owner_type' => 'store', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                    $this->media->attach(null, 'store', $id, $purpose, $stored);
                    DB::afterCommit(fn () => $this->media->removeOwned('store', $id, $oldIds));
                }
                foreach (['logo' => [$command->removeLogo, MediaPurpose::StoreLogo], 'banner' => [$command->removeBanner, MediaPurpose::StoreBanner]] as $field => [$remove, $purpose]) {
                    if ($remove && ! isset($command->staged[$field])) {
                        $ids = $this->integerIds($this->db->table('media_assets')->where(['owner_type' => 'store', 'owner_id' => $id, 'purpose' => $purpose->value])->pluck('id'));
                        DB::afterCommit(fn () => $this->media->removeOwned('store', $id, $ids));
                    }
                }

                return $id;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($command->staged as $stored) {
                $this->media->compensate($stored);
            }
            throw $exception;
        }
        activity('commerce.store')->causedBy($command->actor)->withProperties(['store_id' => $storeId, 'company_id' => $command->companyId, 'seller_profile_id' => $command->sellerProfileId])->log('store.saved');

        return $storeId;
    }

    private function id(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new RuntimeException('Database did not return a valid Store ID.');
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

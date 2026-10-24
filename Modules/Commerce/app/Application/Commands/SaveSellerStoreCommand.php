<?php

namespace Modules\Commerce\Application\Commands;

use App\Models\User;
use App\Support\Media\StoredMedia;

final readonly class SaveSellerStoreCommand
{
    /** @param array<string, StoredMedia> $staged */
    public function __construct(public int $companyId, public int $sellerProfileId, public string $name, public string $description, public array $staged, public bool $removeLogo, public bool $removeBanner, public User $actor) {}
}

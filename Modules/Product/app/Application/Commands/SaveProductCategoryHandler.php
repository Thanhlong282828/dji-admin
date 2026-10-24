<?php

namespace Modules\Product\Application\Commands;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class SaveProductCategoryHandler
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(SaveProductCategoryCommand $command): int
    {
        return (int) $this->db->table('product_categories')->insertGetId(['name' => $command->name, 'parent_id' => $command->parentId, 'status' => $command->status, 'sort_order' => $command->sortOrder, 'slug' => Str::slug($command->name).'-'.Str::lower(Str::random(4)), 'created_at' => now(), 'updated_at' => now()]);
    }
}

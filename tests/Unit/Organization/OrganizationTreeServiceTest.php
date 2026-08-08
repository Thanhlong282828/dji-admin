<?php

namespace Tests\Unit\Organization;

use Modules\Organization\Domain\Services\OrganizationTreeService;
use PHPUnit\Framework\TestCase;

final class OrganizationTreeServiceTest extends TestCase
{
    public function test_it_builds_recursive_tree_and_aggregates_headcount(): void
    {
        $rows = [
            $this->row(1, null, 0, 0, 0.0),
            $this->row(2, 1, 4, 2, 1.5),
            $this->row(3, 2, 2, 1, 0.5),
        ];

        $tree = (new OrganizationTreeService)->build($rows);

        self::assertCount(1, $tree);
        self::assertSame([6], array_column($tree, 'approved_headcount'));
        self::assertSame([3], array_column($tree, 'people_count'));
        self::assertSame([2.0], array_column($tree, 'allocated_fte'));
        self::assertSame([4.0], array_column($tree, 'vacancy'));
        $json = json_encode($tree, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"code":"UNIT-3"', $json);
        self::assertSame(3, substr_count($json, '"children":['));
    }

    /**
     * @return array{id:int,parent_id:int|null,code:string,name:string,type:string,headcount_limit:int,cost_center_code:string|null,cost_center_name:string|null,approved_headcount:int,people_count:int,allocated_fte:float,vacancy:float,detail_url:string}
     */
    private function row(int $id, ?int $parentId, int $approved, int $people, float $fte): array
    {
        return ['id' => $id, 'parent_id' => $parentId, 'code' => 'UNIT-'.$id, 'name' => 'Unit '.$id, 'type' => 'department', 'headcount_limit' => $approved, 'cost_center_code' => null, 'cost_center_name' => null, 'approved_headcount' => $approved, 'people_count' => $people, 'allocated_fte' => $fte, 'vacancy' => $approved - $fte, 'detail_url' => '/units/'.$id];
    }
}

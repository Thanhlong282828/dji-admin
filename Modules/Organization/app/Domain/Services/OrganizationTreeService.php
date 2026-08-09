<?php

namespace Modules\Organization\Domain\Services;

/**
 * @phpstan-type OrganizationTreeRow array{
 *     id: int,
 *     parent_id: int|null,
 *     code: string,
 *     name: string,
 *     type: string,
 *     headcount_limit: int,
 *     cost_center_code: string|null,
 *     cost_center_name: string|null,
 *     approved_headcount: int,
 *     people_count: int,
 *     allocated_fte: float,
 *     vacancy: float,
 *     detail_url: string
 * }
 * @phpstan-type OrganizationTreeNode array{
 *     id: int,
 *     parent_id: int|null,
 *     code: string,
 *     name: string,
 *     type: string,
 *     headcount_limit: int,
 *     cost_center_code: string|null,
 *     cost_center_name: string|null,
 *     approved_headcount: int,
 *     people_count: int,
 *     allocated_fte: float,
 *     vacancy: float,
 *     detail_url: string,
 *     children: list<array<string, mixed>>
 * }
 */
final class OrganizationTreeService
{
    /**
     * @param  list<OrganizationTreeRow>  $rows
     * @return list<OrganizationTreeNode>
     */
    public function build(array $rows, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($rows as $row) {
            if (($row['parent_id'] ?? null) !== $parentId) {
                continue;
            }
            $row['children'] = $this->build($rows, (int) $row['id']);
            foreach ($row['children'] as $child) {
                $row['approved_headcount'] += $child['approved_headcount'];
                $row['people_count'] += $child['people_count'];
                $row['allocated_fte'] += $child['allocated_fte'];
            }
            $row['vacancy'] = $row['approved_headcount'] - $row['allocated_fte'];
            $tree[] = $row;
        }

        return $tree;
    }
}

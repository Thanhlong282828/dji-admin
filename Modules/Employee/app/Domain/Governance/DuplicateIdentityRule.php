<?php

namespace Modules\Employee\Domain\Governance;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DuplicateIdentityRule implements HRDataQualityRule
{
    public function code(): string
    {
        return 'duplicate_identity';
    }

    public function severity(): string
    {
        return 'blocking';
    }

    public function evaluate(int $c): array
    {
        $hashes = DB::table('employees')->where('company_id', $c)->whereNotNull('identity_number_hash')->select('identity_number_hash')->groupBy('identity_number_hash')->havingRaw('COUNT(*) > 1')->pluck('identity_number_hash');
        $numbers = DB::table('employees')->where('company_id', $c)->whereNotNull('identity_number')->select('identity_number')->groupBy('identity_number')->havingRaw('COUNT(*) > 1')->pluck('identity_number');
        $ids = DB::table('employees')->where('company_id', $c)->where(function (Builder $query) use ($hashes, $numbers): void {
            $query->whereIn('identity_number_hash', $hashes)->orWhereIn('identity_number', $numbers);
        })->pluck('id');

        return array_values($ids->map(fn (mixed $id): array => ['entity_type' => 'employee', 'entity_id' => is_numeric($id) ? (int) $id : 0, 'message' => 'Identity hash bị trùng trong cùng công ty.'])->all());
    }
}

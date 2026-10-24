<?php

namespace Modules\Inventory\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InventoryGovernanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'], 'action' => ['required', 'in:stock-count.create,stock-count.submit,stock-count.approve,adjustment.create,adjustment.approve,adjustment.post'],
            'stock_count_id' => ['nullable', 'integer'], 'adjustment_id' => ['nullable', 'integer'], 'inventory_location_id' => ['nullable', 'integer'], 'product_variant_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:120'], 'count_date' => ['nullable', 'date'], 'posting_date' => ['nullable', 'date'], 'counted_qty' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'in:STOCK_COUNT,DAMAGE,LOSS,EXPIRED,FOUND'], 'idempotency_key' => ['nullable', 'string', 'max:191'],
        ];
    }
}

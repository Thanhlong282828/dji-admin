<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->where('status', 'active')],
            'code' => ['required', 'string', 'max:80', Rule::unique('products')->where(fn (Builder $query): Builder => $query->where('store_id', $this->integer('store_id')))->ignore(is_numeric($product) ? (int) $product : null)],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'], 'brand' => ['nullable', 'string', 'max:100'],
            'base_price_minor' => ['required', 'integer', 'min:0'], 'currency' => ['required', Rule::in(['VND'])], 'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=6000,max_height=6000'],
            'gallery' => ['nullable', 'array', 'max:8'], 'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=6000,max_height=6000'],
            'variants' => ['required', 'array', 'min:1', 'max:50'], 'variants.*.id' => ['nullable', 'integer'], 'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.name' => ['required', 'string', 'max:255'], 'variants.*.attributes' => ['nullable', 'string', 'max:2000'], 'variants.*.price_minor' => ['required', 'integer', 'min:0'],
            'variants.*.status' => ['required', Rule::in(['active', 'inactive'])], 'variants.*.on_hand' => ['required', 'integer', 'min:0'], 'variants.*.reserved' => ['required', 'integer', 'min:0', 'lte:variants.*.on_hand'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = $this->input('variants', []);
            $productId = $this->route('product');
            if (! is_array($rows)) {
                return;
            }
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                if ($id !== null && (! is_numeric($productId) || ! DB::table('product_variants')->where('id', $id)->where('product_id', (int) $productId)->exists())) {
                    $validator->errors()->add("variants.{$index}.id", 'Variant không thuộc Product.');
                }
                $sku = $row['sku'] ?? null;
                if (is_string($sku) && DB::table('product_variants')->where('sku', $sku)->when($id !== null, fn (Builder $query): Builder => $query->where('id', '!=', $id))->exists()) {
                    $validator->errors()->add("variants.{$index}.sku", 'SKU đã tồn tại.');
                }
            }
        });
    }
}

<?php

namespace Modules\Commerce\Application\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Domain\Enums\OrderStatus;

final readonly class OrderListFilter
{
    private function __construct(public string $search, public ?string $status, public ?CarbonImmutable $from, public ?CarbonImmutable $to) {}

    public static function fromRequest(Request $request): self
    {
        $request->validate(['q' => ['nullable', 'string', 'max:40'], 'status' => ['nullable', Rule::enum(OrderStatus::class)], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from']]);
        $from = $request->filled('date_from') ? CarbonImmutable::parse($request->string('date_from')->toString())->startOfDay() : null;
        $to = $request->filled('date_to') ? CarbonImmutable::parse($request->string('date_to')->toString())->endOfDay() : null;
        if ($from !== null && $to !== null && $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['date_to' => 'Khoảng ngày không được vượt quá 366 ngày.']);
        }

        return new self($request->string('q')->trim()->toString(), $request->filled('status') ? $request->string('status')->toString() : null, $from, $to);
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->search !== '', fn (Builder $builder): Builder => $builder->where('o.order_number', 'like', '%'.$this->search.'%'))
            ->when($this->status !== null, fn (Builder $builder): Builder => $builder->where('o.status', $this->status))
            ->when($this->from !== null, fn (Builder $builder): Builder => $builder->where('o.placed_at', '>=', $this->from))
            ->when($this->to !== null, fn (Builder $builder): Builder => $builder->where('o.placed_at', '<=', $this->to));
    }
}

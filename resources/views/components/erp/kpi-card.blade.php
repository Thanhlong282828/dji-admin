@props(['label', 'value', 'meta' => null])
<article {{ $attributes->class(['erp-kpi']) }}>
    <span class="erp-kpi__label">{{ $label }}</span>
    <strong class="erp-kpi__value">{{ $value }}</strong>
    @if($meta)<small class="erp-kpi__meta">{{ $meta }}</small>@endif
</article>

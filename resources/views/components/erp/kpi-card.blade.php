@props(['label', 'value', 'meta' => null, 'href' => null, 'actionLabel' => 'Mở workspace'])
@if($href)
<a href="{{ $href }}" {{ $attributes->class(['erp-kpi', 'erp-kpi--link']) }}>
    <span class="erp-kpi__label">{{ $label }}</span>
    <strong class="erp-kpi__value">{{ $value }}</strong>
    @if($meta)<small class="erp-kpi__meta">{{ $meta }}</small>@endif
    <span class="erp-kpi__action" aria-hidden="true">{{ $actionLabel }} →</span>
</a>
@else
<article {{ $attributes->class(['erp-kpi']) }}>
    <span class="erp-kpi__label">{{ $label }}</span>
    <strong class="erp-kpi__value">{{ $value }}</strong>
    @if($meta)<small class="erp-kpi__meta">{{ $meta }}</small>@endif
</article>
@endif

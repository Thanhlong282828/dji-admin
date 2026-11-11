@props(['label', 'value' => null, 'meta' => null, 'href' => null, 'actionLabel' => 'Mở workspace', 'unit' => null, 'icon' => null, 'trend' => null, 'status' => null, 'state' => 'ready'])
@php
    $isLink = $href && $state === 'ready';
    $stateLabel = match ($state) {
        'loading' => 'Đang tải dữ liệu',
        'empty' => 'Chưa có dữ liệu nghiệp vụ',
        'error' => 'Không thể tải dữ liệu',
        default => null,
    };
@endphp
@if($isLink)
<a href="{{ $href }}" {{ $attributes->class(['erp-kpi', 'erp-kpi--link', 'erp-kpi--'.$state]) }}>
    <span class="erp-kpi__heading">@if($icon)<span class="erp-kpi__icon" aria-hidden="true">{{ $icon }}</span>@endif<span class="erp-kpi__label">{{ $label }}</span></span>
    <strong class="erp-kpi__value">{{ $value }}@if($unit)<small>{{ $unit }}</small>@endif</strong>
    @if($trend)<span class="erp-kpi__trend">{{ $trend }}</span>@endif
    @if($status)<span class="erp-kpi__status">{{ $status }}</span>@endif
    @if($meta)<small class="erp-kpi__meta">{{ $meta }}</small>@endif
    <span class="erp-kpi__action" aria-hidden="true">{{ $actionLabel }} →</span>
</a>
@else
<article {{ $attributes->class(['erp-kpi', 'erp-kpi--'.$state]) }} @if($state === 'loading') aria-busy="true" @endif>
    <span class="erp-kpi__heading">@if($icon)<span class="erp-kpi__icon" aria-hidden="true">{{ $icon }}</span>@endif<span class="erp-kpi__label">{{ $label }}</span></span>
    @if($stateLabel)<strong class="erp-kpi__state">{{ $stateLabel }}</strong>@else<strong class="erp-kpi__value">{{ $value }}@if($unit)<small>{{ $unit }}</small>@endif</strong>@endif
    @if($trend)<span class="erp-kpi__trend">{{ $trend }}</span>@endif
    @if($status)<span class="erp-kpi__status">{{ $status }}</span>@endif
    @if($meta)<small class="erp-kpi__meta">{{ $meta }}</small>@endif
</article>
@endif

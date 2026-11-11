@props([
    'variant' => 'primary',
    'loading' => false,
    'loadingLabel' => 'Đang xử lý…',
    'href' => null,
])

@php
    $allowedVariants = ['primary', 'secondary', 'success', 'warning', 'danger', 'outline', 'ghost', 'icon'];
    $resolvedVariant = in_array($variant, $allowedVariants, true) ? $variant : 'secondary';
    $classes = 'erp-button erp-button--'.$resolvedVariant;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button
        {{ $attributes->class([$classes])->merge(['type' => 'button']) }}
        @disabled($loading || $attributes->has('disabled'))
        @if($loading) aria-busy="true" @endif
    >
        @if($loading)<span class="erp-button__spinner" aria-hidden="true"></span><span>{{ $loadingLabel }}</span>@else{{ $slot }}@endif
    </button>
@endif

@props(['title', 'description' => null, 'label' => null])
<section {{ $attributes->class(['erp-data-table']) }}>
    <header class="erp-data-table__header">
        <div><h3>{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</div>
        @isset($actions)<div class="erp-row-actions">{{ $actions }}</div>@endisset
    </header>
    @isset($filters)<div class="erp-filter-toolbar">{{ $filters }}</div>@endisset
    <div class="erp-table-scroll" tabindex="0" @if($label || $title) aria-label="{{ $label ?: $title }}" @endif>
        {{ $slot }}
    </div>
    @isset($pagination)<div class="erp-pagination">{{ $pagination }}</div>@endisset
</section>

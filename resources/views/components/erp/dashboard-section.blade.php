@props(['title', 'description' => null, 'columns' => null])
@php($headingId = 'erp-section-'.substr(hash('sha256', $title), 0, 12))
<section {{ $attributes->class(['erp-dashboard-section']) }} aria-labelledby="{{ $headingId }}">
    <header class="erp-dashboard-section__header"><div><h3 id="{{ $headingId }}">{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</div>@isset($actions)<div class="erp-dashboard-section__actions">{{ $actions }}</div>@endisset</header>
    <div @class(['erp-widget-grid', 'erp-widget-grid--'.$columns => $columns])>{{ $slot }}</div>
</section>

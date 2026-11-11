@props(['eyebrow' => null, 'title', 'description' => null])
<div {{ $attributes->class(['erp-dashboard']) }}>
    <section class="erp-dashboard__hero" aria-labelledby="erp-dashboard-title">
        <div>@if($eyebrow)<span class="erp-dashboard__eyebrow">{{ $eyebrow }}</span>@endif<h2 id="erp-dashboard-title">{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>
        @isset($actions)<div class="erp-dashboard__actions">{{ $actions }}</div>@endisset
    </section>
    {{ $slot }}
</div>

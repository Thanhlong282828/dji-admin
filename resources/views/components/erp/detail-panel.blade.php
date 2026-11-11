@props(['title', 'description' => null])
<section {{ $attributes->class(['erp-detail-panel']) }}>
    <header><h3>{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</header>
    <div class="erp-detail-panel__body">{{ $slot }}</div>
</section>

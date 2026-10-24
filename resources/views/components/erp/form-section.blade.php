@props(['title' => null, 'description' => null])
<section {{ $attributes->class(['admin-panel', 'erp-form-section']) }}>
    @if($title || $description)
        <header class="erp-form-section__header">
            @if($title)<h2>{{ $title }}</h2>@endif
            @if($description)<p>{{ $description }}</p>@endif
        </header>
    @endif
    {{ $slot }}
</section>

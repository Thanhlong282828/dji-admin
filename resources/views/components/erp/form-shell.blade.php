@props(['title' => null, 'description' => null])
<div {{ $attributes->class(['erp-form-shell']) }}>
    @if($title || $description)
        <header class="erp-form-shell__header">
            @if($title)<h2>{{ $title }}</h2>@endif
            @if($description)<p>{{ $description }}</p>@endif
        </header>
    @endif
    {{ $slot }}
</div>

@props(['title', 'description' => null, 'kicker' => 'Enterprise workspace'])
<header {{ $attributes->class(['erp-page-hero']) }}>
    <div class="erp-page-copy">
        <span class="erp-page-kicker erp-gold-text">{{ $kicker }}</span>
        <h2>{{ $title }}</h2>
        @if($description)<p>{{ $description }}</p>@endif
    </div>
    @if(isset($actions))<div class="erp-page-actions">{{ $actions }}</div>@endif
</header>

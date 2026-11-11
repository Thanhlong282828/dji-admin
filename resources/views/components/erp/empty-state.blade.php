@props(['title', 'description' => null])
<div {{ $attributes->class(['erp-empty-state']) }}>
    <span aria-hidden="true">↗</span>
    <div><strong>{{ $title }}</strong>@if($description)<p>{{ $description }}</p>@endif</div>
    @isset($action)<div class="erp-empty-state__action">{{ $action }}</div>@endisset
</div>

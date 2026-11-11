@props(['title' => null, 'description' => null, 'state' => 'ready'])
<article {{ $attributes->class(['erp-widget', 'erp-widget--'.$state]) }} @if($state === 'loading') aria-busy="true" @endif>
    @if($title || $description || isset($actions))<header class="erp-widget__header"><div>@if($title)<h4>{{ $title }}</h4>@endif @if($description)<p>{{ $description }}</p>@endif</div>@isset($actions)<div>{{ $actions }}</div>@endisset</header>@endif
    @if($state === 'loading')<div class="erp-widget__state" role="status" aria-live="polite">Đang tải dữ liệu…</div>@elseif($state === 'empty')<div class="erp-widget__state" role="status">Chưa có dữ liệu nghiệp vụ.</div>@elseif($state === 'error')<div class="erp-widget__state erp-widget__state--error" role="alert">Không thể tải dữ liệu. Vui lòng thử lại.</div>@else{{ $slot }}@endif
</article>

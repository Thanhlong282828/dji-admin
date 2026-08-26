@php
    $cover = $media->get($product->id);
    $isLiked = isset($liked[(int) $product->id]);
    $available = (int) $product->available;
@endphp
<article class="market-product-card">
    <a class="product-image" href="{{ route('public.products.show', $product->slug) }}">
        @if($cover?->delivery_url)
            <img src="{{ $cover->delivery_url }}" alt="Ảnh {{ $product->name }}" loading="lazy">
        @else
            <span aria-hidden="true">▧</span>
        @endif
        @if($available < 1)
            <em>Hết hàng</em>
        @elseif($available <= config('commerce.inventory.low_stock_threshold', 5))
            <em class="low">Sắp hết</em>
        @endif
    </a>
    @auth
        <form class="heart-form" method="POST" action="{{ $isLiked ? route('wishlist.unlike', $product->id) : route('wishlist.like', $product->id) }}">
            @csrf
            @if($isLiked) @method('DELETE') @endif
            <button aria-label="{{ $isLiked ? 'Bỏ yêu thích' : 'Thêm yêu thích' }} {{ $product->name }}" aria-pressed="{{ $isLiked ? 'true' : 'false' }}">{{ $isLiked ? '♥' : '♡' }}</button>
        </form>
    @else
        <a class="heart-form" href="{{ route('login') }}" aria-label="Đăng nhập để yêu thích">♡</a>
    @endauth
    <div class="product-card-body">
        <small>{{ $product->category_name ?: 'Sản phẩm' }}</small>
        <h2><a href="{{ route('public.products.show', $product->slug) }}">{{ $product->name }}</a></h2>
        <strong class="market-price">
            {{ number_format($product->min_price_minor, 0, ',', '.') }} ₫
            @if((int) $product->min_price_minor !== (int) $product->max_price_minor)
                – {{ number_format($product->max_price_minor, 0, ',', '.') }} ₫
            @endif
        </strong>
        <a class="store-link" href="{{ route('public.stores.show', $product->store_slug) }}">{{ $product->store_name }}</a>
        <span class="stock-text">{{ $available > 0 ? 'Còn '.$available.' sản phẩm' : 'Tạm hết hàng' }}</span>
    </div>
</article>

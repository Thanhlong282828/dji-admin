@extends('layouts.public')
@section('title', 'Giỏ hàng')
@section('content')
<section class="market-page-hero compact"><small>BUYER CART</small><h1>Giỏ hàng của bạn</h1><p>Một Cart có thể chứa nhiều Store; mỗi item khóa đúng Variant/SKU.</p></section>
@if($cart->itemCount === 0)
<section class="market-empty cart-empty"><b>🛒</b><h2>Giỏ hàng của bạn đang trống</h2><p>Khám phá catalog và chọn đúng phân loại sản phẩm.</p><a class="market-primary" href="{{ route('public.products.index') }}">Khám phá sản phẩm</a></section>
@else
<section class="cart-layout"><div class="cart-stores">
<form class="select-all-form" method="POST" action="{{ route('cart.selection', ['scope' => 'all']) }}">@csrf @method('PATCH')<input type="hidden" name="selected" value="1"><button>✓ Chọn tất cả sản phẩm hợp lệ</button></form>
@foreach($cart->stores as $storeId => $store)
<article class="cart-store"><header><form method="POST" action="{{ route('cart.selection', ['scope' => 'store', 'subject' => $storeId]) }}">@csrf @method('PATCH')<input type="hidden" name="selected" value="1"><button aria-label="Chọn toàn bộ {{ $store['name'] }}">□</button></form><a href="{{ route('public.stores.show', $store['slug']) }}">{{ $store['name'] }}</a><strong>{{ number_format($store['subtotal_minor'], 0, ',', '.') }} ₫</strong></header>
@foreach($store['items'] as $item)
<div class="cart-item {{ $item['valid'] ? '' : 'is-invalid' }}">
<form method="POST" action="{{ route('cart.selection', ['scope' => 'item', 'subject' => $item['id']]) }}">@csrf @method('PATCH')<input type="hidden" name="selected" value="{{ $item['selected'] ? 0 : 1 }}"><button aria-label="{{ $item['selected'] ? 'Bỏ chọn' : 'Chọn' }} {{ $item['product_name'] }}" @disabled(!$item['valid'])>{{ $item['selected'] ? '☑' : '□' }}</button></form>
<a class="cart-item-image" href="{{ route('public.products.show', $item['product_slug']) }}">@if($item['cover_url'])<img src="{{ $item['cover_url'] }}" alt="{{ $item['product_name'] }}">@else<span>▧</span>@endif</a>
<div class="cart-item-info"><h2><a href="{{ route('public.products.show', $item['product_slug']) }}">{{ $item['product_name'] }}</a></h2><p>{{ $item['variant_name'] }} · SKU {{ $item['sku'] }}</p>@if(!$item['valid'])<strong class="invalid-reason">{{ $item['invalid_reason'] }}</strong>@endif @if($item['price_changed'])<small>Giá khi thêm: {{ number_format($item['added_price_minor'], 0, ',', '.') }} ₫ · Giá hiện tại đã thay đổi</small>@endif</div>
<strong class="market-price">{{ number_format($item['current_price_minor'], 0, ',', '.') }} ₫</strong>
<form class="cart-quantity" method="POST" action="{{ route('cart.items.quantity', $item['id']) }}" data-cart-quantity>@csrf @method('PATCH')<button type="button" data-qty-minus aria-label="Giảm số lượng">−</button><input name="quantity" type="number" min="1" max="{{ max(1, $item['available']) }}" value="{{ $item['quantity'] }}" aria-label="Số lượng {{ $item['product_name'] }}" data-qty-input><button type="button" data-qty-plus aria-label="Tăng số lượng">+</button><button class="update-qty">Cập nhật</button></form>
<form method="POST" action="{{ route('cart.items.remove', $item['id']) }}">@csrf @method('DELETE')<button class="remove-item">Xóa</button></form></div>
@endforeach</article>
@endforeach
</div><aside class="cart-summary"><small>TÓM TẮT</small><h2>{{ $cart->summary->selectedQuantity }} sản phẩm đã chọn</h2><dl><div><dt>Tạm tính</dt><dd>{{ number_format($cart->summary->subtotalMinor, 0, ',', '.') }} ₫</dd></div><div><dt>Giảm giá</dt><dd>0 ₫</dd></div><div class="total"><dt>Tổng cộng</dt><dd>{{ number_format($cart->summary->totalMinor(), 0, ',', '.') }} ₫</dd></div></dl>
@if($cart->summary->selectedQuantity > 0)<a class="market-primary" href="{{ route('checkout.show') }}">Tiếp tục thanh toán</a>@else<button class="market-primary" disabled>Chọn sản phẩm để thanh toán</button>@endif
<p>Tồn kho chỉ được giữ khi bạn xác nhận đặt hàng.</p></aside></section>
@endif
@endsection

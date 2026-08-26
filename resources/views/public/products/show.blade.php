@extends('layouts.public')
@section('title',$product->name)
@section('content')
<section class="public-hero">
    <span>{{ $product->category_name ?: 'Sản phẩm' }}</span>
    <h1>{{ $product->name }}</h1>
    <p>{{ $product->store_name }} · {{ $product->seller_name }}</p>
</section>
<section class="public-detail-grid">
    <div class="media-grid">
        @forelse($media as $asset)
            <img src="{{ $asset->delivery_url }}" alt="{{ $product->name }}">
        @empty
            <div class="panel">Sản phẩm chưa có hình ảnh.</div>
        @endforelse
    </div>
    <article>
        <p>{{ $product->description ?: 'Chưa có mô tả.' }}</p>
        <h2>{{ number_format($product->base_price_minor, 0, ',', '.') }} ₫</h2>
        <h3>Phiên bản</h3>
        @foreach($variants as $variant)
            <div class="panel">
                <strong>{{ $variant->name }}</strong>
                <p>{{ number_format($variant->price_minor, 0, ',', '.') }} ₫ · {{ $variant->available > 0 ? 'Còn '.$variant->available : 'Hết hàng' }}</p>
            </div>
        @endforeach
    </article>
</section>
@endsection

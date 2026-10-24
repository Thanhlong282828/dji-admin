@extends('layouts.admin')
@section('title', 'Seller Dashboard')
@section('content')
<section class="page-hero panel">
    <span class="eyebrow">Seller Center</span>
    <h2>{{ $seller?->display_name ?? 'Chưa có hồ sơ Seller' }}</h2>
    <p>{{ $companyName }} · Trạng thái Seller, Store, Product, tồn kho, Order và doanh thu recognized thuộc đúng Seller account của Company hiện tại.</p>
</section>
<section class="summary-grid">
    <article class="summary-card"><span>Seller status</span><strong>{{ $seller?->status ?? 'N/A' }}</strong></article>
    <article class="summary-card"><span>Cửa hàng</span><strong>{{ $stores->count() }}</strong></article>
    <article class="summary-card"><span>Sản phẩm active</span><strong>{{ $metrics['active_products'] }} / {{ $metrics['products'] }}</strong></article>
    <article class="summary-card"><span>Sắp hết hàng</span><strong>{{ $metrics['low_stock'] }}</strong></article>
    <article class="summary-card"><span>Hết hàng</span><strong>{{ $metrics['out_of_stock'] }}</strong></article>
    <article class="summary-card"><span>Đơn hàng chờ xác nhận</span><strong>{{ $metrics['pending_orders'] }} / {{ $metrics['orders'] }}</strong></article>
    <article class="summary-card"><span>Order đã thanh toán</span><strong>{{ $metrics['paid_orders'] }}</strong></article>
    <article class="summary-card"><span>Order đã giao</span><strong>{{ $metrics['fulfilled_orders'] }}</strong></article>
    <article class="summary-card"><span>Doanh thu Order recognized</span><strong>{{ number_format($metrics['recognized_revenue_minor'], 0, ',', '.') }} ₫</strong></article>
</section>
@if($seller)
    <div class="button-row">
        <a class="button" href="{{ route('admin.seller.store', ['company_id' => $companyId]) }}">Hồ sơ Store</a>
        @can('product.view') <a class="button secondary" href="{{ route('admin.products.index') }}">Sản phẩm</a> @endcan
        @can('inventory.view') <a class="button secondary" href="{{ route('admin.seller.inventory') }}">Tồn kho</a> @endcan
        @can('commerce.store.view') <a class="button secondary" href="{{ route('admin.seller.orders.index') }}">Đơn hàng</a> @endcan
    </div>
@else
    <div class="notice">Hồ sơ Seller cần được nền tảng khởi tạo/xét duyệt trước khi mở cửa hàng.</div>
@endif
@endsection

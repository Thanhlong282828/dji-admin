@extends('layouts.admin')
@section('title', 'Seller Dashboard')
@section('content')
<x-erp.dashboard eyebrow="Seller Center" :title="$seller?->display_name ?? 'Chưa có hồ sơ Seller'" :description="$companyName.' · Dữ liệu Store, Product, tồn kho, Order và doanh thu recognized thuộc Seller account của Company hiện tại.'">
<x-erp.dashboard-section title="Sales / Orders" description="Chỉ số lấy từ Seller account và các luồng commerce canonical." columns="3">
    <x-erp.kpi-card label="Seller status" :value="$seller?->status ?? 'N/A'" icon="◈" :state="$seller ? 'ready' : 'empty'" />
    <x-erp.kpi-card label="Cửa hàng" :value="$stores->count()" icon="⌂" />
    <x-erp.kpi-card label="Sản phẩm active" :value="$metrics['active_products'].' / '.$metrics['products']" icon="◇" @can('product.view') :href="route('admin.products.index')" action-label="Mở sản phẩm" @endcan />
    <x-erp.kpi-card label="Sắp hết hàng" :value="$metrics['low_stock']" icon="!" @can('inventory.view') :href="route('admin.seller.inventory')" action-label="Mở tồn kho" @endcan />
    <x-erp.kpi-card label="Hết hàng" :value="$metrics['out_of_stock']" icon="×" status="Cần bổ sung nguồn hàng" />
    <x-erp.kpi-card label="Đơn chờ xác nhận" :value="$metrics['pending_orders'].' / '.$metrics['orders']" icon="◷" @can('commerce.store.view') :href="route('admin.seller.orders.index')" action-label="Mở đơn hàng" @endcan />
    <x-erp.kpi-card label="Order đã thanh toán" :value="$metrics['paid_orders']" icon="✓" />
    <x-erp.kpi-card label="Order đã giao" :value="$metrics['fulfilled_orders']" icon="→" />
    <x-erp.kpi-card label="Doanh thu recognized" :value="number_format($metrics['recognized_revenue_minor'], 0, ',', '.')" unit="₫" icon="₫" meta="Nguồn: order đã ghi nhận" />
</x-erp.dashboard-section>
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
</x-erp.dashboard>
@endsection

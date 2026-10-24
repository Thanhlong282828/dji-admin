@extends('layouts.admin')
@section('title','Tổng quan chuỗi cung ứng')
@section('content')
@php
    $ownerUrl = static fn (string $permission, string $route): ?string => auth()->user()?->can($permission) === true
        ? route($route, ['company_id' => $companyId])
        : null;
@endphp
<div class="admin-page scm-dashboard">
    <section class="scm-intro" aria-labelledby="scm-operating-state">
        <div><p class="admin-eyebrow">PROCUREMENT → INVENTORY → DEMAND → FULFILLMENT</p><h2 id="scm-operating-state">Trạng thái vận hành</h2><p>Read-only từ các owner domain; không lưu bảng tổng SCM.</p></div>
    </section>

    <section class="scm-section" aria-labelledby="scm-supply-title">
        <div class="scm-section__header"><div><p class="admin-eyebrow">SUPPLY · INBOUND</p><h2 id="scm-supply-title">Mua hàng và nguồn cung</h2></div></div>
        <div class="erp-kpi-grid">
            <x-erp.kpi-card label="Open PO" :value="number_format((int) $data['supply']['open_purchase_orders'])" meta="PO đã duyệt/gửi/chờ nhận" :href="$ownerUrl('purchase-order.view', 'admin.procurement.orders')" />
            <x-erp.kpi-card label="Pending Receipt" :value="number_format((int) $data['supply']['pending_receipts'])" meta="Phiếu nhận hàng đang draft" :href="$ownerUrl('goods-receipt.view', 'admin.procurement.receipts')" />
            <x-erp.kpi-card label="Inbound Qty" :value="number_format((int) $data['supply']['inbound_quantity'])" meta="Số lượng PO chưa nhận" :href="$ownerUrl('purchase-order.view', 'admin.procurement.orders')" />
            <x-erp.kpi-card label="Inbound Value" :value="number_format((int) $data['supply']['inbound_value_minor'], 0, ',', '.').' ₫'" meta="Giá trị theo PO còn mở" :href="$ownerUrl('purchase-order.view', 'admin.procurement.orders')" />
        </div>
    </section>

    <section class="scm-section" aria-labelledby="scm-inventory-title">
        <div class="scm-section__header"><div><p class="admin-eyebrow">INVENTORY · AVAILABILITY</p><h2 id="scm-inventory-title">Khả dụng tồn kho</h2></div><span class="erp-evidence-note">Available = On hand − Reserved</span></div>
        <div class="erp-kpi-grid">
            <x-erp.kpi-card label="SKU" :value="number_format((int) $data['inventory']['skus'])" meta="Variant có số dư" :href="$ownerUrl('inventory.view', 'admin.seller.inventory')" />
            <x-erp.kpi-card label="On hand" :value="number_format((int) $data['inventory']['on_hand'])" meta="Tồn vật lý canonical" :href="$ownerUrl('inventory.view', 'admin.seller.inventory')" />
            <x-erp.kpi-card label="Reserved" :value="number_format((int) $data['inventory']['reserved'])" meta="Reservation canonical" :href="$ownerUrl('inventory.view', 'admin.seller.inventory')" />
            <x-erp.kpi-card label="Available" :value="number_format((int) $data['inventory']['available'])" meta="Khả dụng sau reservation" :href="$ownerUrl('inventory.view', 'admin.seller.inventory')" />
            <x-erp.kpi-card label="In transit" :value="number_format((int) $data['inventory']['in_transit'])" meta="Stock transfer đang vận chuyển" :href="$ownerUrl('inventory.view', 'admin.seller.inventory')" />
            <x-erp.kpi-card label="Inventory value" :value="number_format((int) $data['inventory']['inventory_value_minor'], 0, ',', '.').' ₫'" meta="Weighted-average valuation" :href="$ownerUrl('inventory.view', 'admin.seller.inventory')" />
        </div>
    </section>

    <section class="scm-section" aria-labelledby="scm-demand-title">
        <div class="scm-section__header"><div><p class="admin-eyebrow">DEMAND · FULFILLMENT</p><h2 id="scm-demand-title">Nhu cầu và giao hàng</h2></div><div class="scm-readiness"><span>Carrier <x-erp.status-badge :status="$data['fulfillment']['external_carrier']" /></span><span>On-time <x-erp.status-badge :status="$data['fulfillment']['delivery_performance']" /></span></div></div>
        <div class="erp-kpi-grid">
            <x-erp.kpi-card label="ERP Sales open" :value="number_format((int) $data['demand']['erp_sales']['open_orders'])" meta="Confirmed/partially fulfilled" :href="$ownerUrl('sales.view', 'admin.sales.index')" />
            <x-erp.kpi-card label="ERP remaining units" :value="number_format((int) $data['demand']['erp_sales']['remaining_quantity'])" meta="Ordered − fulfilled" :href="$ownerUrl('sales.view', 'admin.sales.index')" />
            <x-erp.kpi-card label="Marketplace pending" :value="number_format((int) $data['demand']['marketplace_pending'])" meta="Confirmed/processing" :href="$ownerUrl('commerce.store.view', 'admin.seller.orders.index')" />
            <x-erp.kpi-card label="Ready to ship" :value="number_format((int) $data['fulfillment']['ready'])" meta="Pending/packed" :href="$ownerUrl('commerce.store.view', 'admin.seller.orders.index')" />
            <x-erp.kpi-card label="In transit" :value="number_format((int) $data['fulfillment']['in_transit'])" meta="Marketplace shipment evidence" :href="$ownerUrl('commerce.store.view', 'admin.seller.orders.index')" />
            <x-erp.kpi-card label="Delivered" :value="number_format((int) $data['fulfillment']['delivered'])" meta="Canonical fulfillment status" :href="$ownerUrl('commerce.store.view', 'admin.seller.orders.index')" />
        </div>
    </section>

    <section class="admin-panel scm-fulfillment"><div class="admin-panel__header"><div><p class="admin-eyebrow">FULFILLMENT CONTROL</p><h2>Điều phối thực hiện đơn hàng</h2><p>Thao tác được thực hiện tại Sales/Commerce owner workspace.</p></div>@can('sales.view')<a class="admin-button admin-button--ghost" href="{{ route('admin.sales.index',['company_id'=>$companyId]) }}">Mở ERP Sales</a>@endcan</div><div class="admin-table-wrap" tabindex="0"><table class="admin-table"><thead><tr><th>Reference</th><th>Customer</th><th>SKU / Product</th><th>Ordered</th><th>Fulfilled</th><th>Remaining</th><th>Status</th><th>COGS evidence</th></tr></thead><tbody>@forelse($data['details'] as $row)<tr><td class="code">@can('sales.view')<a class="erp-source-link" href="{{ route('admin.sales.index',['company_id'=>$companyId,'sales_order_id'=>$row->sales_order_id ?? $row->order_id ?? null]) }}" title="Mở đơn hàng tại ERP Sales">{{ $row->reference }}</a>@else{{ $row->reference }}@endcan</td><td>{{ $row->customer }}</td><td>{{ $row->sku_snapshot ?: '—' }}<br><small>{{ $row->description }}</small></td><td>{{ number_format((int) $row->quantity) }}</td><td>{{ number_format((int) $row->fulfilled_quantity) }}</td><td>{{ number_format((int) ($row->quantity-$row->fulfilled_quantity)) }}</td><td><x-erp.status-badge :status="$row->status" /></td><td class="money">{{ number_format((int) $row->cogs_minor,0,',','.') }} ₫</td></tr>@empty<tr><td colspan="8"><div class="erp-empty"><strong>Chưa có Sales demand trong phạm vi công ty.</strong><p>KPI phía trên vẫn phản ánh dữ liệu canonical hiện tại; không tạo đơn mẫu để lấp khoảng trống.</p></div></td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection

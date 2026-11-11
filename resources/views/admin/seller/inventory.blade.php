@extends('layouts.admin')
@section('title','Tồn kho Seller')
@section('content')
@php
    $visibleInventory = $items->getCollection();
    $visibleOnHand = $visibleInventory->sum(fn ($item) => (int) $item->on_hand);
    $visibleReserved = $visibleInventory->sum(fn ($item) => (int) $item->reserved);
    $visibleAvailable = $visibleInventory->sum(fn ($item) => (int) $item->available);
    $visibleLowStock = $visibleInventory->filter(fn ($item) => (int) $item->available <= $lowStock)->count();
@endphp
<x-erp.dashboard title="Inventory command center" description="Canonical stock balances, availability and governed adjustment sources." class="inventory-command-center">
<x-erp.dashboard-section title="Inventory snapshot" description="Số liệu trên trang kết quả hiện tại; không suy diễn thành tồn kho toàn công ty.">
<section class="erp-kpi-grid" aria-label="Tóm tắt tồn kho trên trang hiện tại">
    <x-erp.kpi-card label="SKU trên trang" :value="number_format($visibleInventory->count())" meta="Current paginated result" :href="route('admin.seller.inventory',['company_id'=>$companyId])" action-label="Mở danh sách SKU →" />
    <x-erp.kpi-card label="On hand" :value="number_format($visibleOnHand)" meta="Canonical balances on this page" :href="route('admin.seller.inventory.movements',['company_id'=>$companyId])" action-label="Xem movement nguồn →" />
    <x-erp.kpi-card label="Reserved" :value="number_format($visibleReserved)" meta="Reservation hiện hành trên trang" :href="route('admin.seller.inventory',['company_id'=>$companyId])" action-label="Xem balance →" />
    <x-erp.kpi-card label="Available" :value="number_format($visibleAvailable)" meta="On hand trừ reserved" :href="route('admin.seller.inventory',['company_id'=>$companyId])" action-label="Xem SKU khả dụng →" />
    <x-erp.kpi-card label="Low-stock SKU" :value="number_format($visibleLowStock)" :meta="'Available ≤ '.number_format($lowStock).' trên trang'" :href="route('admin.seller.inventory',['company_id'=>$companyId])" action-label="Xem trạng thái tồn kho →" />
</section>
</x-erp.dashboard-section>
<x-erp.dashboard-section title="Stock balances" description="Mỗi dòng truy ngược về SKU, Store, Branch và workflow điều chỉnh được quản trị.">
<x-erp.data-table title="Tồn kho Variant / SKU" description="Balance canonical theo Seller, Store và Branch; điều chỉnh chỉ qua Stock Count governance." label="Danh sách tồn kho Variant và SKU">
    <table><thead><tr><th scope="col">Ảnh</th><th scope="col">Product</th><th scope="col">Variant / SKU</th><th scope="col">Store / Branch</th><th scope="col" class="numeric">On hand</th><th scope="col" class="numeric">Reserved</th><th scope="col" class="numeric">Available</th><th scope="col">Trạng thái</th><th scope="col">Governed adjustment</th></tr></thead><tbody>
    @forelse($items as $item)<tr id="variant-{{ $item->id }}"><td>@if($item->delivery_url)<img class="table-avatar" src="{{ $item->delivery_url }}" alt="Ảnh {{ $item->product_name }}" loading="lazy" decoding="async" data-media-image>@else<span class="table-avatar-placeholder" data-media-state="no-media" aria-label="NO_MEDIA">NO_MEDIA</span>@endif</td><td>{{ $item->product_name }}</td><td>{{ $item->variant_name }}<small>{{ $item->sku }}</small></td><td>{{ $item->store_name }}<br><small>{{ $item->branch_name ?: 'Kho chưa gắn chi nhánh' }}</small></td><td class="numeric">{{ $item->on_hand }}</td><td class="numeric">{{ $item->reserved }}</td><td class="numeric"><strong>{{ $item->available }}</strong>@if($item->available<=$lowStock)<span class="status-pill is-warning">Sắp hết</span>@endif</td><td><x-erp.status-badge :status="$item->status" /></td><td>@can('stock-count.create')<a class="erp-source-link" href="{{ route('admin.seller.inventory.governance',['company_id'=>$companyId]) }}"><strong>Create Stock Count</strong><small>Count → Variance → Approval → Movement → Journal</small></a>@else<span>Restricted source</span>@endcan</td></tr>@empty<tr><td colspan="9"><x-erp.empty-state title="Chưa có tồn kho của công ty này" description="Tồn kho chỉ xuất hiện từ nghiệp vụ nhập, chuyển hoặc điều chỉnh được phê duyệt." /></td></tr>@endforelse
    </tbody></table>
    <x-slot:pagination>{{ $items->withQueryString()->links() }}</x-slot:pagination>
</x-erp.data-table>
</x-erp.dashboard-section>
</x-erp.dashboard>
@endsection

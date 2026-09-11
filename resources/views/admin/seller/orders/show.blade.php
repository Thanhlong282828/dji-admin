@extends('layouts.admin')
@section('title', $order->order_number)
@section('content')
<section class="page-hero panel"><span class="eyebrow">Company → Seller → Order → Payment → Fulfillment → Revenue</span><h2>{{ $order->order_number }}</h2><p>{{ $order->store_name_snapshot }} · Order {{ $order->status }} · Payment {{ $order->payment_status }} · {{ number_format($order->total_minor, 0, ',', '.') }} ₫</p></section>
<section class="detail-grid"><article class="panel"><h3>Giao hàng</h3><strong>{{ $address['recipient_name'] }} · {{ $address['phone'] }}</strong><p>{{ $address['address_line'] }}, {{ $address['ward'] }}, {{ $address['district'] }}, {{ $address['province'] }}</p></article><article class="panel"><h3>Items</h3>@foreach($items as $item)<p><strong>{{ $item->product_name_snapshot }}</strong><br>{{ $item->variant_label_snapshot }} · {{ $item->sku_snapshot }} · SL {{ $item->quantity }}</p>@endforeach</article></section>
@if($order->status === 'pending_payment')<section class="notice">Finance/System Admin phải ghi nhận Payment succeeded trước. Seller không thể tự nâng Order thành paid.</section>@endif
@if($order->status === 'pending_payment' && $order->payment_status === 'paid')<form method="POST" action="{{ route('admin.seller.orders.transition', $order->id) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="confirm"><button class="button">Xác nhận Order đã paid</button></form>@endif
<section class="panel"><h3>Fulfillment</h3>
@if($fulfillment === null)
    @if($order->status === 'confirmed' && $order->payment_status === 'paid')
        <form method="POST" action="{{ route('admin.seller.orders.fulfillments.store', $order->id) }}" class="filter-grid">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><label>Branch xuất kho<select name="branch_id" required><option value="">Chọn Branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }} — {{ $branch->code }}</option>@endforeach</select></label><button class="button">Tạo Fulfillment</button></form>
    @else<p>Fulfillment chỉ được tạo sau Payment succeeded và Seller confirm.</p>@endif
@else
    <dl class="detail-grid"><div><dt>Status</dt><dd>{{ $fulfillment->status }}</dd></div><div><dt>Reference</dt><dd>{{ $fulfillment->reference }}</dd></div><div><dt>Carrier</dt><dd>{{ $fulfillment->carrier ?: '—' }}</dd></div><div><dt>Tracking</dt><dd>{{ $fulfillment->tracking_reference ?: '—' }}</dd></div></dl>
    @if($fulfillment->status === 'pending')
        <form method="POST" action="{{ route('admin.seller.orders.fulfillments.transition', $order->id) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="pack"><button class="button">Đóng gói</button></form>
    @elseif($fulfillment->status === 'packed')
        <form method="POST" action="{{ route('admin.seller.orders.fulfillments.transition', $order->id) }}" class="filter-grid">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="ship"><label>Carrier<input name="carrier" required maxlength="80"></label><label>Tracking reference<input name="tracking_reference" required maxlength="120"></label><button class="button">Xuất kho và giao vận</button></form>
    @elseif($fulfillment->status === 'shipped')
        <form method="POST" action="{{ route('admin.seller.orders.fulfillments.transition', $order->id) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="deliver"><button class="button">Xác nhận đã giao</button></form>
    @elseif($fulfillment->status === 'delivered')<p class="notice">Đã giao. Inventory reservation đã consumed; paid Order đủ điều kiện ghi nhận Branch revenue idempotently.</p>@endif
@endif
</section>
@endsection

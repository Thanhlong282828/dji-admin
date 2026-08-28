@extends('layouts.public')
@section('title', 'Đơn mua')
@section('content')
<section class="market-page-hero compact"><small>BUYER ORDERS</small><h1>Đơn mua</h1><p>Theo dõi các Order tách theo từng Store.</p></section>
<section class="market-section">
<form class="order-filter"><input name="q" value="{{ request('q') }}" placeholder="Tìm mã đơn"><input type="date" name="from" value="{{ request('from') }}" aria-label="Từ ngày"><input type="date" name="to" value="{{ request('to') }}" aria-label="Đến ngày"><select name="status"><option value="">Tất cả trạng thái</option>@foreach(['pending_payment'=>'Chờ thanh toán','confirmed'=>'Đã xác nhận','processing'=>'Đang xử lý','cancelled'=>'Đã hủy'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select><button class="market-primary">Lọc</button></form>
<div class="order-cards">@forelse($orders as $order)
<article class="order-card"><header><div><small>{{ $order->placed_at }}</small><h2>{{ $order->order_number }}</h2></div><span class="order-status">{{ $order->status }}</span></header><div class="order-card-product">@if($order->cover_url)<img src="{{ $order->cover_url }}" alt="">@else<span>▧</span>@endif<p>{{ $order->store_name_snapshot }} · {{ $order->quantity }} sản phẩm</p></div><footer><strong>{{ number_format($order->total_minor,0,',','.') }} ₫</strong><a class="market-secondary" href="{{ route('orders.show',$order->id) }}">Xem đơn</a></footer></article>
@empty<div class="market-empty"><h2>Chưa có đơn hàng</h2><a class="market-primary" href="{{ route('public.products.index') }}">Mua sắm</a></div>@endforelse</div>{{ $orders->links() }}
</section>
@endsection

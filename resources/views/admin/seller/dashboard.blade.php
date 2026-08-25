@extends('layouts.admin') @section('title','Seller Dashboard') @section('content')
<section class="page-hero panel"><span class="eyebrow">Seller Center</span><h2>{{ $seller?->display_name ?? 'Chưa có hồ sơ Seller' }}</h2><p>Trạng thái kinh doanh và độ hoàn thiện cửa hàng. Không hiển thị số bán hàng khi Product/Order chưa được triển khai.</p></section>
<section class="summary-grid"><article class="summary-card"><span>Seller status</span><strong>{{ $seller?->status ?? 'N/A' }}</strong></article><article class="summary-card"><span>Cửa hàng</span><strong>{{ $stores->count() }}</strong></article><article class="summary-card"><span>Media</span><strong>{{ $stores->isEmpty()?'Chưa có':'Sẵn sàng' }}</strong></article></section>
@if($seller)<a class="button" href="{{ route('admin.seller.store') }}">Mở Store Profile</a>@else<div class="notice">Hồ sơ Seller cần được nền tảng khởi tạo/xét duyệt trước khi mở cửa hàng.</div>@endif
@endsection

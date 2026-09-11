@extends('layouts.admin')
@section('title','Sản phẩm & Tồn kho')
@section('header_action')
    @can('product.create')<a class="button" href="{{ route('admin.products.create', ['company_id' => $companyId]) }}">+ Thêm sản phẩm</a>@endcan
@endsection
@section('content')
<section class="page-hero panel"><span class="eyebrow">Company → Seller → Store → Product → Variant → Inventory</span><h2>Product Catalog</h2><p>Danh mục và tồn kho chỉ thuộc Seller account của công ty đang chọn.</p></section>
<section class="table-shell"><div class="table-scroll"><table class="data-table"><thead><tr><th>Ảnh</th><th>Mã / Sản phẩm</th><th>Store</th><th>Danh mục</th><th>Giá</th><th>Variants</th><th>Available</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse($products as $product)<tr><td><div class="product-table-media">@if($product->delivery_url)<img src="{{ $product->delivery_url }}" alt="Ảnh {{ $product->name }}" loading="lazy" decoding="async" data-media-image>@else<span data-media-state="no-media" aria-label="NO_MEDIA">NO_MEDIA</span>@endif</div></td><td><strong>{{ $product->name }}</strong><br><small>{{ $product->code }}</small></td><td>{{ $product->store_name }}</td><td>{{ $product->category_name ?: '—' }}</td><td>{{ number_format($product->base_price_minor,0,',','.') }} ₫</td><td>{{ $product->variants_count }}</td><td>{{ $product->available }}</td><td>{{ $product->status }}</td><td><div class="table-actions">@can('product.update')<a href="{{ route('admin.products.edit', ['product' => $product->id, 'company_id' => $companyId]) }}">Sửa</a>@endcan <a href="{{ route('public.products.show',$product->slug) }}">Public</a>@can('product.delete')<form method="POST" action="{{ route('admin.products.destroy',['product'=>$product->id,'company_id'=>$companyId]) }}" onsubmit="return confirm('Lưu trữ sản phẩm này? Lịch sử đơn hàng vẫn được giữ.')">@csrf @method('DELETE')<button class="danger-link">Xóa</button></form>@endcan</div></td></tr>@empty<tr><td colspan="9">Chưa có sản phẩm của công ty này.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}</section>
@endsection

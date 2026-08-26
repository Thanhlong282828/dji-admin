@extends('layouts.admin')
@section('title',$product?'Sửa sản phẩm':'Thêm sản phẩm')
@section('content')<form class="panel form-grid" enctype="multipart/form-data" method="POST" action="{{ $product?route('admin.products.update',$product->id):route('admin.products.store') }}">@csrf @if($product)@method('PUT')@endif
@if($errors->any())<div class="span-2 validation-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<label>Store<select name="store_id" required>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((int)old('store_id',$product->store_id??0)===(int)$store->id)>{{ $store->name }}</option>@endforeach</select></label><label>Danh mục<select name="product_category_id"><option value="">Chưa phân loại</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)old('product_category_id',$product->product_category_id??0)===(int)$category->id)>{{ $category->name }}</option>@endforeach</select></label><label>Mã sản phẩm<input name="code" required value="{{ old('code',$product->code??'') }}"></label><label>Tên sản phẩm<input name="name" required value="{{ old('name',$product->name??'') }}"></label><label>Thương hiệu<input name="brand" value="{{ old('brand',$product->brand??'') }}"></label><label>Giá cơ bản (VND)<input type="number" min="0" name="base_price_minor" required value="{{ old('base_price_minor',$product->base_price_minor??0) }}"></label><input type="hidden" name="currency" value="VND"><label>Trạng thái<select name="status">@foreach(['draft'=>'Nháp','active'=>'Hoạt động','archived'=>'Lưu trữ'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$product->status??'draft')===$value)>{{ $label }}</option>@endforeach</select></label><label class="span-2">Mô tả<textarea name="description">{{ old('description',$product->description??'') }}</textarea></label><label>Ảnh cover<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-media-input data-preview-target="product-cover-preview"><img id="product-cover-preview" class="media-preview" hidden alt="Xem trước cover"></label><label>Gallery<input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" data-media-input data-preview-target="product-gallery-preview"><span id="product-gallery-preview" class="media-grid"></span></label>
<fieldset class="span-2" data-product-variants>
    <legend>Variants / SKU / Inventory</legend>
    @php($variantRows=old('variants',isset($variants)?$variants->map(fn($v)=>(array)$v)->all():[['sku'=>'','name'=>'Mặc định','attributes'=>'','price_minor'=>$product->base_price_minor??0,'status'=>'active','on_hand'=>0,'reserved'=>0]]))
    <div data-product-variant-rows>
        @foreach($variantRows as $index=>$variant)
            <div class="form-grid panel" data-product-variant-row>
                @if(data_get($variant,'id'))
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ data_get($variant,'id') }}">
                @endif
                <label>SKU<input required name="variants[{{ $index }}][sku]" value="{{ data_get($variant,'sku') }}"></label>
                <label>Tên variant<input required name="variants[{{ $index }}][name]" value="{{ data_get($variant,'name') }}"></label>
                <label>Thuộc tính<input name="variants[{{ $index }}][attributes]" value="{{ is_array(data_get($variant,'attributes'))?data_get($variant,'attributes.label'):data_get($variant,'attributes') }}" placeholder="Màu đen · 256GB"></label>
                <label>Giá VND<input type="number" min="0" required name="variants[{{ $index }}][price_minor]" value="{{ data_get($variant,'price_minor',0) }}"></label>
                <label>On hand<input type="number" min="0" required name="variants[{{ $index }}][on_hand]" value="{{ data_get($variant,'on_hand',0) }}"></label>
                <label>Reserved<input type="number" min="0" required name="variants[{{ $index }}][reserved]" value="{{ data_get($variant,'reserved',0) }}"></label>
                <input type="hidden" name="variants[{{ $index }}][status]" value="active">
                @unless(data_get($variant,'id'))
                    <button class="secondary-button" type="button" data-remove-product-variant>Gỡ variant</button>
                @endunless
            </div>
        @endforeach
    </div>
    <button class="secondary-button" type="button" data-add-product-variant>+ Thêm variant</button>
    <template data-product-variant-template>
        <div class="form-grid panel" data-product-variant-row>
            <label>SKU<input required data-name="sku"></label>
            <label>Tên variant<input required data-name="name"></label>
            <label>Thuộc tính<input data-name="attributes" placeholder="Màu đen · 256GB"></label>
            <label>Giá VND<input type="number" min="0" value="0" required data-name="price_minor"></label>
            <label>On hand<input type="number" min="0" value="0" required data-name="on_hand"></label>
            <label>Reserved<input type="number" min="0" value="0" required data-name="reserved"></label>
            <input type="hidden" value="active" data-name="status">
            <button class="secondary-button" type="button" data-remove-product-variant>Gỡ variant</button>
        </div>
    </template>
</fieldset>
<div class="span-2 hero-actions"><button class="button">Lưu sản phẩm</button><a class="secondary-button" href="{{ route('admin.products.index') }}">Hủy</a></div></form>@endsection

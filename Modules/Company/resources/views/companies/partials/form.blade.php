@csrf
@if(isset($company)) @method('PUT') @endif
<section class="panel fields company-form-grid">
    <label>Mã công ty *<input name="code" value="{{ old('code', $company->code ?? '') }}" maxlength="30" required></label>
    <label>Tên công ty *<input name="name" value="{{ old('name', $company->name ?? '') }}" required></label>
    <label>Tên viết tắt<input name="short_name" value="{{ old('short_name', $company->shortName ?? '') }}"></label>
    <label>Mã số thuế<input name="tax_code" value="{{ old('tax_code', $company->taxCode ?? '') }}"></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $company->email ?? '') }}"></label>
    <label>Điện thoại<input name="phone" value="{{ old('phone', $company->phone ?? '') }}"></label>
    <label>Website<input type="url" name="website" value="{{ old('website', $company->website ?? '') }}"></label>
    <label>Đại diện pháp luật<input name="legal_representative" value="{{ old('legal_representative', $company->legalRepresentative ?? '') }}"></label>
    <label>Quốc gia *<input name="country_code" value="{{ old('country_code', $company->countryCode ?? 'VN') }}" maxlength="2" required></label>
    <label>Tiền tệ *<input name="currency_code" value="{{ old('currency_code', $company->currencyCode ?? 'VND') }}" maxlength="3" required></label>
    <label>Múi giờ *<input name="timezone" value="{{ old('timezone', $company->timezone ?? 'Asia/Ho_Chi_Minh') }}" required></label>
    <label>Trạng thái *<select name="status" required><option value="active" @selected(old('status',$company->status ?? 'active')==='active')>Đang hoạt động</option><option value="inactive" @selected(old('status',$company->status ?? '')==='inactive')>Ngừng hoạt động</option></select></label>
    <label class="wide">Địa chỉ<input name="address" value="{{ old('address', $company->address ?? '') }}"></label>
    <label class="wide">Mô tả<textarea name="description">{{ old('description', $company->description ?? '') }}</textarea></label>
    <div class="wide form-actions"><a class="secondary-button" href="{{ route('admin.companies.index') }}">Hủy</a><button class="button" type="submit">Lưu công ty</button></div>
</section>
@if($errors->any())<div class="errors"><strong>Dữ liệu chưa hợp lệ:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

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
    @hasanyrole('Super Admin|System Admin')
    <section class="wide panel" data-company-branches>
        <div class="detail-heading"><div><small>ĐỊA ĐIỂM VẬN HÀNH</small><h3>Chi nhánh</h3><p>Branch được lưu cùng transaction Company. Bỏ một dòng hiện hữu không xóa Branch.</p></div><button class="secondary-button" type="button" data-add-company-branch>+ Thêm chi nhánh</button></div>
        <div data-company-branch-rows>
            @php($branchRows=old('branches',isset($branches)?$branches->map(fn($branch)=>(array)$branch)->all():[]))
            @foreach($branchRows as $index=>$branch)
                <div class="form-grid panel" data-company-branch-row>
                    @if(data_get($branch,'id'))<input type="hidden" name="branches[{{ $index }}][id]" value="{{ data_get($branch,'id') }}">@endif
                    <label>Mã chi nhánh *<input name="branches[{{ $index }}][code]" required maxlength="50" value="{{ data_get($branch,'code') }}"></label><label>Tên chi nhánh *<input name="branches[{{ $index }}][name]" required value="{{ data_get($branch,'name') }}"></label><label>Địa chỉ<input name="branches[{{ $index }}][address_line]" value="{{ data_get($branch,'address_line') }}"></label><label>Tỉnh/Thành<input name="branches[{{ $index }}][city]" value="{{ data_get($branch,'city') }}"></label><label>Trạng thái<select name="branches[{{ $index }}][status]">@foreach(['active'=>'Hoạt động','inactive'=>'Tạm ngưng','archived'=>'Lưu trữ'] as $value=>$label)<option value="{{ $value }}" @selected(data_get($branch,'status','active')===$value)>{{ $label }}</option>@endforeach</select></label><input type="hidden" name="branches[{{ $index }}][timezone]" value="{{ data_get($branch,'timezone','Asia/Ho_Chi_Minh') }}">@if(!data_get($branch,'id'))<button class="secondary-button" type="button" data-remove-company-branch>Xóa dòng</button>@endif
                </div>
            @endforeach
        </div>
        <template data-company-branch-template><div class="form-grid panel" data-company-branch-row><label>Mã chi nhánh *<input data-name="code" required maxlength="50"></label><label>Tên chi nhánh *<input data-name="name" required></label><label>Địa chỉ<input data-name="address_line"></label><label>Tỉnh/Thành<input data-name="city"></label><label>Trạng thái<select data-name="status"><option value="active">Hoạt động</option><option value="inactive">Tạm ngưng</option><option value="archived">Lưu trữ</option></select></label><input type="hidden" data-name="timezone" value="Asia/Ho_Chi_Minh"><button class="secondary-button" type="button" data-remove-company-branch>Xóa dòng</button></div></template>
    </section>
    @endhasanyrole
    <div class="wide form-actions"><a class="secondary-button" href="{{ route('admin.companies.index') }}">Hủy</a><button class="button" type="submit">Lưu công ty</button></div>
</section>
@if($errors->any())<div class="errors"><strong>Dữ liệu chưa hợp lệ:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

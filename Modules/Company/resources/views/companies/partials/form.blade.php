@csrf
@if(isset($company)) @method('PUT') @endif
<section class="admin-panel admin-form-grid erp-form-section fields company-form-grid">
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
    @can('commerce.seller.governance.manage')
    <section class="wide panel" data-company-sellers>
        <div class="detail-heading"><div><small>COMPANY → SELLER ACCOUNT → STORE</small><h3>Tài khoản Seller</h3><p>Tạo User có Role Seller và gắn trực tiếp vào Company. Account mới ở trạng thái pending; verify/active tại Seller Governance.</p></div><button class="secondary-button" type="button" data-add-company-seller>+ Tạo acc Seller</button></div>
        <div data-company-seller-rows>
            @php($sellerRows=old('seller_accounts',isset($sellerAccounts)?$sellerAccounts->map(fn($seller)=>(array)$seller)->all():[]))
            @foreach($sellerRows as $index=>$seller)
                <div class="form-grid panel" data-company-seller-row>
                    @if(data_get($seller,'id'))<input type="hidden" name="seller_accounts[{{ $index }}][id]" value="{{ data_get($seller,'id') }}">@endif
                    <label>Tên hiển thị Seller *<input name="seller_accounts[{{ $index }}][display_name]" required maxlength="255" value="{{ data_get($seller,'display_name') }}"></label><label>Tên chủ tài khoản *<input name="seller_accounts[{{ $index }}][account_name]" required maxlength="255" value="{{ data_get($seller,'account_name') }}"></label><label>Email đăng nhập *<input type="email" name="seller_accounts[{{ $index }}][email]" required value="{{ data_get($seller,'email') }}"></label><label>Điện thoại<input name="seller_accounts[{{ $index }}][phone_e164]" maxlength="20" value="{{ data_get($seller,'phone_e164') }}" placeholder="+84901234567"></label><label>Mật khẩu {{ data_get($seller,'id') ? 'mới (để trống nếu giữ nguyên)' : '*' }}<input type="password" name="seller_accounts[{{ $index }}][password]" minlength="12" @required(!data_get($seller,'id')) autocomplete="new-password"></label><label>Xác nhận mật khẩu<input type="password" name="seller_accounts[{{ $index }}][password_confirmation]" minlength="12" autocomplete="new-password"></label><div><small>Role</small><strong>Seller</strong></div><div><small>Trạng thái Governance</small><span class="status-pill is-{{ data_get($seller,'status','pending') }}">{{ data_get($seller,'status','pending') }}</span></div>
                    @if(!data_get($seller,'id'))<button class="secondary-button" type="button" data-remove-company-seller>Xóa dòng</button>@endif
                </div>
            @endforeach
        </div>
        <template data-company-seller-template><div class="form-grid panel" data-company-seller-row><label>Tên hiển thị Seller *<input data-name="display_name" required maxlength="255"></label><label>Tên chủ tài khoản *<input data-name="account_name" required maxlength="255"></label><label>Email đăng nhập *<input data-name="email" type="email" required></label><label>Điện thoại<input data-name="phone_e164" maxlength="20" placeholder="+84901234567"></label><label>Mật khẩu *<input data-name="password" type="password" required minlength="12" autocomplete="new-password"></label><label>Xác nhận mật khẩu<input data-name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label><div><small>Role</small><strong>Seller</strong></div><div><small>Trạng thái ban đầu</small><span class="status-pill is-pending">pending</span></div><button class="secondary-button" type="button" data-remove-company-seller>Xóa dòng</button></div></template>
    </section>
    @endcan
    <div class="wide admin-button-group form-actions"><a class="admin-button admin-button--secondary secondary-button" href="{{ route('admin.companies.index') }}">Hủy</a><button class="admin-button admin-button--primary button" type="submit">Lưu công ty</button></div>
</section>
@if($errors->any())<div class="errors"><strong>Dữ liệu chưa hợp lệ:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

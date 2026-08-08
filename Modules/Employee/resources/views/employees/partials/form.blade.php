@if($errors->any())<div class="validation-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<input type="hidden" name="company_id" value="{{ $companyId }}">
<div class="form-grid">
<label>Mã nhân viên<input name="employee_code" value="{{ old('employee_code',$item['employee_code']??'') }}" required></label>
<label>Họ tên<input name="full_name" value="{{ old('full_name',$item['full_name']??'') }}" required></label>
<label>Email công việc<input type="email" name="work_email" value="{{ old('work_email',$item['work_email']??'') }}"></label>
<label>Email cá nhân<input type="email" name="personal_email" value="{{ old('personal_email',$item['personal_email']??'') }}"></label>
<label>Điện thoại<input name="phone" value="{{ old('phone',$item['phone']??'') }}"></label>
<label>Ngày vào làm<input type="date" name="join_date" value="{{ old('join_date',isset($item['join_date'])?substr($item['join_date'],0,10):'') }}" required></label>
<label>Ngày chính thức<input type="date" name="official_date" value="{{ old('official_date',isset($item['official_date'])?substr($item['official_date'],0,10):'') }}"></label>
<label>Trạng thái<select name="employment_status">@foreach(['preboarding','probation','active','suspended','leave','terminated','alumni'] as $status)<option value="{{ $status }}" @selected(old('employment_status',$item['employment_status']??'preboarding')===$status)>{{ $status }}</option>@endforeach</select></label>
<label>Loại hình<select name="employment_type">@foreach(['full_time','part_time','contractor','intern'] as $type)<option value="{{ $type }}" @selected(old('employment_type',$item['employment_type']??'full_time')===$type)>{{ $type }}</option>@endforeach</select></label>
<label>Quốc tịch<input name="nationality" value="{{ old('nationality',$item['nationality']??'VN') }}" maxlength="2"></label>
<label>Số định danh<input name="identity_number" autocomplete="off" placeholder="{{ isset($item)?'Để trống nếu không đổi':'' }}"></label>
<label>Manager ID<input type="number" name="manager_id" value="{{ old('manager_id',$item['manager_id']??'') }}"></label>
</div><button type="submit">Lưu hồ sơ</button>

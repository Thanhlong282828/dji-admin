@extends('layouts.admin')
@section('title',$branch?'Chỉnh sửa chi nhánh':'Thêm chi nhánh')
@section('content')
<form class="panel form-grid" method="post" action="{{ $branch?route('admin.branches.update',['branch'=>$branch->id]):route('admin.branches.store') }}">@csrf @if($branch)@method('put')@endif
<input type="hidden" name="company_id" value="{{ $companyId }}">
<label>Mã chi nhánh<input name="code" required maxlength="50" value="{{ old('code',$branch->code??'') }}"></label>
<label>Tên chi nhánh<input name="name" required value="{{ old('name',$branch->name??'') }}"></label>
<label>Trạng thái<select name="status">@foreach(['active'=>'Hoạt động','inactive'=>'Tạm ngưng','archived'=>'Lưu trữ'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$branch->status??'active')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="span-2">Lãnh đạo chi nhánh<select name="leader_employee_id"><option value="">Chưa gán lãnh đạo</option>@foreach($leaders as $leader)<option value="{{ $leader->id }}" @selected((int)old('leader_employee_id',$branch->leader_employee_id??0)===(int)$leader->id)>{{ $leader->full_name }} — {{ $leader->position_title }} — {{ $leader->unit_name }}</option>@endforeach</select>@error('leader_employee_id')<small class="field-error">{{ $message }}</small>@enderror</label>
<label>Múi giờ<input name="timezone" value="{{ old('timezone',$branch->timezone??'Asia/Ho_Chi_Minh') }}" required></label>
<label class="span-2">Địa chỉ<input name="address_line" value="{{ old('address_line',$branch->address_line??'') }}"></label>
<label>Thành phố<input name="city" value="{{ old('city',$branch->city??'') }}"></label><label>Điện thoại<input name="phone" value="{{ old('phone',$branch->phone??'') }}"></label><label>Email<input type="email" name="email" value="{{ old('email',$branch->email??'') }}"></label>
<div class="span-2 hero-actions"><button class="button">Lưu</button><a class="button secondary" href="{{ route('admin.branches.index',['company_id'=>$companyId]) }}">Hủy</a></div></form>
@endsection

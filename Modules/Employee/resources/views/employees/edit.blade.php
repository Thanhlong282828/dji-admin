@extends('layouts.admin')
@section('title','Chỉnh sửa nhân viên')
@section('header_action')<a class="button" href="{{ route('admin.employees.show',['employee'=>$item['id'],'company_id'=>$companyId]) }}">Xem hồ sơ</a>@endsection
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.employees.update',['employee'=>$item['id']]) }}">
    @csrf @method('PUT')
    <x-erp.form-shell title="Cập nhật hồ sơ nhân viên" description="Giữ nguyên phân quyền, tenant scope và lịch sử nghiệp vụ của hồ sơ hiện tại.">
        @include('employee::employees.partials.form')
    </x-erp.form-shell>
</form>
@endsection

@extends('layouts.admin')
@section('title','Thêm nhân viên')
@section('header_action')<a class="button" href="{{ route('admin.employees.index',['company_id'=>$companyId]) }}">Danh sách</a>@endsection
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.employees.store') }}">
    @csrf
    <x-erp.form-shell title="Tạo hồ sơ nhân viên" description="Thông tin nhân sự, tổ chức và quyền truy cập được lưu qua workflow Employee hiện hữu.">
        @include('employee::employees.partials.form')
    </x-erp.form-shell>
</form>
@endsection

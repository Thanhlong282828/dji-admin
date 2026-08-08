@extends('layouts.admin')
@section('title','Chỉnh sửa công ty')
@section('header_action')<a class="secondary-button" href="{{ route('admin.companies.show',$company->id) }}">Xem chi tiết</a>@endsection
@section('content')<form method="POST" action="{{ route('admin.companies.update',$company->id) }}">@include('company::companies.partials.form')</form>@endsection

@extends('layouts.admin')
@section('title','Thêm công ty')
@section('header_action')<a class="secondary-button" href="{{ route('admin.companies.index') }}">Quay lại danh sách</a>@endsection
@section('content')<form method="POST" action="{{ route('admin.companies.store') }}">@include('company::companies.partials.form')</form>@endsection

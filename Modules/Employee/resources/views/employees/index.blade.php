@extends('layouts.admin')
@section('title','Danh sách nhân viên')
@section('header_action')
    @can('employee.create')
        <a class="button" href="{{ route('admin.employees.create',['company_id'=>$companyId]) }}">+ Thêm nhân viên</a>
    @endcan
@endsection
@section('content')
<section class="panel">
<form method="GET" class="filter-bar"><input type="hidden" name="company_id" value="{{ $companyId }}"><input name="search" value="{{ request('search') }}" placeholder="Mã, tên, email, điện thoại"><select name="employment_status"><option value="">Tất cả trạng thái</option>@foreach(['preboarding','probation','active','suspended','leave','terminated','alumni'] as $status)<option value="{{ $status }}" @selected(request('employment_status')===$status)>{{ $status }}</option>@endforeach</select><select name="employment_type"><option value="">Tất cả loại hình</option>@foreach(['full_time','part_time','contractor','intern'] as $type)<option value="{{ $type }}" @selected(request('employment_type')===$type)>{{ $type }}</option>@endforeach</select><button>Lọc</button>@can('employee.create')<a href="{{ route('admin.employees.create',['company_id'=>$companyId]) }}">+ Thêm nhân viên</a>@endcan</form>
@if(session('success'))<div class="flash-success">{{ session('success') }}</div>@endif
<div class="table-wrap"><table><thead><tr><th>Mã</th><th>Nhân viên</th><th>Email</th><th>Đơn vị / Vị trí</th><th>Quản lý</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse($items as $item) @php($assignment=$item['assignments'][0]??null)<tr><td>{{ $item['employee_code'] }}</td><td><b>{{ $item['full_name'] }}</b></td><td>{{ $item['work_email']?:$item['personal_email'] }}</td><td>{{ data_get($assignment,'organization_unit.name','—') }}<br><small>{{ data_get($assignment,'job_position.title','—') }}</small></td><td>{{ data_get($item,'manager.full_name','—') }}</td><td>{{ $item['employment_status'] }}</td><td><a href="{{ route('admin.employees.show',['employee'=>$item['id'],'company_id'=>$companyId]) }}">Xem</a> @can('employee.update')<a href="{{ route('admin.employees.edit',['employee'=>$item['id'],'company_id'=>$companyId]) }}">Sửa</a>@endcan</td></tr>@empty<tr><td colspan="7" class="empty-state">Chưa có hồ sơ nhân viên phù hợp.</td></tr>@endforelse
</tbody></table></div>{{ $items->withQueryString()->links() }}</section>
@endsection

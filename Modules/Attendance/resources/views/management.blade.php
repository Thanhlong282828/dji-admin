@extends('layouts.admin')
@section('title', 'Lịch và ca làm việc')
@section('content')
<section class="panel" data-company-id="{{ $companyId }}" data-workforce-management>
    <span class="eyebrow">WORKFORCE OPERATIONS</span>
    <h1>Lịch và ca làm việc</h1>
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <nav class="tabs">
        @foreach(['calendars'=>'Lịch làm việc','holidays'=>'Ngày lễ','shifts'=>'Ca làm việc','assignments'=>'Phân ca'] as $key=>$label)
            <a href="{{ route('admin.workforce.management', ['section'=>$key,'company_id'=>$companyId]) }}" class="{{ $viewModel->section === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET"><input type="hidden" name="company_id" value="{{ $companyId }}"><input name="search" value="{{ request('search') }}" placeholder="Tìm mã hoặc tên"><button>Lọc</button></form>

    @php($resource = ['calendars'=>'calendar','holidays'=>'holiday','shifts'=>'shift','assignments'=>'assignment'][$viewModel->section])
    @can(in_array($resource,['calendar','holiday']) ? 'work-calendar.manage' : ($resource === 'assignment' ? 'shift.assign' : 'shift.manage'))
    <details><summary>Thêm mới</summary><form method="POST" action="{{ route('admin.workforce.save', $resource) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}">
        @if($resource==='calendar')
            <input name="code" required placeholder="Mã lịch"><input name="name" required placeholder="Tên lịch"><input name="timezone" value="Asia/Ho_Chi_Minh" required><input type="number" name="weekly_work_minutes" value="2400" required><select name="status"><option value="active">Hoạt động</option><option value="inactive">Ngừng</option></select><input type="date" name="effective_from" required><input type="date" name="effective_to">
        @elseif($resource==='holiday')
            <select name="work_calendar_id"><option value="">Toàn công ty</option>@foreach($viewModel->calendars as $c)<option value="{{ $c['id'] }}">{{ $c['code'] }} — {{ $c['name'] }}</option>@endforeach</select><input type="date" name="holiday_date" required><input name="name" required placeholder="Tên ngày lễ"><input name="country_code" value="VN" maxlength="2"><input name="region" placeholder="Khu vực"><label><input type="checkbox" name="is_paid" value="1" checked>Có lương</label><label><input type="checkbox" name="recurring" value="1">Lặp hàng năm</label><label><input type="checkbox" name="is_working_override" value="1">Ngày làm bù</label>
        @elseif($resource==='shift')
            <input name="code" required placeholder="Mã ca"><input name="name" required placeholder="Tên ca"><input name="timezone" value="Asia/Ho_Chi_Minh" required><input type="time" name="starts_at" required><input type="time" name="ends_at" required><label><input type="checkbox" name="crosses_midnight" value="1">Qua đêm</label><input type="number" name="planned_work_minutes" value="480" min="1" required><input type="number" name="grace_in_minutes" value="0" min="0" required><input type="number" name="grace_out_minutes" value="0" min="0" required><select name="rounding_rule"><option value="none">Không làm tròn</option><option value="nearest_5">Gần nhất 5 phút</option><option value="nearest_15">Gần nhất 15 phút</option></select><input type="number" name="minimum_half_day_minutes" value="240" required><input type="number" name="minimum_full_day_minutes" value="480" required><select name="status"><option value="active">Hoạt động</option><option value="inactive">Ngừng</option></select>
        @else
            <select name="employee_id" required>@foreach($viewModel->employees as $e)<option value="{{ $e['id'] }}">{{ $e['employee_code'] }} — {{ $e['name'] }}</option>@endforeach</select><select name="work_shift_id" required>@foreach($viewModel->shifts as $s)<option value="{{ $s['id'] }}">{{ $s['code'] }} — {{ $s['name'] }}</option>@endforeach</select><select name="assignment_type"><option value="fixed">Cố định</option><option value="temporary">Tạm thời</option><option value="rotation">Xoay ca</option></select><input type="date" name="effective_from" required><input type="date" name="effective_to">
        @endif
        <button type="submit">Lưu</button>
    </form></details>
    @endcan
    <div class="table-wrap"><table><thead><tr>@if(!empty($viewModel->rows))@foreach(array_keys($viewModel->rows[0]) as $column)<th>{{ str_replace('_',' ',strtoupper($column)) }}</th>@endforeach<th>Thao tác</th>@endif</tr></thead><tbody>
    @forelse($viewModel->rows as $row)<tr>@foreach($row as $value)<td>{{ is_bool($value) ? ($value ? 'Có' : 'Không') : $value }}</td>@endforeach<td>@if($resource==='calendar')<a href="{{route('admin.workforce.calendar-days',['calendar'=>$row['id'],'company_id'=>$companyId])}}">Sửa 7 ngày</a>@elseif($resource==='shift')<a href="{{route('admin.workforce.shift-breaks',['shift'=>$row['id'],'company_id'=>$companyId])}}">Break rules</a>@endif @can(in_array($resource,['calendar','holiday']) ? 'work-calendar.manage' : ($resource === 'assignment' ? 'shift.assign' : 'shift.manage'))<form method="POST" action="{{ route('admin.workforce.destroy', [$resource,$row['id']]) }}" onsubmit="return confirm('Xác nhận xóa?')">@csrf @method('DELETE')<input type="hidden" name="company_id" value="{{ $companyId }}"><button>Xóa</button></form>@endcan</td></tr>@empty<tr><td>Chưa có dữ liệu phù hợp.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection

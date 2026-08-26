@extends('layouts.admin')
@section('title', $item['full_name'])
@section('content')
<section class="panel employee-profile">
    @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
    <header class="profile-header">
        @if(!empty($item['avatar_storage_key']))
            <img class="avatar" src="{{ route('admin.employees.avatar.show',['employee'=>$item['id'],'company_id'=>$companyId]) }}" alt="Avatar của {{ $item['full_name'] }}">
        @else
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($item['full_name'],0,1)) }}</span>
        @endif
        <div>
            <small>{{ $item['employee_code'] }}</small>
            <h2>{{ $item['full_name'] }}</h2>
            <p>{{ $item['work_email'] ?? 'Chưa có email công việc' }} · {{ $item['phone'] ?? 'Chưa có điện thoại' }}</p>
        </div>
        @can('employee.update')
            <a href="{{ route('admin.employees.edit', ['employee' => $item['id'], 'company_id' => $companyId]) }}">Chỉnh sửa</a>
        @endcan
        @if(!empty($item['avatar_storage_key']))
            @can('employee.manage-avatar')
                <form method="POST" action="{{ route('admin.employees.avatar.destroy', ['employee' => $item['id']]) }}">
                    @csrf @method('DELETE')
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <button type="submit">Gỡ avatar</button>
                </form>
            @endcan
        @endif
    </header>

    <nav class="tabs" aria-label="Hồ sơ nhân viên">
        <a href="#overview">Tổng quan</a>
        <a href="#work">Công việc</a>
        @can('employee-assignment.view')<a href="#assignments">Phân công ({{ count($item['assignments'] ?? []) }})</a>@endcan
        @can('employee-history.view')<a href="#history">Lịch sử ({{ count($item['histories'] ?? []) }})</a>@endcan
        @can('employee-credential.view')<a href="#credentials">Chứng chỉ ({{ count($item['credentials'] ?? []) }})</a>@endcan
        @can('employment-contract.view')<a href="#contracts">Hợp đồng ({{ count($item['contracts'] ?? []) }})</a>@endcan
        @can('employee-document.view')<a href="#documents">Tài liệu ({{ count($item['documents'] ?? []) }})</a>@endcan
        @can('data-consent.view')<a href="#consents">Đồng ý dữ liệu ({{ count($item['consents'] ?? []) }})</a>@endcan
        @can('employee-user.view')<a href="#account">Tài khoản</a>@endcan
    </nav>

    <article id="overview"><h3>Tổng quan</h3><p>Trạng thái: {{ $item['employment_status'] }}</p><p>Ngày sinh: {{ !empty($item['date_of_birth']) ? \Carbon\CarbonImmutable::parse($item['date_of_birth'])->format('d/m/Y').' · '.\Carbon\CarbonImmutable::parse($item['date_of_birth'])->age.' tuổi' : '—' }}</p><p>Giới tính: {{ $item['gender'] ?? '—' }}</p><p>Định danh: {{ $item['identity_number'] ?: 'Chưa cập nhật' }}</p></article>
    <article id="work"><h3>Công việc</h3><p>Ngày vào làm: {{ $item['join_date'] }}</p><p>Loại hình: {{ $item['employment_type'] }}</p></article>

    @can('employee-assignment.view')
    <article id="assignments">
        <h3>Phân công</h3>
        <a href="{{ route('admin.employees.assignments.index', ['employee' => $item['id'], 'company_id' => $companyId]) }}">Quản lý phân công</a>
        @forelse($item['assignments'] ?? [] as $assignment)
            <p>{{ data_get($assignment, 'job_position.title', 'Chưa có vị trí') }} · {{ data_get($assignment, 'organization_unit.name', 'Chưa có đơn vị') }} · {{ $assignment['allocation_percent'] }}%</p>
        @empty <p>Chưa có phân công.</p> @endforelse
    </article>
    @endcan

    @can('employee-history.view')
    <article id="history"><h3>Lịch sử</h3>
        @forelse($item['histories'] ?? [] as $history)<p>{{ $history['event_type'] }} · {{ $history['effective_at'] }} — {{ $history['reason'] ?? 'Không có ghi chú' }}</p>@empty<p>Chưa có lịch sử.</p>@endforelse
    </article>
    @endcan

    @can('employee-credential.view')
    <article id="credentials"><h3>Chứng chỉ</h3>
        @forelse($item['credentials'] ?? [] as $credential)<p>{{ $credential['name'] }} · {{ $credential['type'] }} · hết hạn {{ $credential['expires_at'] ?? 'không thời hạn' }}</p>@empty<p>Chưa có chứng chỉ.</p>@endforelse
    </article>
    @endcan

    @can('employment-contract.view')
    <article id="contracts"><h3>Hợp đồng</h3>
        @forelse($item['contracts'] ?? [] as $contract)
            <p>{{ $contract['contract_number'] }} · {{ $contract['type'] }} · {{ $contract['status'] }}
                @can('employment-contract.view-salary') · {{ number_format((float) $contract['base_salary'], 0, ',', '.') }} {{ $contract['currency'] }} @endcan
            </p>
        @empty<p>Chưa có hợp đồng.</p>@endforelse
    </article>
    @endcan

    @can('employee-document.view')
    <article id="documents"><h3>Tài liệu riêng tư</h3>
        @forelse($item['documents'] ?? [] as $document)
            <p>{{ $document['name'] }} · v{{ $document['version'] }} · {{ $document['scan_status'] ?? 'pending' }}
                @can('employee-document.download')<a href="{{ route('admin.employee-documents.download', ['document' => $document['id'], 'company_id' => $companyId]) }}">Tải xuống</a>@endcan
            </p>
        @empty<p>Chưa có tài liệu.</p>@endforelse
        @can('employee-document.upload')
        <form method="post" enctype="multipart/form-data" action="{{ route('admin.employees.documents.store', ['employee' => $item['id']]) }}">
            @csrf<input type="hidden" name="company_id" value="{{ $companyId }}">
            <select name="type" required><option value="identity">Định danh</option><option value="contract">Hợp đồng</option><option value="certificate">Chứng chỉ</option><option value="other">Khác</option></select>
            <input name="name" required maxlength="255" placeholder="Tên tài liệu"><input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.docx">
            <button type="submit">Tải tài liệu</button>
        </form>
        @endcan
    </article>
    @endcan

    @can('data-consent.view')
    <article id="consents"><h3>Đồng ý dữ liệu</h3>
        @forelse($item['consents'] ?? [] as $consent)<p>{{ $consent['purpose'] }} · policy {{ $consent['policy_version'] }} · {{ $consent['granted'] ? 'Đồng ý' : 'Đã rút' }}</p>@empty<p>Chưa có quyết định đồng ý dữ liệu.</p>@endforelse
    </article>
    @endcan

    @can('employee-user.view')
    <article id="account"><h3>Tài khoản đăng nhập</h3><p>{{ $item['user_id'] ? 'Đã liên kết' : 'Chưa liên kết' }} · {{ data_get($item,'user.access_enabled',false) ? 'ACTIVE' : 'DISABLED/CHƯA CÓ' }}</p>
@can('employee-user.link')<form method="POST" action="{{ route('admin.employees.account.save',['employee'=>$item['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><label>Email đăng nhập<input type="email" name="account_email" required value="{{ old('account_email',data_get($item,'user.email',$item['work_email']??'')) }}"></label><label>Mật khẩu mới<input type="password" name="account_password" autocomplete="new-password" minlength="12"><small>Để trống khi chỉnh sửa nếu không đổi.</small></label><label>Xác nhận mật khẩu<input type="password" name="account_password_confirmation" autocomplete="new-password"></label><label>Vai trò<select name="role" required>@foreach(app(\App\Support\Iam\RoleHierarchyService::class)->assignableRoles(auth()->user()) as $role)<option value="{{ $role }}" @selected(data_get($item,'user.roles.0.name')===$role)>{{ $role }}</option>@endforeach</select></label><label>Chi nhánh được phép truy cập<select name="branch_ids[]" multiple size="{{ min(6,max(2,$branchOptions->count())) }}">@foreach($branchOptions as $branch)@php($branchAddress=collect([$branch->address_line,$branch->city])->filter()->implode(', '))<option value="{{ $branch->id }}" @selected(in_array((int)$branch->id,old('branch_ids',$accountBranchIds),true))>{{ $branch->name }}{{ $branchAddress !== '' ? ' — '.$branchAddress : '' }}</option>@endforeach</select><small>Có thể chọn nhiều chi nhánh; bỏ chọn chỉ thu hồi access trong công ty hiện tại.</small></label><input type="hidden" name="access_enabled" value="0"><label><input type="checkbox" name="access_enabled" value="1" @checked((bool)data_get($item,'user.access_enabled',true))> Cho phép đăng nhập</label><input type="hidden" name="verified" value="0"><label><input type="checkbox" name="verified" value="1" @checked((bool)data_get($item,'user.email_verified_at',true))> Email đã xác minh</label><button type="submit">Lưu tài khoản</button></form>@endcan
    @if($item['user_id']) @can('employee-user.unlink')<form method="POST" action="{{ route('admin.employees.account.access',['employee'=>$item['id'],'state'=>data_get($item,'user.access_enabled',true)?'disable':'enable']) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><button type="submit">{{ data_get($item,'user.access_enabled',true)?'Vô hiệu hóa đăng nhập':'Mở lại đăng nhập' }}</button></form>@endcan @endif
    </article>
    @endcan
</section>
@endsection

@extends('layouts.admin')
@section('title', $item['full_name'])
@section('content')
<section class="panel employee-profile">
    @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
    <header class="profile-header">
        <div>
            <small>{{ $item['employee_code'] }}</small>
            <h2>{{ $item['full_name'] }}</h2>
            <p>{{ $item['work_email'] ?? 'Chưa có email công việc' }} · {{ $item['phone'] ?? 'Chưa có điện thoại' }}</p>
        </div>
        @can('employee.update')
            <a href="{{ route('admin.employees.edit', ['employee' => $item['id'], 'company_id' => $companyId]) }}">Chỉnh sửa</a>
        @endcan
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

    <article id="overview"><h3>Tổng quan</h3><p>Trạng thái: {{ $item['employment_status'] }}</p><p>Định danh: {{ $item['identity_number'] ?: 'Chưa cập nhật' }}</p></article>
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
    <article id="account"><h3>Tài khoản</h3><p>{{ $item['user_id'] ? 'Đã liên kết tài khoản' : 'Chưa liên kết tài khoản' }}</p></article>
    @endcan
</section>
@endsection

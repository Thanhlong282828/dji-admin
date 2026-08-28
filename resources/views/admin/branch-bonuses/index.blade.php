@extends('layouts.admin')
@section('title', 'Thưởng chi nhánh')
@section('content')
<section class="panel"><span class="eyebrow">BRANCH BONUS · MAKER-CHECKER</span><h1>Thưởng chi nhánh</h1><p>Leader đề xuất; Payroll/Admin duyệt. Bonus approved tạo salary component assignment đúng một tháng và không sửa Payroll lịch sử.</p>@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert error">{{ implode(' ', $errors->all()) }}</div>@endif</section>
@can('branch.bonus.create')
<section class="panel"><h2>Tạo đề xuất</h2><form method="POST" action="{{ route('admin.branch-bonuses.store') }}" class="filter-grid">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><label>Chi nhánh<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label><label>Nhân viên<select name="employee_id" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} — {{ $employee->full_name }}</option>@endforeach</select></label><label>Tham chiếu<input name="bonus_reference" required></label><label>Số tiền VND<input type="number" name="amount_minor" min="1" max="1000000000" required></label><label>Loại<select name="reason_category"><option value="performance">Hiệu suất</option><option value="sales">Kinh doanh</option><option value="project">Dự án</option><option value="recognition">Ghi nhận</option><option value="other">Khác</option></select></label><label>Kỳ hiệu lực<input type="date" name="effective_period" required></label><label class="span-full">Lý do<textarea name="reason" required></textarea></label><button>Tạo bản nháp</button></form></section>
@endcan
<section class="panel">
    <h2>Đề xuất</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tham chiếu</th><th>Chi nhánh</th><th>Nhân viên</th><th>Kỳ</th><th>Số tiền</th><th>Maker</th><th>Trạng thái</th><th>Tiếp theo</th></tr></thead>
            <tbody>
                @forelse($bonuses as $bonus)
                    <tr>
                        <td>{{ $bonus->bonus_reference }}</td><td>{{ $bonus->branch_name }}</td><td>{{ $bonus->employee_code }} · {{ $bonus->full_name }}</td><td>{{ $bonus->effective_period }}</td><td>{{ number_format((int) $bonus->amount_minor, 0, ',', '.') }} ₫</td><td>{{ $bonus->maker_name }}</td><td>{{ $bonus->status }}</td>
                        <td><div class="actions">
                            @if($bonus->status === 'draft')
                                @can('branch.bonus.create')
                                    <form method="POST" action="{{ route('admin.branch-bonuses.transition', [$bonus->id, 'submit']) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $bonus->version }}"><button>Gửi duyệt</button></form>
                                @endcan
                            @endif
                            @if($bonus->status === 'submitted')
                                @can('branch.bonus.approve')
                                    @foreach(['approve' => 'Duyệt', 'reject' => 'Từ chối'] as $action => $label)
                                        <form method="POST" action="{{ route('admin.branch-bonuses.transition', [$bonus->id, $action]) }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $bonus->version }}">@if($action === 'reject')<input name="comment" required placeholder="Lý do">@endif<button>{{ $label }}</button></form>
                                    @endforeach
                                @endcan
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="8">Chưa có đề xuất bonus.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $bonuses->links() }}
</section>
@endsection

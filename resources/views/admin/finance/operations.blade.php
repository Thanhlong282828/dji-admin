@extends('layouts.admin')

@section('title', 'Vận hành tài chính')

@section('content')
    @php($page = $data['page'])
    <div class="admin-page finance-operations-page">
        <header class="admin-page-header">
            <div>
                <p class="admin-eyebrow">FINANCE CONTROL</p>
                <h1>Chi phí · Tài sản · Ngân sách</h1>
                <p>Chứng từ vận hành đi qua phê duyệt, sổ kế toán và đối chiếu Budget vs Actual.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="admin-alert admin-alert--success" role="status">{{ session('status') }}</div>
        @endif

        <nav class="admin-tabs" aria-label="Vận hành tài chính">
            @foreach (['expenses' => 'Chi phí', 'assets' => 'Tài sản & khấu hao', 'budget' => 'Budget vs Actual'] as $key => $label)
                <a href="{{ route('admin.finance.operations', ['page' => $key, 'company_id' => $companyId]) }}" @class(['is-active' => $page === $key]) @if($page === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($page === 'expenses')
            <section class="admin-panel">
                <div class="admin-panel__header"><div><h2>Expense workflow</h2><p>Draft → Submitted → Approved/Rejected → Paid. Maker không tự duyệt.</p></div></div>
                @can('finance.operations.manage')
                    <form method="POST" action="{{ route('admin.finance.operations.mutate') }}" class="admin-form-grid">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="expense.create">
                        <label>Chi nhánh<select name="branch_id" required>@foreach($data['branches'] as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                        <label>Loại chi phí<select name="expense_category_id" required>@foreach($data['categories'] as $category)<option value="{{ $category->id }}">{{ $category->code }} · {{ $category->name }}</option>@endforeach</select></label>
                        <label>Tham chiếu<input name="reference" required></label><label>Số tiền (VND)<input name="amount_minor" type="number" min="1" required></label>
                        <label>Ngày chi phí<input name="expense_date" type="date" required></label><label class="admin-form-grid__wide">Diễn giải<input name="description" required></label>
                        <button class="admin-button" type="submit">Tạo expense draft</button>
                    </form>
                @endcan
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Reference</th><th>Chi nhánh</th><th>Loại</th><th>Ngày</th><th>Số tiền</th><th>Trạng thái</th><th>Hành động</th></tr></thead><tbody>
                    @forelse($data['expenses'] as $expense)<tr><td>{{ $expense->reference }}</td><td>{{ $expense->branch_name }}</td><td>{{ $expense->category_name }}</td><td>{{ $expense->expense_date }}</td><td>{{ number_format((int)$expense->amount_minor, 0, ',', '.') }} ₫</td><td><span class="admin-badge">{{ $expense->status }}</span></td><td>
                        @php($next = ['draft' => 'submitted', 'submitted' => 'approved', 'approved' => 'paid'][$expense->status] ?? null)
                        @if($next && (($next === 'submitted' && auth()->user()->can('finance.operations.manage')) || ($next !== 'submitted' && auth()->user()->can('finance.operations.approve'))))
                            <form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="expense.transition"><input type="hidden" name="id" value="{{ $expense->id }}"><input type="hidden" name="to" value="{{ $next }}"><button class="admin-button admin-button--small" type="submit">{{ $next }}</button></form>
                        @else — @endif
                    </td></tr>@empty<tr><td colspan="7">Chưa có expense trong Company này.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @elseif ($page === 'assets')
            <div class="erp-kpi-grid">
                <x-erp.kpi-card label="Tài sản" :value="number_format($data['assetMetrics']['count'], 0, ',', '.')" meta="Asset register canonical" />
                <x-erp.kpi-card label="Đang hoạt động" :value="number_format($data['assetMetrics']['active'], 0, ',', '.')" meta="Đã activation" />
                <x-erp.kpi-card label="Nguyên giá" :value="number_format($data['assetMetrics']['cost'], 0, ',', '.').' ₫'" meta="Không phải OPEX" />
                <x-erp.kpi-card label="Khấu hao lũy kế" :value="number_format($data['assetMetrics']['accumulated'], 0, ',', '.').' ₫'" meta="Chi phí đã ghi nhận" />
                <x-erp.kpi-card label="Giá trị còn lại" :value="number_format($data['assetMetrics']['book_value'], 0, ',', '.').' ₫'" meta="Nguyên giá − khấu hao" />
            </div>
            <section class="admin-panel">
                <div class="admin-panel__header"><div><h2>Asset register</h2><p>Nguyên giá, nơi sử dụng, khấu hao đường thẳng và giá trị còn lại có audit trail.</p></div></div>
                @can('finance.operations.manage')
                    <form method="POST" action="{{ route('admin.finance.operations.mutate') }}" class="admin-form-grid">
                        @csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset.create">
                        <label>Mã tài sản<input name="asset_code" required></label><label>Tên tài sản<input name="name" required></label>
                        <label>Loại<select name="asset_category_id" required>@foreach($data['categories'] as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
                        <label>Chi nhánh<select name="branch_id" required>@foreach($data['branches'] as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                        <label>Cost Center<select name="cost_center_id"><option value="">Chưa phân bổ</option>@foreach($data['costCenters'] as $center)<option value="{{ $center->id }}">{{ $center->code }} · {{ $center->name }}</option>@endforeach</select></label>
                        <label>Nguyên giá<input name="acquisition_cost_minor" type="number" min="1" required></label><label>Giá trị còn lại mục tiêu<input name="residual_value_minor" type="number" min="0" value="0" required></label>
                        <label>Ngày mua<input name="acquisition_date" type="date" required></label><label>Ngày sử dụng<input name="in_service_date" type="date" required></label><label>Thời gian (tháng)<input name="useful_life_months" type="number" min="1" required></label>
                        <button class="admin-button" type="submit">Tạo asset draft</button>
                    </form>
                @endcan
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã</th><th>Tài sản</th><th>Chi nhánh / Cost Center</th><th>Nguyên giá</th><th>Khấu hao</th><th>NBV</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
                    @forelse($data['assets'] as $asset)<tr><td>{{ $asset->asset_code }}</td><td>{{ $asset->name }}<small>{{ $asset->category_name }}</small></td><td>{{ $asset->branch_name }}<small>{{ $asset->cost_center_code ? $asset->cost_center_code.' · '.$asset->cost_center_name : 'Chưa phân bổ Cost Center' }}</small></td><td>{{ number_format((int)$asset->acquisition_cost_minor, 0, ',', '.') }} ₫<small>{{ $asset->useful_life_months }} tháng · {{ $asset->depreciation_method }}</small></td><td>{{ number_format((int)$asset->accumulated_minor, 0, ',', '.') }} ₫<small>{{ $asset->latest_depreciation_period ? 'Kỳ gần nhất '.$asset->latest_depreciation_period : 'Chưa khấu hao' }}</small></td><td>{{ number_format((int)$asset->book_value_minor, 0, ',', '.') }} ₫</td><td>{{ $asset->status }}</td><td>
                        @can('finance.operations.manage') @if($asset->status === 'draft')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset.activate"><input type="hidden" name="id" value="{{ $asset->id }}"><button class="admin-button admin-button--small">Activate</button></form>@endif @endcan
                        @can('finance.operations.manage') @if($asset->status === 'active')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}" class="admin-inline-form">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset.depreciate"><input type="hidden" name="id" value="{{ $asset->id }}"><input type="month" name="period_month" required aria-label="Kỳ khấu hao"><button class="admin-button admin-button--small">Khấu hao</button></form>@endif @endcan
                    </td></tr>@empty<tr><td colspan="8">Chưa có asset.</td></tr>@endforelse
                </tbody></table></div>
            </section>
            <section class="admin-panel">
                <div class="admin-panel__header"><div><h2>Lịch sử khấu hao</h2><p>Mỗi kỳ được tính từ nguyên giá, giá trị còn lại mục tiêu và thời gian sử dụng; journal là bằng chứng kế toán canonical.</p></div></div>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Kỳ</th><th>Asset</th><th>Cost Center snapshot</th><th>Chi phí kỳ</th><th>Lũy kế</th><th>NBV</th><th>Journal</th></tr></thead><tbody>
                    @forelse($data['depreciation'] as $entry)<tr><td>{{ $entry->period_month }}</td><td>#{{ $entry->asset_id }}</td><td>{{ $entry->cost_center_code_snapshot ? $entry->cost_center_code_snapshot.' · '.$entry->cost_center_name_snapshot : 'Không có dimension lịch sử' }}</td><td>{{ number_format((int)$entry->amount_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$entry->accumulated_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$entry->book_value_minor, 0, ',', '.') }} ₫</td><td>#{{ $entry->accounting_journal_id }}</td></tr>@empty<tr><td colspan="7">Chưa có kỳ khấu hao.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @else
            <section class="admin-panel">
                <div class="admin-panel__header"><div><h2>Budget · Commitment · Actual {{ $data['year'] }}</h2><p>Commitment lấy từ phần PO đã duyệt/gửi nhưng chưa nhận; Actual lấy từ journal đã post. Khi nhận hàng, phần đó rời Commitment và vào Actual, không double-count.</p></div></div>
                <form method="GET" class="admin-filter-bar"><input type="hidden" name="company_id" value="{{ $companyId }}"><label>Năm<input name="year" type="number" min="2000" max="2100" value="{{ $data['year'] }}"></label><button class="admin-button" type="submit">Áp dụng</button></form>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Budget</th><th>Tháng</th><th>Chi nhánh</th><th>Tài khoản</th><th>Approved Budget</th><th>Open Commitment</th><th>Realized Actual</th><th>Consumed</th><th>Remaining</th><th>% Remaining</th></tr></thead><tbody>
                    @forelse($data['rows'] as $row)<tr><td>{{ $row['line']->budget_name }}</td><td>{{ $row['line']->month }}</td><td>{{ $row['line']->branch_name ?? 'Toàn công ty' }}</td><td>{{ $row['line']->account_code }} · {{ $row['line']->account_name }}</td><td>{{ number_format($row['budget'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['commitment'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['actual'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['consumed'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['remaining'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['variance_bp'] / 100, 2, ',', '.') }}%</td></tr>@empty<tr><td colspan="10">Chưa có dòng ngân sách cho năm này.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @endif
    </div>
@endsection

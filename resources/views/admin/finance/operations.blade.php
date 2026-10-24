@extends('layouts.admin')

@section('title', 'Vận hành tài chính')

@section('content')
    @php($page = $data['page'])
    <div class="admin-page finance-operations-page">
        <header class="admin-page-header">
            <div>
                <p class="admin-eyebrow">FINANCE CONTROL</p>
                <div class="erp-title-with-help"><h1>Chi phí · Tài sản · Ngân sách</h1>@if($page === 'assets' && is_array($processHelp))<x-erp.process-help :title="$processHelp['title']" :steps="$processHelp['steps']" :route="route('admin.finance.operations',['page'=>'assets','company_id'=>$companyId])" :description="$processHelp['description']" :owner="$processHelp['owner']" :accounting-impact="$processHelp['accounting_impact']" />@endif</div>
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
                <x-erp.kpi-card label="Tài sản" :value="number_format($data['assetMetrics']['count'], 0, ',', '.')" meta="Nguồn: Asset register canonical" href="#asset-register" action-label="Mở Asset register ↓" />
                <x-erp.kpi-card label="Đang hoạt động" :value="number_format($data['assetMetrics']['active'], 0, ',', '.')" meta="Nguồn: asset đã activation" href="#asset-register" action-label="Xem tài sản ↓" />
                <x-erp.kpi-card label="Nguyên giá" :value="number_format($data['assetMetrics']['cost'], 0, ',', '.').' ₫'" meta="Nguồn: Asset register · không phải OPEX" href="#asset-register" action-label="Mở nguồn nguyên giá ↓" />
                <x-erp.kpi-card label="Khấu hao lũy kế" :value="number_format($data['assetMetrics']['accumulated'], 0, ',', '.').' ₫'" meta="Nguồn: lịch sử khấu hao đã ghi nhận" href="#depreciation-history" action-label="Mở lịch sử ↓" />
                <x-erp.kpi-card label="Giá trị còn lại" :value="number_format($data['assetMetrics']['book_value'], 0, ',', '.').' ₫'" meta="Asset register − khấu hao canonical" href="#asset-register" action-label="Đối chiếu tài sản ↓" />
                @if(($assetLifecycle['schema_status'] ?? null) === 'READY')
                    <x-erp.kpi-card label="Impairment đã post" :value="number_format($assetLifecycle['metrics']['impairment'], 0, ',', '.').' ₫'" meta="Nguồn: AssetLifecycleReadService" href="#asset-impairments" action-label="Mở chứng từ nguồn ↓" />
                    <x-erp.kpi-card label="Disposal chờ xử lý" :value="number_format($assetLifecycle['metrics']['pending_disposal'], 0, ',', '.')" meta="Nguồn: disposal draft/approved" href="#asset-disposals" action-label="Mở disposal workspace ↓" />
                    <x-erp.kpi-card label="Disposal proceeds" :value="number_format($assetLifecycle['metrics']['proceeds'], 0, ',', '.').' ₫'" meta="Nguồn: disposal đã post" href="#asset-disposals" action-label="Mở nguồn proceeds ↓" />
                    <x-erp.kpi-card label="Gain / Loss" :value="number_format($assetLifecycle['metrics']['gain_loss'], 0, ',', '.').' ₫'" meta="Proceeds − book value locked" href="#asset-disposals" action-label="Đối chiếu journal ↓" />
                @endif
            </div>
            @if(($assetLifecycle['schema_status'] ?? null) !== 'READY')
                <section class="admin-panel"><div class="erp-empty-state"><span>?</span><div><strong>SCHEMA_UPGRADE_REQUIRED</strong><p>Asset impairment và governed disposal chưa khả dụng. Không fallback sang luồng thanh lý trực tiếp.</p></div></div></section>
            @else
                <section class="admin-panel" id="asset-impairments">
                    <div class="admin-panel__header"><div><h2>Asset impairment</h2><p>Maker-checker, policy snapshot và journal nguồn; không thay đổi book value trước khi post.</p></div></div>
                    @can('asset-impairment.create')
                        <form method="POST" action="{{ route('admin.finance.operations.mutate') }}" class="admin-form-grid">
                            @csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset-impairment.create">
                            <label>Asset<select name="asset_id" required>@foreach($data['assets']->where('status', 'active') as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} · {{ $asset->name }}</option>@endforeach</select></label>
                            <label>Reference<input name="reference" required></label><label>Review date<input type="date" name="review_date" required></label>
                            <label>Reason<select name="reason" required><option>LOSS_OF_VALUE</option><option>DAMAGE</option><option>MARKET_CHANGE</option><option>OBSOLESCENCE</option></select></label>
                            <label>Impairment amount<input type="number" min="1" name="impairment_amount_minor" required></label><label>Idempotency key<input name="idempotency_key" required></label>
                            <button class="admin-button" type="submit">Create impairment draft</button>
                        </form>
                    @endcan
                    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Reference</th><th>Asset source</th><th>Reason / date</th><th>Book before</th><th>Impairment</th><th>Status</th><th>Journal source</th><th>Action</th></tr></thead><tbody>
                        @forelse($assetLifecycle['impairments'] as $item)<tr><td><strong>{{ $item->reference }}</strong></td><td><a class="erp-source-link" href="#asset-{{ $item->asset_id }}"><strong>{{ $item->asset_code }}</strong><small>View Asset source ↑</small></a></td><td>{{ $item->reason }}<small>{{ $item->review_date }}</small></td><td>{{ number_format((int)$item->previous_book_value_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$item->impairment_amount_minor, 0, ',', '.') }} ₫</td><td>{{ $item->status }}</td><td>@if($item->accounting_journal_id)<a class="erp-source-link" href="{{ route('admin.payroll.accounting.journals.show', ['journal' => $item->accounting_journal_id, 'company_id' => $companyId]) }}">{{ $item->journal_number ?? '#'.$item->accounting_journal_id }}<small>View Journal →</small></a>@else — @endif</td><td>
                            @can('asset-impairment.approve') @if($item->status === 'draft')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset-impairment.approve"><input type="hidden" name="id" value="{{ $item->id }}"><button class="admin-button admin-button--small">Approve</button></form>@endif @endcan
                            @can('asset-impairment.post') @if($item->status === 'approved')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset-impairment.post"><input type="hidden" name="id" value="{{ $item->id }}"><button class="admin-button admin-button--small">Post</button></form>@endif @endcan
                        </td></tr>@empty<tr><td colspan="8"><div class="erp-empty-state"><span>↗</span><div><strong>No operating data available</strong><p>Create from an active Asset; no demo record is inserted.</p></div><a href="#asset-register">Create from upstream workflow ↑</a></div></td></tr>@endforelse
                    </tbody></table></div>
                </section>
                <section class="admin-panel" id="asset-disposals">
                    <div class="admin-panel__header"><div><h2>Asset disposal & retirement</h2><p>Book value được khóa lúc tạo; approval và post tạo đúng một journal rồi mới retire asset.</p></div></div>
                    @can('asset-disposal.create')
                        <form method="POST" action="{{ route('admin.finance.operations.mutate') }}" class="admin-form-grid">
                            @csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset-disposal.create">
                            <label>Asset<select name="asset_id" required>@foreach($data['assets']->where('status', 'active') as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} · {{ $asset->name }}</option>@endforeach</select></label>
                            <label>Reference<input name="reference" required></label><label>Disposal date<input type="date" name="disposal_date" required></label>
                            <label>Reason<input name="reason" required></label><label>Proceeds<input type="number" min="0" name="proceeds_minor" value="0" required></label><label>Idempotency key<input name="idempotency_key" required></label>
                            <button class="admin-button" type="submit">Create disposal request</button>
                        </form>
                    @endcan
                    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Reference</th><th>Asset source</th><th>Date / reason</th><th>Book value</th><th>Proceeds</th><th>Gain / loss</th><th>Status</th><th>Journal source</th><th>Action</th></tr></thead><tbody>
                        @forelse($assetLifecycle['disposals'] as $item)<tr><td><strong>{{ $item->reference }}</strong></td><td><a class="erp-source-link" href="#asset-{{ $item->asset_id }}"><strong>{{ $item->asset_code }}</strong><small>View Asset source ↑</small></a></td><td>{{ $item->disposal_date }}<small>{{ $item->reason }}</small></td><td>{{ number_format((int)$item->book_value_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$item->proceeds_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$item->gain_loss_minor, 0, ',', '.') }} ₫</td><td>{{ $item->status }}</td><td>@if($item->accounting_journal_id)<a class="erp-source-link" href="{{ route('admin.payroll.accounting.journals.show', ['journal' => $item->accounting_journal_id, 'company_id' => $companyId]) }}">{{ $item->journal_number ?? '#'.$item->accounting_journal_id }}<small>View Journal →</small></a>@else — @endif</td><td>
                            @can('asset-disposal.approve') @if($item->status === 'draft')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset-disposal.approve"><input type="hidden" name="id" value="{{ $item->id }}"><button class="admin-button admin-button--small">Approve</button></form>@endif @endcan
                            @can('asset-disposal.post') @if($item->status === 'approved')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset-disposal.post"><input type="hidden" name="id" value="{{ $item->id }}"><button class="admin-button admin-button--small">Post & retire</button></form>@endif @endcan
                        </td></tr>@empty<tr><td colspan="9"><div class="erp-empty-state"><span>↗</span><div><strong>No operating data available</strong><p>Create from an active Asset; no fake disposal is created.</p></div><a href="#asset-register">Create from upstream workflow ↑</a></div></td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endif
            <section class="admin-panel" id="asset-register">
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
                    @forelse($data['assets'] as $asset)<tr id="asset-{{ $asset->id }}"><td><a class="erp-source-link" href="#asset-{{ $asset->id }}"><strong>{{ $asset->asset_code }}</strong><small>Canonical Asset #{{ $asset->id }}</small></a></td><td>{{ $asset->name }}<small>{{ $asset->category_name }}</small></td><td>{{ $asset->branch_name }}<small>{{ $asset->cost_center_code ? $asset->cost_center_code.' · '.$asset->cost_center_name : 'Chưa phân bổ Cost Center' }}</small></td><td>{{ number_format((int)$asset->acquisition_cost_minor, 0, ',', '.') }} ₫<small>{{ $asset->useful_life_months }} tháng · {{ $asset->depreciation_method }}</small></td><td><a class="erp-source-link" href="#depreciation-history">{{ number_format((int)$asset->accumulated_minor, 0, ',', '.') }} ₫<small>Mở depreciation source ↓</small></a></td><td>{{ number_format((int)$asset->book_value_minor, 0, ',', '.') }} ₫</td><td>{{ $asset->status }}</td><td>
                        @can('finance.operations.manage') @if($asset->status === 'draft')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset.activate"><input type="hidden" name="id" value="{{ $asset->id }}"><button class="admin-button admin-button--small">Activate</button></form>@endif @endcan
                        @can('finance.operations.manage') @if($asset->status === 'active')<form method="POST" action="{{ route('admin.finance.operations.mutate') }}" class="admin-inline-form">@csrf<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="action" value="asset.depreciate"><input type="hidden" name="id" value="{{ $asset->id }}"><input type="month" name="period_month" required aria-label="Kỳ khấu hao"><button class="admin-button admin-button--small">Khấu hao</button></form>@endif @endcan
                    </td></tr>@empty<tr><td colspan="8">Chưa có asset.</td></tr>@endforelse
                </tbody></table></div>
            </section>
            <section class="admin-panel" id="depreciation-history">
                <div class="admin-panel__header"><div><h2>Lịch sử khấu hao</h2><p>Mỗi kỳ được tính từ nguyên giá, giá trị còn lại mục tiêu và thời gian sử dụng; journal là bằng chứng kế toán canonical.</p></div></div>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Kỳ</th><th>Asset</th><th>Cost Center snapshot</th><th>Chi phí kỳ</th><th>Lũy kế</th><th>NBV</th><th>Journal</th></tr></thead><tbody>
                    @forelse($data['depreciation'] as $entry)<tr><td>{{ $entry->period_month }}</td><td><a class="erp-source-link" href="#asset-register"><strong>#{{ $entry->asset_id }}</strong><small>Mở Asset register ↑</small></a></td><td>{{ $entry->cost_center_code_snapshot ? $entry->cost_center_code_snapshot.' · '.$entry->cost_center_name_snapshot : 'Không có dimension lịch sử' }}</td><td>{{ number_format((int)$entry->amount_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$entry->accumulated_minor, 0, ',', '.') }} ₫</td><td>{{ number_format((int)$entry->book_value_minor, 0, ',', '.') }} ₫</td><td>@if((int) $entry->accounting_journal_id > 0)<a class="erp-source-link" href="{{ route('admin.payroll.accounting.journals.show', ['journal' => $entry->accounting_journal_id, 'company_id' => $companyId]) }}"><strong>#{{ $entry->accounting_journal_id }}</strong><small>Mở Journal nguồn →</small></a>@else<span class="erp-evidence-state">NO_JOURNAL</span>@endif</td></tr>@empty<tr><td colspan="7">Chưa có kỳ khấu hao.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @else
            <section class="admin-panel">
                <div class="admin-panel__header"><div><h2>Budget · Commitment · Actual {{ $data['year'] }}</h2><p>Commitment lấy từ phần PO đã duyệt/gửi nhưng chưa nhận; Actual lấy từ journal đã post. Khi nhận hàng, phần đó rời Commitment và vào Actual, không double-count.</p></div></div>
                <form method="GET" class="admin-filter-bar"><input type="hidden" name="company_id" value="{{ $companyId }}"><label>Năm<input name="year" type="number" min="2000" max="2100" value="{{ $data['year'] }}"></label><button class="admin-button" type="submit">Áp dụng</button></form>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Budget</th><th>Tháng</th><th>Chi nhánh</th><th>Tài khoản</th><th>Approved Budget</th><th>Open Commitment</th><th>Realized Actual</th><th>Consumed</th><th>Remaining</th><th>% Remaining</th></tr></thead><tbody>
                    @forelse($data['rows'] as $row)<tr><td><a class="erp-source-link" href="{{ route('admin.finance.cost-center-costs', ['company_id' => $companyId, 'year' => $data['year']]) }}"><strong>{{ $row['line']->budget_name }}</strong><small>Mở Cost Center budget →</small></a></td><td>{{ $row['line']->month }}</td><td><a class="erp-source-link" href="{{ route('admin.finance.branch-performance', ['company_id' => $companyId]) }}">{{ $row['line']->branch_name ?? 'Toàn công ty' }}<small>Mở Branch Finance →</small></a></td><td><a class="erp-source-link" href="{{ route('admin.payroll.accounting.accounts.index', ['company_id' => $companyId]) }}">{{ $row['line']->account_code }} · {{ $row['line']->account_name }}<small>Mở Chart of Accounts →</small></a></td><td>{{ number_format($row['budget'], 0, ',', '.') }} ₫</td><td><a class="erp-source-link" href="{{ route('admin.procurement.orders', ['company_id' => $companyId]) }}">{{ number_format($row['commitment'], 0, ',', '.') }} ₫<small>Mở PO commitment →</small></a></td><td><a class="erp-source-link" href="{{ route('admin.payroll.accounting.journals.index', ['company_id' => $companyId]) }}">{{ number_format($row['actual'], 0, ',', '.') }} ₫<small>Mở posted journals →</small></a></td><td>{{ number_format($row['consumed'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['remaining'], 0, ',', '.') }} ₫</td><td>{{ number_format($row['variance_bp'] / 100, 2, ',', '.') }}%</td></tr>@empty<tr><td colspan="10"><div class="erp-empty-state"><span>↗</span><div><strong>Chưa có dòng ngân sách cho năm này</strong><p>Không nội suy ngân sách từ Actual; hãy mở Cost Center workspace để quản lý nguồn.</p></div><a href="{{ route('admin.finance.cost-center-costs', ['company_id' => $companyId]) }}">Mở Cost Center →</a></div></td></tr>@endforelse
                </tbody></table></div>
            </section>
        @endif
    </div>
@endsection

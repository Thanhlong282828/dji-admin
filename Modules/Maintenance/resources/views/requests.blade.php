@extends('layouts.admin')
@section('title','Yêu cầu bảo trì')
@section('content')
<x-erp.page-header eyebrow="MAINTENANCE REQUEST" title="Yêu cầu bảo trì" description="Yêu cầu và maker-checker tách biệt với Work Order thực thi." />
@can('maintenance.request.create')<section class="panel"><h2>Tạo yêu cầu</h2><form method="post" action="{{ route('admin.maintenance.requests.store') }}" class="filter-grid">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><label>Mã<input name="code" required></label><label>Tài sản<select name="asset_id" required>@foreach($data['assets'] as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} · {{ $a->name }}</option>@endforeach</select></label><label>Tiêu đề<input name="title" required></label><label>Ưu tiên<select name="priority"><option>NORMAL</option><option>LOW</option><option>HIGH</option><option>CRITICAL</option></select></label><label class="span-2">Mô tả<textarea name="description" required></textarea></label><button class="button">Tạo yêu cầu</button></form></section>@endcan
<section class="table-shell"><div class="table-heading"><h2>Danh sách</h2></div><div class="table-scroll"><table><thead><tr><th>Mã</th><th>Tài sản</th><th>Yêu cầu</th><th>Ưu tiên</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($data['requests'] as $r)
<tr><td>{{ $r->code }}</td><td>{{ $r->asset_code }} · {{ $r->asset_name }}</td><td>{{ $r->title }}</td><td>{{ $r->priority }}</td><td><x-erp.status-badge :status="$r->status"/></td><td>
@if($r->status === 'DRAFT')
@can('maintenance.request.submit')<form method="post" action="{{ route('admin.maintenance.requests.submit',$r->id) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><button class="button secondary">Gửi</button></form>@endcan
@elseif($r->status === 'SUBMITTED')
@can('maintenance.request.approve')<form method="post" action="{{ route('admin.maintenance.requests.decision',$r->id) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><button name="decision" value="approve" class="button">Duyệt</button><button name="decision" value="reject" class="button secondary">Từ chối</button></form>@endcan
@elseif($r->status === 'APPROVED')
@can('maintenance.work-order.create')<form method="post" action="{{ route('admin.maintenance.requests.convert',$r->id) }}">@csrf<input type="hidden" name="company_id" value="{{ $company }}"><button class="button">Tạo Work Order</button></form>@endcan
@endif
</td></tr>
@empty<tr><td colspan="6" class="empty-state">Chưa có yêu cầu bảo trì.</td></tr>@endforelse
</tbody></table></div>{{ $data['requests']->withQueryString()->links() }}</section>
@endsection

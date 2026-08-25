@extends('layouts.admin')
@section('title','Chi nhánh')
@section('header_action') @can('branch.create')<a class="button" href="{{ route('admin.branches.create',['company_id'=>$companyId]) }}">+ Thêm chi nhánh</a>@endcan @endsection
@section('content')
<section class="page-hero panel"><span class="eyebrow">Multi-Branch</span><h2>Chi nhánh vận hành</h2><p>Branch là địa điểm/phạm vi truy cập của công ty, độc lập với cây Organization Unit.</p></section>
<section class="table-shell"><div class="table-scroll" tabindex="0"><table class="data-table"><thead><tr><th>Mã</th><th>Chi nhánh</th><th>Công ty</th><th>Địa chỉ</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr></thead><tbody>@forelse($branches as $branch)<tr><td>{{ $branch->code }}</td><td><strong>{{ $branch->name }}</strong></td><td>{{ $branch->company_name }}</td><td>{{ collect([$branch->address_line,$branch->city])->filter()->join(', ') ?: '—' }}</td><td><span class="status-pill is-{{ $branch->status }}">{{ $branch->status }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($branch->created_at)->format('d/m/Y') }}</td><td>@can('branch.update')<a href="{{ route('admin.branches.edit',['branch'=>$branch->id,'company_id'=>$companyId]) }}">Sửa</a>@endcan</td></tr>@empty<tr><td colspan="7">Chưa có chi nhánh trong phạm vi này.</td></tr>@endforelse</tbody></table></div>{{ $branches->links() }}</section>
@endsection

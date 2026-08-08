@extends('layouts.admin')
@section('title','Candidate Offers')
@section('content')
<section class="panel" data-recruitment-page data-company-id="{{ $companyId }}" data-realtime-entity="offer" data-refresh-url="{{ request()->fullUrl() }}"><div class="page-heading"><div><span class="eyebrow">RECRUITMENT</span><h1>Candidate Offers</h1></div><span data-realtime-state>Đã đồng bộ</span><button type="button" data-realtime-refresh>Làm mới</button>
@can('candidate-offer.create')<a class="button" href="{{ route('admin.candidate-offers.create',['company_id'=>$companyId]) }}">Tạo offer</a>@endcan</div>
@can('candidate-offer.view')<a class="button" href="{{ route('admin.candidate-offers.export',['company_id'=>$companyId]) }}">Export CSV</a>@endcan
<table><thead><tr><th>Số offer</th><th>Ứng viên</th><th>Vị trí</th><th>Ngày bắt đầu</th><th>Trạng thái</th></tr></thead>
<tbody>@forelse($items as $item)<tr><td><a href="{{ route('admin.candidate-offers.show',['candidate_offer'=>$item->id,'company_id'=>$companyId]) }}">{{ $item->offer_number }}</a></td>
<td>{{ $item->application->candidate->full_name }}</td><td>{{ $item->jobPosition->title }}</td><td>{{ $item->start_date->format('d/m/Y') }}</td><td>{{ $item->status->value }}</td></tr>
@empty<tr><td colspan="5" class="empty-state">Chưa có candidate offer.</td></tr>@endforelse</tbody></table>{{ $items->withQueryString()->links() }}</section>
@endsection

@extends('layouts.admin')
@section('title','Chi tiết Candidate Offer')
@section('content')
<section class="panel" data-recruitment-page data-company-id="{{ $companyId }}" data-offer-id="{{ $offer->id }}" data-realtime-version="{{ $offer->version }}" data-realtime-entity="offer" data-realtime-entity-id="{{ $offer->id }}" data-refresh-url="{{ request()->fullUrl() }}"><span class="eyebrow">{{ $offer->offer_number }}</span><h1>{{ $offer->application->candidate->full_name }} — {{ $offer->jobPosition->title }}</h1><span data-realtime-state>Đã đồng bộ</span><button type="button" data-realtime-refresh>Làm mới</button>
<dl><dt>Trạng thái</dt><dd>{{ $offer->status->value }}</dd><dt>Ngày bắt đầu</dt><dd>{{ $offer->start_date->format('d/m/Y') }}</dd>
<dt>Lương cơ bản</dt><dd>@can('candidate-offer.view-salary'){{ $offer->base_salary }} {{ $offer->currency }}@else••••••@endcan</dd>
<dt>Reservation</dt><dd>{{ $offer->reservation_status }}</dd><dt>Version</dt><dd>{{ $offer->version }}</dd></dl>
@can('candidate-offer.hire')
@if($offer->status->value === 'accepted' && $offer->reservation_status === 'reserved')<a class="button" href="{{ route('admin.candidate-offers.hire-form',['candidate_offer'=>$offer->id,'company_id'=>$companyId]) }}">Hire Candidate</a>@endif
@if($offer->hired_employee_id)<a class="button" href="{{ route('admin.employees.show',['employee'=>$offer->hired_employee_id,'company_id'=>$companyId]) }}">Mở hồ sơ nhân viên</a>@can('onboarding.view')<a class="button" href="{{ route('admin.onboarding-cases.index',['company_id'=>$companyId,'employee_id'=>$offer->hired_employee_id]) }}">Mở Onboarding Case</a>@endcan @endif
@endcan
@can('candidate-offer.update')<form method="POST" enctype="multipart/form-data" action="{{ route('admin.candidate-offers.document.store',['candidate_offer'=>$offer->id]) }}">@csrf
<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="file" name="document" accept="application/pdf" required><button>Upload PDF riêng tư</button></form>@endcan
@can('candidate-offer.download')@if($offer->document_mime_type)<a class="button" href="{{ route('admin.candidate-offers.download',['candidate_offer'=>$offer->id,'company_id'=>$companyId]) }}">Tải tài liệu Offer</a>@endif @endcan
@can('candidate-offer.update')@if($offer->document_mime_type)<form method="POST" action="{{ route('admin.candidate-offers.document.destroy',['candidate_offer'=>$offer->id]) }}">@csrf @method('DELETE')<input type="hidden" name="company_id" value="{{ $companyId }}"><button>Gỡ tài liệu</button></form>@endif @endcan
@foreach(['pending_approval'=>'Submit','approved'=>'Approve','draft'=>'Return','sent'=>'Send','accepted'=>'Accept','declined'=>'Decline','withdrawn'=>'Withdraw'] as $target=>$label)
@php($permission = ['pending_approval'=>'candidate-offer.submit','approved'=>'candidate-offer.approve','draft'=>'candidate-offer.update','sent'=>'candidate-offer.send','accepted'=>'candidate-offer.accept','declined'=>'candidate-offer.decline','withdrawn'=>'candidate-offer.withdraw'][$target])
@can($permission)<form method="POST" action="{{ route('admin.candidate-offers.transition',['candidate_offer'=>$offer->id,'target'=>$target]) }}">@csrf @method('PATCH')
<input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="version" value="{{ $offer->version }}">
@if(in_array($target,['declined','withdrawn','draft'],true))<input name="reason" placeholder="Lý do">@endif<button>{{ $label }}</button></form>@endcan
@endforeach</section>
@endsection

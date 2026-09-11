@extends('layouts.admin')
@section('title','Chi tiết tài liệu')
@section('content')
@php($document=$data['document'])
<x-erp.page-header :title="$document->title" :kicker="'DMS · '.$document->owner_module" :description="$document->subject_type.' #'.$document->subject_id.' · current v'.$document->version_number">
    <x-slot:actions><x-erp.status-badge :status="$document->status" /></x-slot:actions>
</x-erp.page-header>
@can('document.upload')@if($document->status==='ACTIVE')<form method="POST" enctype="multipart/form-data" action="{{ route('admin.documents.versions.store',['document'=>$document->id,'company_id'=>$company]) }}" class="admin-filter-bar">@csrf<label>Phiên bản mới<input type="file" name="document" required></label><button class="button">Tải lên</button></form>@endif@endcan
<section class="admin-panel"><h2>Lịch sử phiên bản bất biến</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Version</th><th>Original name</th><th>MIME</th><th>Size</th><th>SHA-256</th><th>Uploaded</th><th>Action</th></tr></thead><tbody>@foreach($data['versions'] as $v)<tr><td>v{{ $v->version_number }}</td><td>{{ $v->original_name }}</td><td>{{ $v->mime_type }}</td><td>{{ number_format($v->size_bytes) }}</td><td><code>{{ $v->sha256 }}</code></td><td>{{ $v->created_at }}</td><td>@can('document.download')<a href="{{ route('admin.documents.download',['version'=>$v->id,'company_id'=>$company]) }}">Tải xuống</a>@endcan</td></tr>@endforeach</tbody></table></div></section>
@can('document.archive')@if($document->status==='ACTIVE')<form method="POST" action="{{ route('admin.documents.archive',['document'=>$document->id,'company_id'=>$company]) }}">@csrf<button class="button ghost">Lưu trữ</button></form>@endif@endcan
@endsection

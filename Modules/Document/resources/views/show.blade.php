@extends('layouts.admin')
@section('title', 'Chi tiết tài liệu')
@section('content')
@php($document = $data['document'])
<div class="erp-dashboard">
    <x-erp.page-header :title="$document->title" :kicker="'DMS · '.$document->owner_module" :description="$document->subject_type.' #'.$document->subject_id.' · current v'.$document->version_number"><x-slot:actions><x-erp.status-badge :status="$document->status" /></x-slot:actions></x-erp.page-header>
    <div class="erp-workflow-summary">
        <x-erp.detail-panel title="Nguồn nghiệp vụ" :description="$document->owner_module"><strong class="erp-detail-value">{{ $document->subject_type }} #{{ $document->subject_id }}</strong></x-erp.detail-panel>
        <x-erp.detail-panel title="Classification" description="Quyền download vẫn được kiểm tra ở route/controller"><strong class="erp-detail-value">{{ $document->classification }}</strong></x-erp.detail-panel>
        <x-erp.detail-panel title="Phiên bản hiện hành" description="Immutable version sequence"><strong class="erp-detail-value">v{{ $document->version_number }}</strong></x-erp.detail-panel>
    </div>
    @can('document.upload')
        @if($document->status === 'ACTIVE')
            <x-erp.form-shell title="Tải phiên bản mới" description="Phiên bản cũ được giữ bất biến; file tiếp tục qua validation và malware scan hiện hữu.">
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.documents.versions.store', ['document' => $document->id, 'company_id' => $company]) }}" class="erp-filter-form">@csrf<label>Chọn tệp<input type="file" name="document" required></label><x-erp.button type="submit">Tải lên phiên bản</x-erp.button></form>
            </x-erp.form-shell>
        @endif
    @endcan
    <x-erp.data-table title="Lịch sử phiên bản bất biến" description="Checksum và metadata do Document module ghi nhận; không phải approval evidence.">
        <table><thead><tr><th scope="col">Version</th><th scope="col">Original name</th><th scope="col">MIME</th><th scope="col">Size</th><th scope="col">SHA-256</th><th scope="col">Uploaded</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($data['versions'] as $version)
            <tr><td>v{{ $version->version_number }}</td><td>{{ $version->original_name }}</td><td>{{ $version->mime_type }}</td><td class="numeric">{{ number_format($version->size_bytes) }}</td><td><code class="erp-code-wrap">{{ $version->sha256 }}</code></td><td>{{ $version->created_at }}</td><td>@can('document.download')<x-erp.button variant="outline" :href="route('admin.documents.download', ['version' => $version->id, 'company_id' => $company])">Tải xuống</x-erp.button>@endcan</td></tr>
        @empty<tr><td colspan="7"><x-erp.empty-state title="Chưa có phiên bản" description="Không có file version hợp lệ cho tài liệu này." /></td></tr>@endforelse
        </tbody></table>
    </x-erp.data-table>
    @can('document.archive')
        @if($document->status === 'ACTIVE')
            <form method="POST" action="{{ route('admin.documents.archive', ['document' => $document->id, 'company_id' => $company]) }}" onsubmit="return confirm('Lưu trữ tài liệu này?')">
                @csrf
                <x-erp.button type="submit" variant="danger">Lưu trữ tài liệu</x-erp.button>
            </form>
        @endif
    @endcan
</div>
@endsection

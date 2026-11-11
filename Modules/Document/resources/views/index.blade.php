@extends('layouts.admin')
@section('title', 'Kho tài liệu')
@section('content')
<div class="erp-dashboard">
    <x-erp.page-header title="Kho tài liệu doanh nghiệp" kicker="DMS · PRIVATE EVIDENCE" description="Metadata đa-domain, phiên bản bất biến, SHA-256 và tải xuống có kiểm soát. Module hiện chưa có action phê duyệt tài liệu riêng; không hiển thị nút duyệt giả." />
    @can('document.create')
        <x-erp.form-shell title="Tạo tài liệu" description="Bản ghi chỉ được tạo qua Document command hiện có và giữ nguyên company scope.">
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.documents.store') }}" class="form-grid">
                @csrf
                <input type="hidden" name="company_id" value="{{ $company }}">
                <label>Tiêu đề<input name="title" value="{{ request('title') }}" required></label>
                <label>Loại<select name="document_type"><option>EMPLOYEE_CONTRACT</option><option>PURCHASE_ORDER</option><option>GOODS_RECEIPT</option><option @selected(request('document_type') === 'SERVICE_ACCEPTANCE_EVIDENCE')>SERVICE_ACCEPTANCE_EVIDENCE</option><option>VENDOR_INVOICE</option><option>EXPENSE_RECEIPT</option><option>ASSET_EVIDENCE</option><option>CUSTOMER_INVOICE</option><option>ACCOUNTING_SUPPORT</option></select></label>
                <label>Owner module<select name="owner_module"><option>Employee</option><option @selected(request('owner_module') === 'Procurement')>Procurement</option><option>Finance</option><option>Commerce</option></select></label>
                <label>Subject type<input name="subject_type" value="{{ request('subject_type') }}" required></label>
                <label>Subject ID<input type="number" min="1" name="subject_id" value="{{ request('subject_id') }}" required></label>
                <label>Classification<select name="classification"><option>INTERNAL</option><option>CONFIDENTIAL</option><option>RESTRICTED</option><option>PUBLIC_INTERNAL</option></select></label>
                <label>Tệp<input type="file" name="document" required></label>
                <x-erp.form-actions><x-erp.button type="submit">Tạo tài liệu</x-erp.button></x-erp.form-actions>
            </form>
        </x-erp.form-shell>
    @endcan
    <x-erp.data-table title="Hàng đợi rà soát tài liệu" description="Trạng thái, owner và linked record lấy từ document read model; không suy diễn approval state.">
        <table><thead><tr><th scope="col">Document</th><th scope="col">Type</th><th scope="col">Owner</th><th scope="col">Linked record</th><th scope="col">Version</th><th scope="col">Status</th><th scope="col">Classification</th><th scope="col">Updated</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($data['documents'] as $row)
            <tr><td><strong>{{ $row->title }}</strong></td><td>{{ $row->document_type }}</td><td>{{ $row->owner_module }}</td><td>{{ $row->subject_type }} #{{ $row->subject_id }}</td><td>v{{ $row->version_number }}</td><td><x-erp.status-badge :status="$row->status" /></td><td>{{ $row->classification }}</td><td>{{ $row->updated_at }}</td><td><x-erp.button variant="outline" :href="route('admin.documents.show', ['document' => $row->id, 'company_id' => $company])">Xem chi tiết</x-erp.button></td></tr>
        @empty
            <tr><td colspan="9"><x-erp.empty-state title="Chưa có tài liệu doanh nghiệp" description="Tài liệu xuất hiện sau khi người có quyền tạo bản ghi từ nguồn nghiệp vụ và tải phiên bản đầu tiên." /></td></tr>
        @endforelse
        </tbody></table>
        <x-slot:pagination>{{ $data['documents']->links() }}</x-slot:pagination>
    </x-erp.data-table>
</div>
@endsection

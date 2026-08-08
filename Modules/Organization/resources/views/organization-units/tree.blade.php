@extends('layouts.admin')
@section('title', 'Cây tổ chức')
@section('content')
<section
    class="panel"
    data-organization-tree
    data-company-id="{{ $companyId }}"
    data-endpoint="{{ route('admin.organization-units.tree-data', ['company_id' => $companyId]) }}"
>
    <div class="tree-toolbar">
        <input data-tree-search placeholder="Tìm theo mã hoặc tên đơn vị">
        <select data-tree-type>
            <option value="">Tất cả loại đơn vị</option>
            @foreach(['company', 'branch', 'division', 'department', 'section', 'team'] as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        <button type="button" data-tree-expand>Expand all</button>
        <button type="button" data-tree-collapse>Collapse all</button>
        <span data-tree-state role="status">Đã tải dữ liệu</span>
    </div>
    <div data-tree-content>
        @if($tree)
            <ul class="organization-tree">
                @foreach($tree as $node)
                    @include('organization::organization-units.partials.node', ['node' => $node])
                @endforeach
            </ul>
        @else
            <div class="empty-state">Chưa có dữ liệu cây tổ chức.</div>
        @endif
    </div>
</section>
@endsection

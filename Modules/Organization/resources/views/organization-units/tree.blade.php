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
<style>.organization-tree,.organization-tree ul{list-style:none;margin:0;padding-left:1.15rem}.organization-tree{padding-left:0}.organization-tree li{position:relative;margin:.65rem 0}.organization-tree li::before{content:"";position:absolute;left:-.75rem;top:-.7rem;bottom:-.7rem;border-left:1px solid rgba(255,228,119,.16)}.tree-node{display:grid;grid-template-columns:auto minmax(180px,1.3fr) minmax(260px,1fr) auto;gap:.85rem;align-items:center;padding:.85rem;border:1px solid rgba(255,228,119,.18);border-radius:14px;background:linear-gradient(145deg,rgba(255,228,119,.055),rgba(13,16,21,.88))}.tree-node>[data-tree-toggle]{width:2rem;height:2rem;border:1px solid rgba(255,228,119,.35);border-radius:9px;background:#11151b;color:#ffe477}.tree-node__identity{display:grid;grid-template-columns:auto 1fr;gap:.18rem .55rem}.tree-node__identity small{grid-column:1/-1;color:#c69d31;text-transform:uppercase;letter-spacing:.12em}.tree-node__identity strong{color:#fff4c2}.tree-node__identity span{color:#d9dee7}.tree-node__metrics{display:grid;grid-template-columns:repeat(4,minmax(52px,1fr));gap:.45rem}.tree-node__metrics>span{display:flex;flex-direction:column;padding:.45rem;border-radius:9px;background:rgba(255,255,255,.035)}.tree-node__metrics small{color:#8f98a8}.tree-node__actions{display:flex;flex-direction:column;align-items:flex-end;gap:.35rem}.tree-node__actions>span{font-size:.72rem;color:#c69d31}.tree-node__actions a{color:#ffe477;text-decoration:none;font-weight:800}@media(max-width:900px){.tree-node{grid-template-columns:auto 1fr}.tree-node__metrics,.tree-node__actions{grid-column:2}.tree-node__actions{align-items:flex-start}}@media(max-width:560px){.organization-tree,.organization-tree ul{padding-left:.45rem}.tree-node__metrics{grid-template-columns:repeat(2,1fr)}}</style>
@endsection

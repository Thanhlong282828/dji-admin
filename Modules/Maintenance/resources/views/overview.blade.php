@extends('layouts.admin')
@section('title','Tổng quan bảo trì')
@section('content')
<x-erp.page-header eyebrow="CMMS CONTROL" title="Tổng quan bảo trì" description="Số liệu trực tiếp từ Asset, Maintenance Request, Work Order, Preventive Plan và Inventory evidence." />
<section class="erp-kpi-grid">@foreach(['requests'=>'Yêu cầu','submitted'=>'Chờ duyệt','work_orders'=>'Work Order','in_progress'=>'Đang thực hiện','overdue'=>'Quá hạn','due_plans'=>'Preventive đến hạn','completed'=>'Hoàn thành','downtime_minutes'=>'Downtime (phút)'] as $key=>$label)<x-erp.kpi-card :label="$label" :value="number_format($data['summary'][$key])" />@endforeach<x-erp.kpi-card label="Parts cost" :value="number_format($data['summary']['parts_cost_minor'],0,',','.').' ₫'" /></section>
<section class="panel"><h2>Ranh giới dữ liệu</h2><p>Asset: canonical Finance Asset. Spare parts: canonical Inventory Movement. Procurement, Expense và Accounting: PARTIAL khi chưa có Work Order reference. MTTR/MTBF: NOT_ENOUGH_EVIDENCE.</p></section>
@endsection

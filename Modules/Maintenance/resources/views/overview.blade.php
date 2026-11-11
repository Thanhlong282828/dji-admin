@extends('layouts.admin')
@section('title','Tổng quan bảo trì')
@section('content')
<x-erp.dashboard eyebrow="CMMS CONTROL" title="Tổng quan bảo trì" description="Số liệu trực tiếp từ Asset, Maintenance Request, Work Order, Preventive Plan và Inventory evidence.">
    <x-erp.dashboard-section title="Tín hiệu vận hành" description="Mỗi chỉ số liên kết về workspace sở hữu dữ liệu canonical.">
        <section class="erp-kpi-grid" aria-label="Chỉ số bảo trì">
            @foreach(['requests'=>'Yêu cầu','submitted'=>'Chờ duyệt','work_orders'=>'Work Order','in_progress'=>'Đang thực hiện','overdue'=>'Quá hạn','due_plans'=>'Preventive đến hạn','completed'=>'Hoàn thành','downtime_minutes'=>'Downtime (phút)'] as $key=>$label)
                @php($target=in_array($key,['requests','submitted'],true)?'admin.maintenance.requests':($key==='due_plans'?'admin.maintenance.plans':'admin.maintenance.work-orders'))
                <x-erp.kpi-card :label="$label" :value="number_format($data['summary'][$key])" meta="MAINTENANCE READ MODEL" :href="route($target,['company_id'=>$company,'status'=>in_array($key,['submitted','in_progress','completed'],true)?strtoupper($key):null])" />
            @endforeach
            <x-erp.kpi-card label="Parts cost" :value="number_format($data['summary']['parts_cost_minor'],0,',','.').' ₫'" meta="WORK ORDER PARTS EVIDENCE" :href="route('admin.maintenance.work-orders',['company_id'=>$company])" />
        </section>
    </x-erp.dashboard-section>
    <x-erp.dashboard-section title="Ranh giới dữ liệu" description="Phân biệt evidence đầy đủ và trạng thái chưa đủ bằng chứng.">
        <x-erp.widget title="Domain evidence" description="Không nội suy MTTR/MTBF khi nguồn chưa đủ.">
            <p>Asset: canonical Finance Asset. Spare parts: canonical Inventory Movement. Procurement, Expense và Accounting: PARTIAL khi chưa có Work Order reference. MTTR/MTBF: NOT_ENOUGH_EVIDENCE.</p>
        </x-erp.widget>
    </x-erp.dashboard-section>
</x-erp.dashboard>
@endsection

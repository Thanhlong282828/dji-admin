@extends('layouts.admin')
@section('title','Tổng quan dự án')
@section('header_action')<a class="button" href="{{ route('admin.projects.index',['company_id'=>$company]) }}">Mở danh sách dự án</a>@endsection
@section('content')
<x-erp.page-header eyebrow="PROJECT PORTFOLIO" title="Tổng quan dự án" description="Portfolio control dựa trên project, task, milestone và timesheet canonical." />
<section class="erp-kpi-grid">
@foreach(['projects'=>'Tổng dự án','active'=>'Đang hoạt động','completed'=>'Hoàn thành','on_hold'=>'Tạm dừng','cancelled'=>'Đã hủy','at_risk'=>'Có rủi ro','overdue_projects'=>'Trễ hạn'] as $key=>$label)<x-erp.kpi-card :label="$label" :value="number_format($data['summary'][$key])" />@endforeach
<x-erp.kpi-card label="Task quá hạn" :value="number_format($data['summary']['tasks']['overdue'])"/><x-erp.kpi-card label="Milestone mở" :value="number_format($data['summary']['milestones']['open'])"/><x-erp.kpi-card label="Timesheet chờ duyệt" :value="number_format($data['summary']['timesheets']['submitted'])"/><x-erp.kpi-card label="Thành viên hoạt động" :value="number_format($data['summary']['active_members'])"/>
</section>
<section class="panel"><h2>Project Finance evidence</h2><div class="analytics-kv"><div><span>Planned budget</span><strong>{{ number_format($data['summary']['budget_minor'],0,',','.') }} ₫</strong></div><div><span>Commitment / Actual / Remaining</span><strong>NOT_ENOUGH_EVIDENCE</strong></div><div><span>Expense / Procurement / Asset</span><strong>PARTIAL</strong></div><div><span>Revenue/Billing</span><strong>NOT_IMPLEMENTED</strong></div></div><p class="muted">Không suy diễn chi phí hoặc doanh thu dự án từ tổng Company khi owner documents chưa có project reference.</p></section>
@endsection

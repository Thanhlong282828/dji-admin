@extends('layouts.admin')
@section('title','Tổng quan POS')
@section('content')
<x-erp.dashboard eyebrow="Check-in → Checkout → Receipt → Revenue → Branch Finance" title="Tổng quan bán hàng tại quầy" description="Số liệu lấy trực tiếp từ phiếu thu đã thanh toán; không cộng lượt khách đang phục vụ.">
    <x-erp.dashboard-section title="Sales / Orders" description="Tóm tắt giao dịch canonical theo phạm vi Company hiện tại." columns="4">
        <x-erp.kpi-card label="Doanh thu hôm nay" :value="number_format((int) ($todaySummary->revenue ?? 0),0,',','.')" unit="₫" icon="₫" :meta="(int) ($todaySummary->transactions ?? 0).' phiếu · '.((int) ($todaySummary->revenue ?? 0) === 0 ? 'VALID_ZERO' : 'CANONICAL')" :href="route('admin.branch-commerce.receipts',['company_id'=>$companyId])" action-label="Mở phiếu thu" />
        <x-erp.kpi-card label="Doanh thu tháng" :value="number_format((int) ($monthSummary->revenue ?? 0),0,',','.')" unit="₫" icon="◫" :meta="(int) ($monthSummary->transactions ?? 0).' phiếu · '.(int) ($monthSummary->customers ?? 0).' khách'" />
        <x-erp.kpi-card label="Sản phẩm / Dịch vụ" :value="(int) $productsSold.' / '.(int) $servicesSold" icon="◇" meta="Số lượng đã checkout trong tháng" />
        <x-erp.kpi-card label="Sắp hết hàng" :value="(int) $lowStock" icon="!" meta="Available từ 1–5" />
    </x-erp.dashboard-section>
    <x-erp.dashboard-section title="Hoạt động gần đây" description="Receipt và doanh thu được ghi trong cùng transaction.">
        <x-erp.widget title="Phiếu thu gần đây">
            <x-slot:actions><x-erp.button variant="secondary" :href="route('admin.branch-commerce.receipts',['company_id'=>$companyId])">Xem tất cả</x-erp.button></x-slot:actions>
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Phiếu</th><th>Chi nhánh</th><th>Khách</th><th>Tổng</th><th>Thanh toán</th></tr></thead><tbody>
            @forelse($recent as $item)
                <tr><td><a href="{{ route('admin.branch-commerce.receipts.show',['receipt'=>$item->id,'company_id'=>$companyId]) }}">{{ $item->reference }}</a></td><td>{{ $item->branch_name }}</td><td>{{ $item->customer_name }}</td><td class="money">{{ number_format((int) $item->total_minor,0,',','.') }} ₫</td><td>{{ $item->paid_at }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="erp-empty-state"><strong>Chưa có phiếu thu đã thanh toán</strong><span>Hoàn tất checkout hợp lệ để tạo receipt và doanh thu canonical.</span></div></td></tr>
            @endforelse
            </tbody></table></div>
        </x-erp.widget>
    </x-erp.dashboard-section>
</x-erp.dashboard>
@endsection

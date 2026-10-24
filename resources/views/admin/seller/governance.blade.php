@extends('layouts.admin')
@section('title','Seller Governance')
@section('content')
<section class="page-hero panel"><span class="eyebrow">Company Seller Governance</span><h2>Seller Governance</h2><p>{{ $companyName }} · IAM quyết định quyền; Seller Profile thuộc đúng Company và state machine quyết định trạng thái kế tiếp.</p></section>
<section class="table-shell"><div class="table-scroll"><table class="seller-governance-table"><thead><tr><th>Company</th><th>Seller</th><th>Owner account</th><th>Store</th><th>Status</th><th>Verified</th><th>Action</th></tr></thead><tbody>
@forelse($sellers as $seller)
@php($allowed=$transitions[$seller->status] ?? [])
<tr>
    <td data-label="Company">{{ $seller->company_name }}</td><td data-label="Seller">{{ $seller->display_name }}</td><td data-label="Owner account">{{ $seller->owner_name }}<small>{{ $seller->owner_email }}</small></td><td data-label="Store">{{ $seller->stores_count }}</td><td data-label="Status"><span class="status-pill is-{{ $seller->status }}">{{ $seller->status }}</span></td><td data-label="Verified">{{ $seller->verified_at ?: '—' }}</td>
    <td data-label="Action">
        @can('commerce.seller.governance.manage')
            @if($allowed !== [])
                <form method="post" action="{{ route('admin.commerce.sellers.transition',$seller->id) }}">
                    @csrf @method('patch')
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <label class="sr-only" for="seller-status-{{ $seller->id }}">Trạng thái kế tiếp của {{ $seller->display_name }}</label>
                    <select id="seller-status-{{ $seller->id }}" name="status" required>
                        @foreach($allowed as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="seller-reason-{{ $seller->id }}">Lý do governance</label>
                    <input id="seller-reason-{{ $seller->id }}" name="reason" maxlength="1000" placeholder="Lý do (bắt buộc khi đình chỉ/cấm)">
                    <button class="button">Áp dụng</button>
                </form>
            @else
                <span class="status-pill">Không còn transition</span>
            @endif
        @endcan
    </td>
</tr>
@empty<tr><td colspan="7">Company chưa có Seller account.</td></tr>@endforelse
</tbody></table></div>{{ $sellers->links() }}</section>
@endsection

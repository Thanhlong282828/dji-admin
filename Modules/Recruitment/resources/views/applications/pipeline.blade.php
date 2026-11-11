@extends('layouts.admin')
@section('title', 'Pipeline ứng tuyển')

@section('content')
<section class="erp-board-workspace" data-recruitment-page data-company-id="{{ $companyId }}" data-realtime-entity="all" data-refresh-url="{{ request()->fullUrl() }}">
    <header class="erp-board-workspace__header">
        <div>
            <p class="admin-eyebrow">RECRUITMENT · CANONICAL APPLICATION STAGES</p>
            <h2>Pipeline ứng tuyển</h2>
            <p>Ứng viên được nhóm theo trạng thái thực tế của application; màn hình không tự tạo hoặc chuyển stage.</p>
        </div>
        <div class="erp-board-workspace__sync" role="status">
            <span data-realtime-state>Đã đồng bộ</span>
            <button class="erp-button erp-button--secondary" type="button" data-realtime-refresh>Làm mới</button>
        </div>
    </header>

    <div class="erp-board" tabindex="0" aria-label="Pipeline ứng tuyển theo trạng thái">
        @foreach(['received','screening','test','interview','reference','offer','hired','rejected'] as $stage)
            @php($stageItems = $pipeline[$stage] ?? [])
            <section class="erp-board-column" aria-labelledby="recruitment-stage-{{ $stage }}">
                <header class="erp-board-column__header">
                    <h3 id="recruitment-stage-{{ $stage }}">{{ str_replace('_', ' ', strtoupper($stage)) }}</h3>
                    <span class="erp-status-badge" aria-label="{{ count($stageItems) }} hồ sơ">{{ count($stageItems) }}</span>
                </header>
                <div class="erp-board-column__cards" role="list" aria-label="Hồ sơ ở trạng thái {{ str_replace('_', ' ', $stage) }}">
                    @forelse($stageItems as $item)
                        <article class="erp-board-card" role="listitem" tabindex="0">
                            <strong>{{ data_get($item, 'candidate.full_name') ?: 'Ứng viên chưa có tên hiển thị' }}</strong>
                            <p>{{ data_get($item, 'requisition.job_position.title') ?: 'Chưa xác định vị trí' }}</p>
                            <footer><span>Điểm đánh giá</span><b>{{ $item['score'] ?? '—' }}</b></footer>
                        </article>
                    @empty
                        <div class="erp-board-column__empty" role="status"><strong>Chưa có hồ sơ</strong><p>Không có application ở stage này.</p></div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</section>
@endsection

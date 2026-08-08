<li data-tree-item data-type="{{ $node['type'] }}">
    <div class="tree-node">
        @if($node['children'])
            <button type="button" data-tree-toggle aria-expanded="true" aria-label="Thu gọn {{ $node['name'] }}">−</button>
        @endif
        <span><b>{{ $node['code'] }}</b> {{ $node['name'] }} <small>{{ $node['type'] }}</small></span>
        <span>
            Limit {{ $node['headcount_limit'] }} · People {{ $node['people_count'] }} ·
            FTE {{ number_format($node['allocated_fte'], 2) }} ·
            Vacancy {{ number_format($node['vacancy'], 2) }} ·
            {{ $node['cost_center_code'] ?: 'No cost center' }}
        </span>
        <a href="{{ $node['detail_url'] }}?company_id={{ $companyId }}">Chi tiết</a>
    </div>
    @if($node['children'])
        <ul data-tree-children>
            @foreach($node['children'] as $child)
                @include('organization::organization-units.partials.node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>

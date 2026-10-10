<li data-tree-item data-type="{{ $node['type'] }}">
    <article class="tree-node">
        @if($node['children'])
            <button type="button" data-tree-toggle aria-expanded="true" aria-label="Thu gọn {{ $node['name'] }}">−</button>
        @endif
        <div class="tree-node__identity"><small>{{ $node['type'] }}</small><strong>{{ $node['code'] }}</strong><span>{{ $node['name'] }}</span></div>
        <div class="tree-node__metrics"><span><small>Limit</small><b>{{ $node['headcount_limit'] }}</b></span><span><small>People</small><b>{{ $node['people_count'] }}</b></span><span><small>FTE</small><b>{{ number_format($node['allocated_fte'], 2) }}</b></span><span><small>Vacancy</small><b>{{ number_format($node['vacancy'], 2) }}</b></span></div>
        <div class="tree-node__actions"><span>{{ $node['cost_center_code'] ?: 'No cost center' }}</span><a href="{{ $node['detail_url'] }}?company_id={{ $companyId }}">Chi tiết →</a></div>
    </article>
    @if($node['children'])
        <ul data-tree-children>
            @foreach($node['children'] as $child)
                @include('organization::organization-units.partials.node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>

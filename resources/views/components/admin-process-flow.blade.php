@props(['nodes' => []])

@if(count($nodes) > 1)
    <nav class="erp-process-flow" aria-label="Quy trình nghiệp vụ" data-process-flow>
        <div class="erp-process-flow__heading">
            <span>Quy trình nghiệp vụ</span>
            <small>Luồng đến bước hiện tại</small>
        </div>
        <ol class="erp-process-flow__track">
            @foreach($nodes as $node)
                @php
                    $position = $node['position'] ?? ($node['current'] ? 'current' : 'future');
                    $connectorActive = !$loop->first && in_array($position, ['upstream', 'current'], true);
                    $url = $node['route'] !== null && Route::has($node['route'])
                        ? route($node['route'], $node['parameters'])
                        : null;
                @endphp
                <li class="erp-process-flow__item is-{{ $position }}" data-flow-node="{{ $node['key'] }}" data-flow-position="{{ $position }}">
                    @if(!$loop->first)
                        <span class="erp-process-flow__connector {{ $connectorActive ? 'is-active' : '' }}" aria-hidden="true"><i></i></span>
                    @endif
                    @if($url !== null && !$node['current'])
                        <a class="erp-process-flow__node" href="{{ $url }}" @if(($node['help'] ?? '') !== '') title="{{ $node['help'] }}" @endif>
                            <b aria-hidden="true">{{ $node['icon'] ?? '◆' }}</b><span>{{ $node['label'] }}</span>
                        </a>
                    @else
                        <span class="erp-process-flow__node" @if($node['current']) aria-current="step" @endif>
                            <b aria-hidden="true">{{ $node['icon'] ?? '◆' }}</b><span>{{ $node['label'] }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif

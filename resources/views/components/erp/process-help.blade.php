@props([
    'title',
    'steps' => [],
    'route' => null,
    'description' => '',
    'owner' => '',
    'accountingImpact' => '',
])

<details class="erp-process-help" data-process-help @if(request('process_help') === 'open') open @endif>
    <summary
        class="erp-process-help__trigger"
        aria-label="Mở giải thích quy trình {{ $title }}"
        title="Xem nguồn dữ liệu, module sở hữu, CQRS và tác động kế toán"
    >?</summary>
    <section class="erp-process-help__card" role="dialog" aria-modal="true" aria-label="Giải thích quy trình {{ $title }}">
        <header class="erp-process-help__header">
            <div>
                <span>ERP PROCESS EXPLAINER</span>
                <h2>{{ $title }}</h2>
                @if($description !== '')<p>{{ $description }}</p>@endif
            </div>
            @if(is_string($route) && $route !== '')
                <a class="erp-process-help__overview-link" href="{{ $route }}">Mở workspace nguồn →</a>
            @endif
            <button class="erp-process-help__close" type="button" aria-label="Đóng giải thích quy trình {{ $title }}" data-process-help-close>×</button>
        </header>
        <dl class="erp-process-help__summary">
            @if($owner !== '')<div><dt>Owner</dt><dd>{{ $owner }}</dd></div>@endif
            @if($accountingImpact !== '')<div><dt>Accounting impact</dt><dd>{{ $accountingImpact }}</dd></div>@endif
        </dl>
        <ol class="erp-process-help__steps">
            @foreach($steps as $step)
                <li class="erp-process-help__step {{ ($step['has_operating_data'] ?? false) ? 'has-data' : 'is-empty' }}">
                    <div class="erp-process-help__step-index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                    <article>
                        <header>
                            <h3>{{ $step['title'] }}</h3>
                            @if($step['has_operating_data'] ?? false)
                                <span class="erp-process-help__evidence">{{ number_format((int) ($step['evidence_count'] ?? 0)) }} evidence</span>
                            @else
                                <span class="erp-process-help__evidence is-empty">No operating data available</span>
                            @endif
                        </header>
                        <dl>
                            <div><dt>Owner</dt><dd>{{ $step['owner'] }}</dd></div>
                            <div><dt>Source</dt><dd><code>{{ $step['source'] }}</code></dd></div>
                            <div><dt>Meaning</dt><dd>{{ $step['meaning'] }}</dd></div>
                            <div><dt>Accounting</dt><dd>{{ $step['accounting'] }}</dd></div>
                            <div><dt>Next</dt><dd>{{ $step['next'] }}</dd></div>
                        </dl>
                        @if(is_string($step['url'] ?? null) && $step['url'] !== '')
                            <a class="erp-process-help__source" href="{{ $step['url'] }}">
                                {{ ($step['has_operating_data'] ?? false) ? $step['link_label'] : $step['empty_action'] }} →
                            </a>
                        @else
                            <small class="erp-process-help__restricted">Source link unavailable for current permission or schema.</small>
                        @endif
                    </article>
                </li>
            @endforeach
        </ol>
    </section>
</details>

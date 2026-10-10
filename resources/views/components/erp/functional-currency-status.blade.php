@props(['company'])
@php($configured = filled($company['functional_currency'] ?? null))
<section class="fx-currency-status {{ $configured ? 'is-configured' : 'is-unconfigured' }}" aria-labelledby="fx-functional-currency-title">
    <div class="fx-currency-status__mark" aria-hidden="true">{{ $configured ? '✓' : '!' }}</div>
    <div class="fx-currency-status__content">
        <span class="fx-eyebrow">COMPANY GOVERNANCE SOURCE</span>
        <h3 id="fx-functional-currency-title">Functional currency</h3>
        @if($configured)
            <p><strong>{{ $company['functional_currency'] }}</strong> · Configured and immutable for {{ $company['name'] }}.</p>
        @else
            <p><strong>UNCONFIGURED</strong> · FX conversion and revaluation remain unavailable until Company configures its canonical accounting currency.</p>
        @endif
    </div>
    @if($company['source_url'] ?? null)<a class="fx-source-action" href="{{ $company['source_url'] }}">View Company source <span aria-hidden="true">↗</span></a>@endif
</section>

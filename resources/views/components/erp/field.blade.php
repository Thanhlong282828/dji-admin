@props(['label', 'help' => null, 'required' => false])
<label {{ $attributes->class(['erp-field']) }}>
    <span class="erp-field__label">{{ $label }}@if($required)<span class="erp-field__required" aria-hidden="true"> *</span>@endif</span>
    {{ $slot }}
    @if($help)<small class="erp-field__help">{{ $help }}</small>@endif
</label>

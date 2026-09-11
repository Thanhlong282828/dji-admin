@props(['status'])
@php($normalized = Illuminate\Support\Str::of((string) $status)->lower()->replace('_', '-'))
<span {{ $attributes->class(['erp-status', 'erp-status--'.$normalized]) }}>{{ Illuminate\Support\Str::headline((string) $status) }}</span>

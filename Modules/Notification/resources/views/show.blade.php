@extends('layouts.admin')
@section('title','Notification Detail')
@section('content')
@include('notification::partials.styles')
@php($item=$vm->items[0])
<div class="nc-shell"><section class="nc-hero"><div><span class="nc-kicker">{{ $item['category'] }} · immutable message</span><h1>{{ $item['title'] }}</h1><div class="nc-meta"><span>{{ $item['created_at'] }}</span><span>{{ $item['entity_type'] }} #{{ $item['entity_id'] }}</span></div></div><a class="nc-button nc-button--ghost" href="{{ route('admin.notifications.index',['company_id'=>$companyId]) }}">Back to inbox</a></section><section class="nc-panel"><p>{{ $item['body'] }}</p>@if($item['navigation_reference'])<p><a class="nc-button" href="{{ $item['navigation_reference'] }}">Open authorized source</a></p>@endif</section></div>
@endsection

@props(['errors'])
@if($errors->any())
    <div {{ $attributes->class(['erp-validation-summary']) }} role="alert" aria-live="polite">
        <strong>Vui lòng kiểm tra lại thông tin.</strong>
        <ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

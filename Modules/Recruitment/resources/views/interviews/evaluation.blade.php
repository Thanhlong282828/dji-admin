@extends('layouts.admin')
@section('title','Interview Evaluation')
@section('content')
<section class="panel"><span class="eyebrow">SCORECARD</span><h1>{{ $interview->title }}</h1>
@if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admin.interviews.evaluation.draft',['interview'=>$interview->id]) }}" class="form-grid">@csrf @method('PUT')
<input type="hidden" name="company_id" value="{{ $companyId }}">
@foreach([['TECHNICAL','Năng lực chuyên môn',40],['PROBLEM_SOLVING','Giải quyết vấn đề',30],['COMMUNICATION','Giao tiếp',30]] as $index=>$criterion)
<fieldset><legend>{{ $criterion[1] }}</legend><input type="hidden" name="items[{{ $index }}][criterion_code]" value="{{ $criterion[0] }}">
<input type="hidden" name="items[{{ $index }}][criterion_label]" value="{{ $criterion[1] }}"><input type="hidden" name="items[{{ $index }}][maximum_score]" value="5">
<input type="hidden" name="items[{{ $index }}][weight]" value="{{ $criterion[2] }}"><label>Điểm 0–5<input type="number" name="items[{{ $index }}][score]" min="0" max="5" required></label>
<label>Nhận xét<input name="items[{{ $index }}][comment]"></label></fieldset>@endforeach
<label>Khuyến nghị<select name="recommendation">@foreach(['strong_hire','hire','neutral','no_hire','strong_no_hire'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
<label>Điểm mạnh<textarea name="strengths"></textarea></label><label>Quan ngại<textarea name="concerns"></textarea></label><label>Ghi chú riêng<textarea name="private_notes"></textarea></label>
<button formaction="{{ route('admin.interviews.evaluation.draft',['interview'=>$interview->id]) }}">Lưu draft</button>
<button formaction="{{ route('admin.interviews.evaluation.submit',['interview'=>$interview->id]) }}">Submit evaluation</button></form></section>
@endsection

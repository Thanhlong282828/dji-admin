@extends('layouts.admin')
@section('title', 'Phân công nhân sự')
@section('content')
<section class="panel">
    <header><h2>Phân công nhân sự</h2><a href="{{ route('admin.employees.show', ['employee' => $employeeId, 'company_id' => $companyId]) }}">Hồ sơ nhân viên</a></header>
    @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
    @forelse($assignments as $assignment)
        <article class="assignment-card">
            <strong>{{ $assignment->jobPosition?->title }}</strong>
            <span>{{ $assignment->organizationUnit?->name }}</span>
            <span>{{ $assignment->assignment_type->value }} · {{ $assignment->allocation_percent }}%</span>
            <span>{{ $assignment->effective_from?->format('d/m/Y') }} — {{ $assignment->effective_to?->format('d/m/Y') ?? 'Hiện tại' }}</span>
        </article>
    @empty
        <p>Chưa có phân công.</p>
    @endforelse
</section>
@endsection

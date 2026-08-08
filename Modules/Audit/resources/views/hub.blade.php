@extends('layouts.admin')

@section('title', $vm->title)

@section('content')
    <style>
        .ea{--gold:#d4af37;color:#eef1f6;max-width:1500px;margin:auto}.ea *{box-sizing:border-box}.ea-hero,.ea-panel,.ea-card{background:linear-gradient(145deg,#151920,#080a0e);border:1px solid #30343d;border-radius:20px}.ea-hero{padding:28px;display:flex;justify-content:space-between;align-items:end;gap:20px}.ea-kicker{color:var(--gold);letter-spacing:.16em;text-transform:uppercase;font-size:12px}.ea h1{font-size:clamp(28px,4vw,52px);margin:8px 0}.ea-nav{display:flex;gap:8px;flex-wrap:wrap}.ea-nav a{color:#f5dfa0;border:1px solid #6d5925;background:#17140b;padding:10px 14px;border-radius:999px;text-decoration:none}.ea-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin:18px 0}.ea-card{padding:20px}.ea-card strong{display:block;color:var(--gold);font-size:30px}.ea-panel{padding:22px;margin-top:18px;overflow:hidden}.ea-table{overflow-x:auto}.ea table{width:100%;min-width:800px;border-collapse:collapse}.ea th,.ea td{padding:12px;border-bottom:1px solid #292d34;white-space:nowrap;text-align:left}.ea th{color:#d9bf67}@media(max-width:700px){.ea-hero{align-items:start;flex-direction:column}.ea-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ea-panel{padding:14px}.ea-nav a{flex:1;text-align:center}}
    </style>

    <main class="ea">
        <header class="ea-hero">
            <div>
                <span class="ea-kicker">Immutable enterprise evidence</span>
                <h1>{{ $vm->title }}</h1>
                <p>Actor, action, resource, correlation and integrity in one company-scoped timeline.</p>
            </div>
            <nav class="ea-nav">
                @foreach (['dashboard' => 'Dashboard', 'timeline' => 'Timeline', 'actors' => 'Actors', 'resources' => 'Resources', 'exports' => 'Exports', 'retention' => 'Retention'] as $route => $label)
                    <a href="{{ route('admin.audit.'.$route, ['company_id' => $companyId]) }}">{{ $label }}</a>
                @endforeach
            </nav>
        </header>

        @if ($page === 'dashboard')
            <section class="ea-grid">
                @foreach ($vm->metrics as $key => $value)
                    <article class="ea-card">
                        <span>{{ str($key)->headline() }}</span>
                        <strong>{{ $value }}</strong>
                    </article>
                @endforeach
            </section>
        @endif

        <section class="ea-panel">
            @if ($page === 'exports')
                <form method="POST" action="{{ route('admin.audit.exports.store') }}" class="ea-nav">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <button name="format" value="csv" type="submit">Generate CSV</button>
                    <button name="format" value="pdf" type="submit">Generate PDF</button>
                </form>
            @elseif ($page === 'retention')
                <form method="POST" action="{{ route('admin.audit.retention.store') }}" class="ea-nav">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <input name="name" value="Enterprise default" aria-label="Policy name">
                    <input name="retention_days" type="number" min="30" value="2555" aria-label="Retention days">
                    <button type="submit">Apply without deletion</button>
                </form>
                <form method="POST" action="{{ route('admin.audit.retention.archive') }}" class="ea-nav" style="margin-top:12px">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <input name="range_start" type="date" value="{{ today()->subYear()->toDateString() }}" aria-label="Archive range start">
                    <input name="range_end" type="date" value="{{ today()->toDateString() }}" aria-label="Archive range end">
                    <button type="submit">Create immutable archive</button>
                </form>
            @endif
            <div class="ea-table">
                @if ($vm->items === [])
                    <p>No audit evidence in this company scope.</p>
                @else
                    <table>
                        <thead><tr>
                            @foreach (array_keys($vm->items[0]) as $key)
                                <th>{{ str($key)->headline() }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($vm->items as $item)
                                <tr>
                                    @foreach ($item as $key => $value)
                                        <td>
                                            @if ($page === 'exports' && $key === 'id')
                                                <a href="{{ route('admin.audit.exports.download', ['export' => $value, 'company_id' => $companyId]) }}">Download #{{ $value }}</a>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </main>
@endsection

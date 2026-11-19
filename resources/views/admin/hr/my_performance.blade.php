@extends('layouts.admin')

@section('title', 'My Targets')

@section('content')
{{-- Read-only: the same rows HR edits, seen by the person they are about. --}}
<style>
    .mp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; }
    .mp-item { border: 1px solid var(--border); border-left-width: 4px; border-radius: 12px; padding: 0.9rem 1rem; background: var(--bg-card); }
    .mp-item b { display: block; color: var(--text-main); font-size: 0.92rem; }
    .mp-item small { color: var(--text-muted); font-size: 0.78rem; }
    .mp-bar { height: 6px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; margin-top: 9px; }
    .mp-bar i { display: block; height: 100%; }
    .mp-jd h4 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin: 1.3rem 0 0.6rem; }
    .mp-jd ul { margin: 0 0 0 1.1rem; padding: 0; color: var(--text-main); font-size: 0.88rem; line-height: 1.9; }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.3rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-bullseye" style="color: var(--primary);"></i> My Targets — {{ $month->format('F Y') }}
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">What you are measured on this month, and how it is going.</p>
            </div>
            <form method="GET" action="{{ route('admin.hr.my_performance') }}">
                <select name="month" onchange="this.form.submit()"
                        style="height: 38px; padding: 0 0.7rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                    @foreach($months as $m)
                        <option value="{{ $m->format('Y-m') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>{{ $m->format('F Y') }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if($kpis->count())
            <div class="mp-grid">
                @foreach($kpis as $kpi)
                    @php $c = $kpi->progress() >= 80 ? 'var(--success)' : ($kpi->progress() >= 50 ? 'var(--warning)' : 'var(--danger)'); @endphp
                    <div class="mp-item" style="border-left-color: {{ $c }};">
                        <b>{{ $kpi->metric }}</b>
                        <small>{{ $kpi->valueText($kpi->achieved_value) }} of {{ $kpi->valueText($kpi->target_value) }} &middot; {{ $kpi->progress() }}%</small>
                        <div class="mp-bar"><i style="width: {{ $kpi->progress() }}%; background: {{ $c }};"></i></div>
                        @if($kpi->notes)<small style="display: block; margin-top: 7px; font-style: italic;">{{ $kpi->notes }}</small>@endif
                    </div>
                @endforeach
            </div>
        @else
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">No targets set for this month.</p>
        @endif
    </div>

    <div class="card mp-jd">
        <div class="card-header"><div class="card-title"><i class="fas fa-clipboard-list" style="color: var(--primary);"></i> My job description</div></div>
        @if($jd && (filled($jd->responsibilities) || filled($jd->purpose)))
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">
                {{ $jd->title ?: 'Role' }}
                @if($jd->reports_to)<span style="font-weight: 500; color: var(--text-muted); font-size: 0.85rem;"> &middot; reports to {{ $jd->reports_to }}</span>@endif
            </div>
            @if($jd->purpose)
                <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.6rem 0 0;">{{ $jd->purpose }}</p>
            @endif
            @if(count($jd->lines('responsibilities')))
                <h4>Responsibilities</h4>
                <ul>@foreach($jd->lines('responsibilities') as $line)<li>{{ $line }}</li>@endforeach</ul>
            @endif
            @if(count($jd->lines('requirements')))
                <h4>Requirements</h4>
                <ul>@foreach($jd->lines('requirements') as $line)<li>{{ $line }}</li>@endforeach</ul>
            @endif
            @if($jd->tools)
                <h4>Tools</h4>
                <p style="color: var(--text-main); font-size: 0.88rem; margin: 0;">{{ $jd->tools }}</p>
            @endif
        @else
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">HR has not written your job description yet.</p>
        @endif
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Team Work Record')

@section('content')
{{--
    Who did what: transfers given and taken, deals won and the money behind
    them, tickets closed, and the days they were in to do it.

    Everything is counted from the rows the CRM already keeps, so this is a
    different view of the same truth rather than a second set of figures.
--}}
@php
    $maxDay = max(1, collect($report['days'])->max(fn ($d) => max($d['transfers'], $d['taken'], $d['deals'])));
    $leaders = $report['rows']->take(8);
    $maxLeader = max(1, $leaders->max('transfers_given') ?: 1);
@endphp

<style>
    .wr-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
    .wr-filters > div { flex: 1 1 150px; min-width: 0; }
    .wr-filters > div:last-child { flex: 0 0 auto; }
    .wr-filters label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
                      text-transform: uppercase; color: var(--text-soft); margin-bottom: 5px; }
    .wr-filters input, .wr-filters select { width: 100%; height: 38px; padding: 0.5rem 0.65rem; border-radius: 9px;
        border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.85rem; }

    .wr-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 1rem; }
    .wr-card { border: 1px solid var(--border); border-left-width: 4px; border-radius: 14px; padding: 0.95rem 1.1rem; background: var(--bg-card); }
    .wr-card span { display: block; font-size: 0.76rem; color: var(--text-muted); margin-bottom: 5px; }
    .wr-card strong { font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
    .wr-card small { display: block; font-size: 0.71rem; color: var(--text-muted); margin-top: 5px; line-height: 1.45; }

    /* Day by day, drawn as bars rather than a chart library: three numbers a day
       over a month is a picture, not a dataset. */
    .wr-chart { display: flex; align-items: flex-end; gap: 4px; height: 190px; overflow-x: auto; padding-bottom: 6px; }
    .wr-day { flex: 1 0 26px; display: flex; flex-direction: column; align-items: center; gap: 5px; min-width: 26px; }
    .wr-stack { display: flex; align-items: flex-end; gap: 2px; height: 150px; width: 100%; justify-content: center; }
    .wr-bar { width: 7px; border-radius: 3px 3px 0 0; background: var(--border); transition: height 0.3s ease; }
    .wr-bar.given { background: #6366f1; }
    .wr-bar.taken { background: #0ea5e9; }
    .wr-bar.deals { background: #22c55e; }
    .wr-day small { font-size: 0.64rem; color: var(--text-muted); }
    .wr-day.is-off small { color: var(--danger); }

    .wr-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 0.78rem; color: var(--text-muted); margin-top: 0.8rem; }
    .wr-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .wr-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

    .wr-leader { display: grid; grid-template-columns: 170px 1fr 70px; gap: 10px; align-items: center; padding: 0.4rem 0; font-size: 0.85rem; }
    .wr-leader b { color: var(--text-main); font-weight: 600; }
    .wr-leader-bar { height: 10px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; }
    .wr-leader-bar i { display: block; height: 100%; background: #6366f1; }
    .wr-leader-value { text-align: right; font-variant-numeric: tabular-nums; color: var(--text-main); font-weight: 700; }

    .wr-num { text-align: right; font-variant-numeric: tabular-nums; }
    .wr-name { font-weight: 600; color: var(--text-main); }
    .wr-sub { font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-chart-column" style="color: var(--primary);"></i> Team Work Record
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} &middot;
                    what each person gave, took, won and worked.
                </p>
            </div>
            <a href="{{ route('admin.hr.work_record.export', request()->all()) }}" class="btn btn-ghost"
               style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-weight: 600; text-decoration: none;">
                <i class="fas fa-file-csv" style="color: #10b981;"></i> Export CSV
            </a>
        </div>

        <form method="GET" action="{{ route('admin.hr.work_record') }}" class="wr-filters">
            <div>
                <label for="wrFrom">From</label>
                <input type="date" id="wrFrom" name="from" value="{{ $from->toDateString() }}">
            </div>
            <div>
                <label for="wrTo">To</label>
                <input type="date" id="wrTo" name="to" value="{{ $to->toDateString() }}">
            </div>
            <div>
                <label for="wrDept">Department</label>
                <select id="wrDept" name="department_id">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" {{ (string) ($filters['department_id'] ?? '') === (string) $department->id ? 'selected' : '' }}>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="wrUser">Employee</label>
                <select id="wrUser" name="user_id">
                    <option value="">Everyone</option>
                    @foreach($report['people'] as $person)
                        <option value="{{ $person->id }}" {{ (string) ($filters['user_id'] ?? '') === (string) $person->id ? 'selected' : '' }}>
                            {{ $person->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" >
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="{{ route('admin.hr.work_record') }}" class="btn btn-ghost"
                   >Clear</a>
            </div>
        </form>
    </div>

    {{-- ---- the totals ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                {{ $employee ? $employee->name . ' — the period in total' : 'The team, in total' }}
            </div>
        </div>
        <div class="wr-cards">
            @foreach($metrics as $key => $meta)
                <div class="wr-card" style="border-left-color: {{ $meta['colour'] }};">
                    <span>{{ $meta['label'] }}</span>
                    <strong>{{ \App\Services\WorkRecordService::format($key, $report['totals'][$key] ?? 0) }}</strong>
                    <small>{{ $meta['help'] }}</small>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---- day by day ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-calendar-days" style="color: var(--primary);"></i> Day by day</div>
        </div>

        <div class="wr-chart">
            @foreach($report['days'] as $day)
                <div class="wr-day {{ in_array($day['weekday'], ['Sat', 'Sun'], true) ? 'is-off' : '' }}"
                     title="{{ $day['label'] }} — {{ $day['transfers'] }} given, {{ $day['taken'] }} taken, {{ $day['deals'] }} won">
                    <div class="wr-stack">
                        <div class="wr-bar given" style="height: {{ max(2, round($day['transfers'] / $maxDay * 150)) }}px;"></div>
                        <div class="wr-bar taken" style="height: {{ max(2, round($day['taken'] / $maxDay * 150)) }}px;"></div>
                        <div class="wr-bar deals" style="height: {{ max(2, round($day['deals'] / $maxDay * 150)) }}px;"></div>
                    </div>
                    <small>{{ $day['short'] }}</small>
                </div>
            @endforeach
        </div>

        <div class="wr-legend">
            <span><i style="background: #6366f1;"></i> Transfers given</span>
            <span><i style="background: #0ea5e9;"></i> Transfers taken</span>
            <span><i style="background: #22c55e;"></i> Deals won</span>
        </div>
    </div>

    {{-- ---- who gave the most ---- --}}
    @if(!$employee && $leaders->count())
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-ranking-star" style="color: var(--primary);"></i> Most transfers given</div>
        </div>
        @foreach($leaders as $row)
            <div class="wr-leader">
                <b>{{ $row['user']->name }}</b>
                <div class="wr-leader-bar">
                    <i style="width: {{ round($row['transfers_given'] / $maxLeader * 100) }}%;"></i>
                </div>
                <div class="wr-leader-value">{{ $row['transfers_given'] }}</div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ---- the table ---- --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Every person</div>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        @foreach($metrics as $meta)
                            <th class="wr-num" title="{{ $meta['help'] }}">{{ $meta['label'] }}</th>
                        @endforeach
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td>
                            <div class="wr-name">{{ $row['user']->name }}</div>
                            <div class="wr-sub">{{ str_replace('_', ' ', $row['role']) }}</div>
                        </td>
                        <td>{{ $row['department'] }}</td>
                        @foreach($metrics as $key => $meta)
                            <td class="wr-num">{{ \App\Services\WorkRecordService::format($key, $row[$key]) }}</td>
                        @endforeach
                        <td>
                            <a href="{{ route('admin.hr.work_record', array_merge(request()->all(), ['user_id' => $row['user']->id])) }}"
                               class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.28rem 0.7rem; font-size: 0.75rem; border-radius: 8px; text-decoration: none; white-space: nowrap;">
                                Just them
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($metrics) + 3 }}" style="text-align: center; padding: 2.5rem; color: var(--text-muted); ">
                            Nobody matches those filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

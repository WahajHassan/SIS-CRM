@extends('layouts.admin')

@section('title', 'Sales History')

@section('content')
<div class="page-head">
    <div class="page-sub" style="margin: 0;">Review past monthly performances.</div>
    <div class="page-actions">
        <a href="{{ route('admin.history') }}" class="btn btn-primary">Monthly team report</a>
        <a href="{{ route('admin.history.agent') }}" class="btn btn-ghost">Yearly agent report</a>
    </div>
</div>

<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" action="{{ route('admin.history') }}" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); white-space: nowrap;">Select Month:</label>
            <input type="month" name="month" value="{{ $selected_month }}" onchange="this.form.submit()" style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 8px; outline: none; font-family: inherit; background: var(--bg-card);">
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by agent name..." style="padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); outline: none; font-size: 0.85rem; width: 220px; font-family: inherit;">
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-search"></i></button>
            @if(request('search'))
                <a href="{{ route('admin.history', ['month' => $selected_month]) }}" class="btn btn-ghost btn-sm" style="color: var(--danger);"><i class="fas fa-times"></i></a>
            @endif
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-history" style="color: var(--primary);"></i> Performance for {{ \Carbon\Carbon::parse($selected_month)->format('F Y') }}</div>
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Agent</th>
                    <th>Goal</th>
                    <th>Transfers</th>
                    <th>Sales</th>
                    <th>Total Revenue</th>
                    <th>Conversion Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($performers as $p)
                @php
                    $progress = $p->hist_goal > 0 ? min(100, ($p->revenue / $p->hist_goal) * 100) : 0;
                @endphp
                <tr>
                    <td style="font-weight: 800; color: var(--text-muted);">#{{ $loop->iteration }}</td>
                    <td style="font-weight: 600;">
                        {{ $p->display_name }}
                        @if($p->needs_red_mark)
                            <span title="Negative Report: Under 2 conversions out of 10 transfers" style="color: #ef4444; margin-left: 5px; font-size: 1.1rem;"><i class="fas fa-exclamation-circle"></i></span>
                        @endif
                        @if($p->is_closer) <span style="font-size: 0.7rem; background: rgba(59,130,246,0.1); color: #3b82f6; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">Closer</span> @endif
                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;">{{ $p->role }}</div>
                    </td>
                    <td style="font-weight: 600;">${{ number_format($p->hist_goal) }}</td>
                    <td style="font-weight: 600;">{{ $p->hist_transferred }}</td>
                    <td style="font-weight: 600; color: var(--green);">{{ $p->hist_sales }}</td>
                    <td style="font-weight: 800; color: var(--primary);">${{ number_format($p->revenue) }}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="flex: 1; height: 6px; background: rgba(0,0,0,0.05); border-radius: 10px; overflow: hidden;">
                                <div style="height: 100%; width: {{ $p->conversion_rate }}%; background: var(--brand-gradient);"></div>
                            </div>
                            <span style="font-size: 0.8rem; font-weight: 700;">{{ $p->conversion_rate }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
                @if($performers->isEmpty())
                <tr>
                    <td colspan="7"><div class="empty-state"><p>No performance records found.</p></div></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection

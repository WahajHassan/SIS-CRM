@extends('layouts.admin')

@section('title', 'Yearly Agent Report')

@section('content')
<div class="page-head">
    <div class="page-sub" style="margin: 0;">Review full year performance data for a single agent.</div>
    <div class="page-actions">
        <a href="{{ route('admin.history') }}" class="btn btn-ghost">Monthly team report</a>
        <a href="{{ route('admin.history.agent') }}" class="btn btn-primary">Yearly agent report</a>
    </div>
</div>

<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" action="{{ route('admin.history.agent') }}" style="display: flex; align-items: center; gap: 15px;">
        <div>
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px;">Select Agent:</label>
            <select name="agent_id" onchange="this.form.submit()" style="padding: 0.6rem; border: 1px solid var(--border); border-radius: 8px; outline: none; min-width: 200px; background: var(--bg-card);">
                @foreach($performers as $p)
                    <option value="{{ $p->id }}" {{ $selected_agent && $selected_agent->id == $p->id ? 'selected' : '' }}>
                        {{ $p->display_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px;">Select Year:</label>
            <select name="year" onchange="this.form.submit()" style="padding: 0.6rem; border: 1px solid var(--border); border-radius: 8px; outline: none; min-width: 120px; background: var(--bg-card);">
                @for($y = now()->year; $y >= 2024; $y--)
                    <option value="{{ $y }}" {{ $selected_year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
    </form>
</div>

@if($selected_agent)
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-chart-line" style="color: var(--primary);"></i>
            Performance for {{ $selected_agent->display_name }} in {{ $selected_year }}
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Goal</th>
                    <th>Transfers</th>
                    <th>Sales</th>
                    <th>Total Revenue</th>
                    <th>Conversion Rate</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total_goal = 0;
                    $total_revenue = 0;
                    $total_transferred = 0;
                    $total_sales = 0;
                @endphp
                @foreach($monthsData as $data)
                @php
                    $progress = $data->goal > 0 ? min(100, ($data->revenue / $data->goal) * 100) : 0;
                    $total_goal += $data->goal;
                    $total_revenue += $data->revenue;
                    $total_transferred += $data->transferred;
                    $total_sales += $data->sales;
                @endphp
                <tr>
                    <td style="font-weight: 800; color: var(--text-main);">{{ $data->month_name }}</td>
                    <td style="font-weight: 600;">${{ number_format($data->goal) }}</td>
                    <td style="font-weight: 600;">{{ $data->transferred }}</td>
                    <td style="font-weight: 600; color: var(--green);">{{ $data->sales }}</td>
                    <td style="font-weight: 800; color: var(--primary);">${{ number_format($data->revenue) }}</td>
                    <td>
                        <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--info);">{{ $data->conversion_rate }}%</span>
                    </td>
                    <td style="width: 150px;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 5px;">{{ round($progress) }}% of goal</div>
                        <div style="width: 100%; height: 6px; background: var(--bg-subtle); border-radius: var(--radius-pill); overflow: hidden;">
                            <div style="width: {{ $progress }}%; height: 100%; background: {{ $progress >= 100 ? 'var(--success)' : 'var(--primary)' }};"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: var(--bg-body); border-top: 2px solid var(--border);">
                    <td style="font-weight: 900; color: var(--text-main); padding: 15px;">YEARLY TOTALS</td>
                    <td style="font-weight: 800;">${{ number_format($total_goal) }}</td>
                    <td style="font-weight: 800;">{{ $total_transferred }}</td>
                    <td style="font-weight: 800; color: var(--green);">{{ $total_sales }}</td>
                    <td style="font-weight: 900; color: var(--primary); font-size: 1.1rem;">${{ number_format($total_revenue) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@else
<div class="alert alert-info">
    Please select an agent to view their full year performance data.
</div>
@endif

@endsection

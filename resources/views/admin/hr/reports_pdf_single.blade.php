<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['employee']->name }} — {{ $report['month']->format('F Y') }}</title>
    {{-- One person, one month, printed to be handed over in a meeting. --}}
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 3px; }
        .meta { font-size: 9.5px; color: #6b7280; margin-bottom: 12px; }
        .meta b { color: #1f2937; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 0.5px solid #cbd5e1; padding: 3px 5px; }
        thead th { background: #e2e8f0; font-size: 9px; text-align: left; }
        td.mid { text-align: center; }
        .summary { margin-top: 14px; width: 60%; }
        .summary td { border: none; padding: 2px 0; }
        .summary td.label { color: #6b7280; }
        .summary td.value { text-align: right; font-weight: bold; }
        h2 { font-size: 11px; margin: 14px 0 6px; }
    </style>
</head>
<body>
    <h1>{{ $report['employee']->name }}</h1>
    <div class="meta">
        <b>{{ $report['month']->format('F Y') }}</b> &middot;
        {{ $report['department'] }} &middot;
        {{ $report['shift'] }} &middot;
        {{ $report['employee']->employeeProfile?->designation ?: ucfirst(str_replace('_', ' ', $report['employee']->role)) }}
        &middot; generated {{ now()->format('d M Y, h:i A') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Shift</th>
                @if($report['sections']['check_times'] ?? true)<th>In</th><th>Out</th>@endif
                @if($report['sections']['working_hours'] ?? true)<th>Worked</th>@endif
                <th>Status</th>
                @if($report['sections']['late'] ?? true)<th>Late</th>@endif
                @if($report['sections']['early_off'] ?? true)<th>Early off</th>@endif
                @if($report['sections']['leaves'] ?? true)<th>Leave</th>@endif
                @if($report['sections']['remarks'] ?? true)<th>Remarks</th>@endif
            </tr>
        </thead>
        <tbody>
        @foreach($report['rows'] as $day)
            @continue(in_array($day['status_key'], ['weekend', 'holiday'], true) && !($report['sections']['weekends'] ?? true))
            <tr>
                <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}</td>
                <td>{{ $day['day'] }}</td>
                <td>{{ $day['shift'] }}</td>
                @if($report['sections']['check_times'] ?? true)
                    <td class="mid">{{ $day['in'] ?: '—' }}</td>
                    <td class="mid">{{ $day['out'] ?: '—' }}</td>
                @endif
                @if($report['sections']['working_hours'] ?? true)<td class="mid">{{ $day['worked'] }}</td>@endif
                <td @if($day['colour']) style="background: {{ $day['colour'] }};" @endif>{{ $day['status'] }}</td>
                @if($report['sections']['late'] ?? true)<td class="mid">{{ $day['late_minutes'] ?: '' }}</td>@endif
                @if($report['sections']['early_off'] ?? true)<td class="mid">{{ $day['early_minutes'] ?: '' }}</td>@endif
                @if($report['sections']['leaves'] ?? true)<td>{{ $day['leave_type'] }}</td>@endif
                @if($report['sections']['remarks'] ?? true)<td>{{ $day['remarks'] }}</td>@endif
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($report['sections']['totals'] ?? true)
        @php $summary = $report['summary']; @endphp
        <h2>Summary</h2>
        <table class="summary">
            <tr><td class="label">Days present</td><td class="value">{{ $summary['present'] }}</td></tr>
            <tr><td class="label">Half days</td><td class="value">{{ $summary['half_day'] }}</td></tr>
            @if($report['sections']['absents'] ?? true)
                <tr><td class="label">Absent</td><td class="value">{{ $summary['absent'] }}</td></tr>
            @endif
            @if($report['sections']['late'] ?? true)
                <tr><td class="label">Lates</td><td class="value">{{ $summary['late'] }} ({{ $summary['late_minutes'] }} mins)</td></tr>
            @endif
            @if($report['sections']['early_off'] ?? true)
                <tr><td class="label">Early offs</td><td class="value">{{ $summary['early_off'] }} ({{ $summary['early_minutes'] }} mins)</td></tr>
            @endif
            @if($report['sections']['late_offs'] ?? true)
                <tr><td class="label">Late-deducted offs</td><td class="value">{{ $summary['late_offs'] }}</td></tr>
            @endif
            @if($report['sections']['working_hours'] ?? true)
                <tr><td class="label">Hours worked</td><td class="value">{{ round($summary['worked_minutes'] / 60, 1) }}</td></tr>
            @endif
            @if($report['sections']['leaves'] ?? true)
                @foreach($summary['leave_by_type'] as $name => $days)
                    <tr><td class="label">{{ $name }}</td><td class="value">{{ rtrim(rtrim(number_format($days, 1), '0'), '.') }}</td></tr>
                @endforeach
            @endif
        </table>
    @endif
</body>
</html>

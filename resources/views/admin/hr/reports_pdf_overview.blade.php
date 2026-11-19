<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance {{ $report['month']->format('F Y') }}</title>
    {{--
        Printed landscape: a month is 31 columns wide and there is no arranging
        that into portrait. Everything is inline because dompdf sees this file
        on its own, with no stylesheet to load.
    --}}
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #1f2937; margin: 0; }
        h1 { font-size: 13px; margin: 0 0 2px; }
        .meta { font-size: 8px; color: #6b7280; margin-bottom: 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 0.5px solid #cbd5e1; padding: 2px 3px; }
        thead th { background: #e2e8f0; font-size: 7px; text-align: center; }
        td.name { text-align: left; white-space: nowrap; }
        td.cell { text-align: center; font-weight: bold; }
        td.total { text-align: center; }
        .legend { margin-top: 10px; font-size: 7.5px; }
        .legend span { display: inline-block; margin-right: 10px; }
        .swatch { display: inline-block; width: 14px; height: 9px; text-align: center; font-weight: bold; font-size: 6.5px; line-height: 9px; margin-right: 3px; }
    </style>
</head>
<body>
    <h1>Attendance — {{ $report['month']->format('F Y') }}</h1>
    <div class="meta">
        {{ $report['filters']['department'] ?? 'All departments' }} &middot;
        {{ $report['filters']['shift'] ?? 'All shifts' }} &middot;
        generated {{ now()->format('d M Y, h:i A') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Dept</th>
                @foreach($report['days'] as $day)
                    <th>{{ $day['number'] }}</th>
                @endforeach
                <th>P</th>
                @if($report['sections']['late'] ?? true)<th>L</th>@endif
                @if($report['sections']['absents'] ?? true)<th>A</th>@endif
                @if($report['sections']['leaves'] ?? true)<th>Lv</th>@endif
                @if($report['sections']['working_hours'] ?? true)<th>Hrs</th>@endif
            </tr>
        </thead>
        <tbody>
        @foreach($report['rows'] as $row)
            <tr>
                <td class="name">{{ $row['user']->name }}</td>
                <td class="name">{{ $row['department'] }}</td>
                @foreach($report['days'] as $day)
                    @php $cell = $row['cells'][$day['date']] ?? ['code' => '', 'colour' => null]; @endphp
                    <td class="cell" @if($cell['colour']) style="background: {{ $cell['colour'] }};" @endif>{{ $cell['code'] }}</td>
                @endforeach
                <td class="total">{{ $row['totals']['present'] + $row['totals']['half_day'] }}</td>
                @if($report['sections']['late'] ?? true)<td class="total">{{ $row['totals']['late'] }}</td>@endif
                @if($report['sections']['absents'] ?? true)<td class="total">{{ $row['totals']['absent'] }}</td>@endif
                @if($report['sections']['leaves'] ?? true)<td class="total">{{ $row['totals']['leave'] }}</td>@endif
                @if($report['sections']['working_hours'] ?? true)<td class="total">{{ round($row['totals']['worked_minutes'] / 60, 1) }}</td>@endif
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="legend">
        @foreach($report['legend'] as $entry)
            <span><span class="swatch" style="background: {{ $entry['colour'] }};">{{ $entry['code'] }}</span>{{ $entry['label'] }}</span>
        @endforeach
    </div>
</body>
</html>

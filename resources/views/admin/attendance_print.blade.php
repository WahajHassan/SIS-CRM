<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | Systematic IT Solutions</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
            background: #ffffff;
            margin: 2rem;
            font-size: 0.9rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a;
        }

        .subtitle {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 5px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
            background: #f8fafc;
            padding: 1.2rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .meta-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .meta-value {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        th {
            background: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-agent { background: rgba(16,185,129,0.1); color: #10b981; }
        .badge-closer { background: rgba(59,130,246,0.1); color: #3b82f6; }

        .break-log {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 3px;
        }

        .no-print-btn {
            background: #f99d4e;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            margin-bottom: 1.5rem;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                font-size: 0.8rem;
            }
            .header {
                margin-top: 2.5rem;
            }
            .meta-grid {
                background: none;
                border: 1px solid #cbd5e1;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; border-bottom: 1px solid #e2e8f0;">
        <span style="font-weight: 600; color: #64748b;">PDF Print Preview Mode</span>
        <div>
            <button onclick="window.print()" class="no-print-btn" style="margin: 0; background: #10b981;">Print / Save PDF</button>
            <button onclick="window.close();" class="no-print-btn" style="margin: 0 0 0 8px; background: #64748b;">Close Window</button>
        </div>
    </div>

    <div class="header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="{{ asset('img/logo/logo-dark.webp') }}" alt="Systematic IT Logo" style="height: 50px;">
            <div>
                <div class="subtitle" style="margin-top: 0; font-size: 1rem; font-weight: 600; color: #64748b;">Employee Attendance & Session Reports</div>
            </div>
        </div>
    </div>

    <div class="meta-grid">
        <div>
            <div class="meta-label">Employee Filter</div>
            <div class="meta-value">{{ $target_user ? $target_user->display_name : 'All Employees' }}</div>
        </div>
        <div>
            <div class="meta-label">Date Range</div>
            <div class="meta-value">
                @if($start_date === $end_date)
                    {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }}
                @else
                    {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}
                @endif
            </div>
        </div>
        <div>
            <div class="meta-label">Report Generated At</div>
            <div class="meta-value">{{ now()->format('M d, Y h:i A') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee Name</th>
                <th>Role</th>
                <th>Clock In</th>
                <th>Break Sessions</th>
                <th>Clock Out</th>
                <th>Total Work Hours</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $att)
            <tr>
                <td style="font-weight: 600;">{{ $att->date->format('M d, Y') }}</td>
                <td style="font-weight: 700;">{{ $att->user->display_name }}</td>
                <td>
                    <span class="badge {{ $att->user->role === 'closer' ? 'badge-closer' : 'badge-agent' }}">
                        {{ $att->user->role }}
                    </span>
                </td>
                <td style="font-weight: 600;">{{ $att->clock_in ? $att->clock_in->format('h:i A') : '--' }}</td>
                <td>
                    @if($att->breaks->count() > 0)
                        @foreach($att->breaks as $break)
                            <div class="break-log">
                                ☕ Break: {{ $break->break_start->format('h:i A') }} - 
                                {{ $break->break_end ? $break->break_end->format('h:i A') : 'Active' }} 
                                <strong>({{ $break->durationText() }})</strong>
                            </div>
                        @endforeach
                    @else
                        <span style="font-style: italic; color: #94a3b8;">No breaks taken</span>
                    @endif
                </td>
                <td style="font-weight: 600;">{{ $att->clock_out ? $att->clock_out->format('h:i A') : '--' }}</td>
                <td style="font-weight: 700; color: #10b981;">{{ $att->totalWorkedHoursText() }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; padding: 3rem; color: #64748b; font-style: italic;">
                    No records found for the selected filter parameters.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>

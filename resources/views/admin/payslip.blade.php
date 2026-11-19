@php
    $salary = (float) ($pay['monthly_salary'] ?? 0);
    $earned = (float) ($pay['earned'] ?? 0);
    $absentOff = (float) ($pay['absent_deduction'] ?? 0);
    $lateOff = (float) ($pay['late_deduction'] ?? 0);
    $net = (float) ($pay['net_salary'] ?? 0);
    $incentive = (float) ($bonus['earned'] ?? 0);
    $total = $net + $incentive;

    $money = fn ($n) => number_format((float) $n, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip — {{ $person->display_name }} — {{ $month->format('F Y') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /*
            A payslip is a document somebody keeps, so it is built for paper
            first: black on white, no shadows, and nothing that only makes sense
            on a screen. The browser's own Save as PDF is the export — which
            means no PDF library to install on a shared host, and no second
            renderer whose output could drift from this one.
        */
        * { box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            color: #0f172a;
            background: #f1f5f9;
            margin: 0;
            padding: 2rem 1rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .sheet {
            max-width: 820px; margin: 0 auto; background: #fff;
            padding: 2.4rem 2.6rem; border-radius: 10px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 2rem;
               border-bottom: 2px solid #0f172a; padding-bottom: 1.3rem; }
        .top h1 { margin: 0; font-size: 1.5rem; letter-spacing: -0.02em; }
        .top .who { font-size: 0.85rem; color: #64748b; margin-top: 3px; }
        .top .right { text-align: right; }
        .top .right b { display: block; font-size: 1.05rem; }

        .pair { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.4rem 2.4rem; margin: 1.6rem 0 1.9rem; }
        .pair .k { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
                   text-transform: uppercase; color: #94a3b8; }
        .pair .v { font-weight: 600; }

        h2 { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;
             color: #94a3b8; margin: 1.8rem 0 0.6rem; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
             text-transform: uppercase; color: #94a3b8; padding: 0 0 0.5rem; border-bottom: 1px solid #e2e8f0; }
        td { padding: 0.55rem 0; border-bottom: 1px solid #f1f5f9; }
        td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

        .total { display: flex; justify-content: space-between; align-items: baseline;
                 margin-top: 1.6rem; padding-top: 1.1rem; border-top: 2px solid #0f172a; }
        .total .label { font-weight: 700; }
        .total .amount { font-size: 1.7rem; font-weight: 800; font-variant-numeric: tabular-nums; }

        .foot { margin-top: 2.2rem; padding-top: 1.1rem; border-top: 1px solid #e2e8f0;
                font-size: 0.74rem; color: #94a3b8; display: flex; justify-content: space-between; gap: 1rem; }

        .off { color: #b91c1c; }
        .on { color: #047857; }

        .actions { max-width: 820px; margin: 0 auto 1rem; display: flex; gap: 10px; justify-content: flex-end; }
        .btn { font-family: inherit; font-size: 0.84rem; font-weight: 600; cursor: pointer;
               border: 1px solid #cbd5e1; background: #fff; color: #0f172a;
               border-radius: 8px; padding: 0.5rem 1rem; text-decoration: none; display: inline-flex; gap: 7px; }
        .btn.go { background: #0f172a; color: #fff; border-color: #0f172a; }

        /* On paper none of the page furniture belongs. */
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ url()->previous() }}" class="btn">Back</a>
        <button type="button" class="btn go" onclick="window.print()">Print or save as PDF</button>
    </div>

    <div class="sheet">
        <div class="top">
            <div>
                <h1>{{ $company->name ?? 'Payslip' }}</h1>
                <div class="who">Payslip for {{ $month->format('F Y') }}</div>
            </div>
            <div class="right">
                <b>{{ $person->display_name }}</b>
                <div class="who">
                    {{ ucwords(str_replace('_', ' ', $person->role)) }}
                    @if($person->employeeProfile?->designation)
                        · {{ $person->employeeProfile->designation }}
                    @endif
                </div>
            </div>
        </div>

        <div class="pair">
            <div>
                <div class="k">Employee</div>
                <div class="v">{{ $person->display_name }}</div>
            </div>
            <div>
                <div class="k">Period</div>
                <div class="v">{{ $month->format('1 F Y') }} – {{ $month->copy()->endOfMonth()->format('j F Y') }}</div>
            </div>
            <div>
                <div class="k">Department</div>
                <div class="v">{{ $person->employeeProfile?->department ?: '—' }}</div>
            </div>
            <div>
                <div class="k">Monthly salary</div>
                <div class="v">{{ $money($salary) }}</div>
            </div>
        </div>

        {{-- The days first, because every figure below is worked out from them
             and somebody checking a payslip starts by checking the days. --}}
        <h2>The month</h2>
        <table>
            <thead>
                <tr><th>Days</th><th class="n">Count</th></tr>
            </thead>
            <tbody>
                <tr><td>Working days in the month</td><td class="n">{{ $pay['working_days'] ?? 0 }}</td></tr>
                <tr><td>Present</td><td class="n">{{ $pay['present_days'] ?? 0 }}</td></tr>
                @if(($pay['half_days'] ?? 0) > 0)
                    <tr><td>Half days</td><td class="n">{{ $pay['half_days'] }}</td></tr>
                @endif
                @if(($pay['leave_days'] ?? 0) > 0)
                    <tr><td>Paid leave</td><td class="n">{{ $pay['leave_days'] }}</td></tr>
                @endif
                @if(($pay['unpaid_leave_days'] ?? 0) > 0)
                    <tr><td>Unpaid leave</td><td class="n">{{ $pay['unpaid_leave_days'] }}</td></tr>
                @endif
                @if(($pay['absent_days'] ?? 0) > 0)
                    <tr><td>Absent</td><td class="n">{{ $pay['absent_days'] }}</td></tr>
                @endif
                @if(($pay['late_days'] ?? 0) > 0)
                    <tr>
                        <td>Late arrivals</td>
                        <td class="n">{{ $pay['late_days'] }} ({{ \App\Services\PayrollService::hoursText((int) ($pay['late_minutes'] ?? 0)) }})</td>
                    </tr>
                @endif
                <tr><td><b>Days credited</b></td><td class="n"><b>{{ $pay['credited_days'] ?? 0 }}</b></td></tr>
            </tbody>
        </table>

        <h2>Pay</h2>
        <table>
            <thead>
                <tr><th>Item</th><th class="n">Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Earned for {{ $pay['credited_days'] ?? 0 }} day(s) at {{ $money($pay['per_day'] ?? 0) }}</td>
                    <td class="n">{{ $money($earned) }}</td>
                </tr>

                {{--
                    Absence is not a subtraction, and showing it as one makes the
                    arithmetic on the page wrong.

                    Days that were not worked are simply not among the credited
                    days, so they never entered the figure above. Lateness is the
                    only thing actually taken off. Printing absence with a minus
                    sign gave a payslip whose own numbers did not add up — which
                    is the fastest way to make somebody distrust every figure on
                    it. It is said here as what it is: the days, and what they
                    would have been worth.
                --}}
                @if($absentOff > 0)
                    <tr>
                        <td class="off">
                            @php
                                // Built here rather than inline: a Blade directive
                                // glued to the end of a word is not read as one.
                                $missed = [];
                                if (($pay['absent_days'] ?? 0) > 0) $missed[] = $pay['absent_days'] . ' day(s) absent';
                                if (($pay['unpaid_leave_days'] ?? 0) > 0) $missed[] = $pay['unpaid_leave_days'] . ' unpaid';
                                if (($pay['half_days'] ?? 0) > 0) $missed[] = $pay['half_days'] . ' half day(s)';
                            @endphp
                            Not earned — {{ implode(', ', $missed) }}
                            <div style="font-size: 0.74rem; color: #94a3b8;">worth {{ $money($absentOff) }} at {{ $money($pay['per_day'] ?? 0) }} a day</div>
                        </td>
                        <td class="n off">—</td>
                    </tr>
                @endif

                @if($lateOff > 0)
                    <tr>
                        <td class="off">Less lateness ({{ $pay['late_deducted_days'] }} day(s))</td>
                        <td class="n off">− {{ $money($lateOff) }}</td>
                    </tr>
                @endif

                <tr>
                    <td><b>Salary</b></td>
                    <td class="n"><b>{{ $money($net) }}</b></td>
                </tr>

                @if($bonus)
                    <tr>
                        <td class="on">
                            Incentive
                            @if(($bonus['sales'] ?? 0) > 0)
                                — {{ $money($bonus['sales']) }} in sales
                                @if(($bonus['target'] ?? 0) > 0)
                                    against a {{ $money($bonus['target']) }} target
                                @endif
                            @endif
                        </td>
                        <td class="n on">{{ $incentive > 0 ? '+ ' . $money($incentive) : $money(0) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="total">
            <span class="label">Total for {{ $month->format('F Y') }}</span>
            <span class="amount">{{ $money($total) }}</span>
        </div>

        <div class="foot">
            <span>
                Worked out from the attendance on file for {{ $month->format('F Y') }}.
                @if($month->isSameMonth(now()))
                    This month is not finished, so these figures are what has been earned so far.
                @endif
            </span>
            <span style="white-space: nowrap;">Issued {{ now()->format('j M Y') }}</span>
        </div>
    </div>
</body>
</html>

@extends('layouts.admin')

@section('title', 'My Wallet')

@section('content')
{{--
    What somebody has earned.

    Nothing here is recorded anywhere new: the salary is read off the same
    attendance the payroll sheet reads, and the incentive off the same deals the
    overview counts. If this screen ever disagreed with the payroll sheet, one of
    them would be wrong — and this one has no figures of its own to be wrong
    with.

    So the job here is not to calculate. It is to answer, in one look, the
    question people were opening four screens to answer: what am I getting this
    month, and why is it not more.
--}}
@php
    $currency = 'Rs';

    $salary = (float) ($pay['monthly_salary'] ?? 0);
    $net = (float) ($pay['net_salary'] ?? 0);
    $absentCut = (float) ($pay['absent_deduction'] ?? 0);
    $lateCut = (float) ($pay['late_deduction'] ?? 0);
    $earnedBonus = (float) ($bonus['earned'] ?? 0);

    $takeHome = $net + $earnedBonus;
    $heldBack = $absentCut + $lateCut;

    $money = fn ($n) => $currency . number_format((float) $n);
@endphp

<style>
    .wl-top { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 1.3rem; align-items: start; }
    @media (max-width: 1000px) { .wl-top { grid-template-columns: 1fr; } }

    /* The one number people came for. */
    .wl-hero {
        position: relative; overflow: hidden;
        border-radius: var(--radius-lg); padding: 1.6rem 1.5rem; color: #fff;
        background: linear-gradient(135deg, #0F766E 0%, #047857 55%, #065F46 100%);
    }
    .wl-hero::after {
        content: ''; position: absolute; right: -70px; top: -70px; width: 240px; height: 240px;
        border-radius: 50%; background: rgba(255,255,255,0.07); pointer-events: none;
    }
    .wl-hero .wl-what {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: rgba(255,255,255,0.72);
    }
    .wl-hero .wl-sum {
        font-size: 2.3rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1.1;
        margin: 6px 0 4px; font-variant-numeric: tabular-nums;
    }
    .wl-hero .wl-when { font-size: 0.84rem; color: rgba(255,255,255,0.78); }
    .wl-split { display: flex; gap: 1.6rem; margin-top: 1.3rem; flex-wrap: wrap; }
    .wl-split div span {
        display: block; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.06em;
        text-transform: uppercase; color: rgba(255,255,255,0.62);
    }
    .wl-split div b { font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }

    /* Why it is not more. */
    .wl-line { display: flex; align-items: center; gap: 10px; padding: 0.62rem 0.1rem;
               border-bottom: 1px solid var(--border); font-size: 0.86rem; }
    .wl-line:last-child { border-bottom: none; }
    .wl-line .wl-name { flex: 1; color: var(--text-muted); }
    .wl-line .wl-why { font-size: 0.74rem; color: var(--text-soft); }
    .wl-line b { font-variant-numeric: tabular-nums; }
    .wl-line.is-plus b { color: var(--success); }
    .wl-line.is-minus b { color: var(--danger); }
    .wl-line.is-total { border-top: 2px solid var(--border); margin-top: 4px; padding-top: 0.8rem; font-weight: 700; }
    .wl-line.is-total .wl-name { color: var(--text-main); font-weight: 700; }

    .wl-months { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    .wl-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto auto; gap: 14px;
              align-items: center; padding: 0.7rem 0.1rem; border-bottom: 1px solid var(--border); }
    .wl-row:last-child { border-bottom: none; }
    .wl-row .wl-m { font-weight: 600; font-size: 0.88rem; }
    .wl-row .wl-m small { display: block; font-weight: 400; font-size: 0.74rem; color: var(--text-muted); }
    .wl-row .wl-fig { font-variant-numeric: tabular-nums; font-size: 0.86rem; text-align: right; min-width: 86px; }
    .wl-row .wl-fig span { display: block; font-size: 0.64rem; color: var(--text-soft);
                           text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
    @media (max-width: 700px) { .wl-row { grid-template-columns: 1fr auto; } }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">
        Everything you have earned, worked out from your own attendance and your own sales.
    </div>
    <div class="page-actions">
        <form method="GET" class="wl-months">
            <select name="month" class="control" style="width: auto;" onchange="this.form.submit()">
                @foreach($months as $option)
                    <option value="{{ $option['key'] }}" {{ $option['current'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </form>

        {{-- The thing people are actually asked for when they rent a flat.
             Opens in its own tab because it is a document, not a screen. --}}
        @if($pay)
            <a href="{{ route('admin.payslip', ['user' => $me->id, 'month' => $month->format('Y-m')]) }}"
               target="_blank" rel="noopener" class="btn btn-ghost" title="A payslip you can print or save">
                <i class="fas fa-file-invoice-dollar"></i> Payslip
            </a>
        @endif
    </div>
</div>

@if(!$pay)
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-wallet"></i>
            <strong>Nothing for {{ $month->format('F Y') }} yet</strong>
            <p>Once there is attendance on the clock for this month, what it adds up to shows here.</p>
        </div>
    </div>
@else

<div class="wl-top" style="margin-bottom: 1.3rem;">
    {{-- ---------- the figure ---------- --}}
    <div class="wl-hero">
        <div class="wl-what">Yours for {{ $month->format('F') }}</div>
        <div class="wl-sum">{{ $money($takeHome) }}</div>
        <div class="wl-when">
            @if($month->isSameMonth(now()))
                So far this month — it carries on counting
            @else
                {{ $month->format('F Y') }}
            @endif
        </div>

        <div class="wl-split">
            <div>
                <span>Salary</span>
                <b>{{ $money($net) }}</b>
            </div>
            @if($bonus)
            <div>
                <span>Incentive</span>
                <b>{{ $money($earnedBonus) }}</b>
            </div>
            @endif
            @if($heldBack > 0)
            <div>
                <span>Held back</span>
                <b>−{{ $money($heldBack) }}</b>
            </div>
            @endif
        </div>
    </div>

    {{-- ---------- how it got there ---------- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-receipt"></i> How it adds up</div>
        </div>

        <div class="wl-line">
            <span class="wl-name">Monthly salary</span>
            <b>{{ $money($salary) }}</b>
        </div>

        <div class="wl-line">
            <span class="wl-name">
                Days worked
                <span class="wl-why">{{ $pay['present_days'] }} of {{ $pay['working_days'] }} working days</span>
            </span>
            <b>{{ $money($pay['earned'] ?? 0) }}</b>
        </div>

        @if($absentCut > 0)
        <div class="wl-line is-minus">
            <span class="wl-name">
                Absent
                <span class="wl-why">{{ $pay['absent_days'] }} {{ \Illuminate\Support\Str::plural('day', $pay['absent_days']) }} with no leave</span>
            </span>
            <b>−{{ $money($absentCut) }}</b>
        </div>
        @endif

        @if($lateCut > 0)
        <div class="wl-line is-minus">
            <span class="wl-name">
                Late
                <span class="wl-why">{{ $pay['late_days'] }} late {{ \Illuminate\Support\Str::plural('arrival', $pay['late_days']) }}, costing {{ $pay['late_deducted_days'] }} {{ \Illuminate\Support\Str::plural('day', $pay['late_deducted_days']) }}</span>
            </span>
            <b>−{{ $money($lateCut) }}</b>
        </div>
        @endif

        @if($bonus && $earnedBonus > 0)
        <div class="wl-line is-plus">
            <span class="wl-name">
                Incentive
                <span class="wl-why">on ${{ number_format($bonus['sales']) }} of sales</span>
            </span>
            <b>+{{ $money($earnedBonus) }}</b>
        </div>
        @endif

        <div class="wl-line is-total">
            <span class="wl-name">Yours</span>
            <b>{{ $money($takeHome) }}</b>
        </div>
    </div>
</div>

{{-- ---------- what is still on the table ---------- --}}
@if($bonus && !empty($bonus['blocks']))
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-bullseye"></i> What is still on the table</div>
        <span class="chip">{{ $bonus['progress'] }}% of target</span>
    </div>

    @foreach($bonus['blocks'] as $block)
        <div class="wl-line {{ $block['achieved'] ? 'is-plus' : '' }}">
            <span class="wl-name">
                {{ $block['label'] }}
                <span class="wl-why">{{ $block['figure'] }} {{ $block['figure_note'] }}{{ $block['note'] ? ' · ' . $block['note'] : '' }}</span>
            </span>
            @if($block['achieved'])
                <span class="badge badge-success">Earned</span>
            @else
                <span class="chip">{{ $block['progress'] }}%</span>
            @endif
        </div>
    @endforeach
</div>
@endif

{{-- ---------- the months behind ---------- --}}
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-clock-rotate-left"></i> The months behind</div>
    </div>

    @forelse($history as $row)
        <div class="wl-row">
            <span class="wl-m">
                {{ $row['label'] }}
                <small>{{ $row['present_days'] }} days in{{ $row['absent_days'] ? ' · ' . $row['absent_days'] . ' absent' : '' }}{{ $row['late_days'] ? ' · ' . $row['late_days'] . ' late' : '' }}</small>
            </span>

            <span class="wl-fig"><span>Salary</span>{{ $money($row['net']) }}</span>

            @if($bonus)
                <span class="wl-fig"><span>Incentive</span>{{ $money($row['bonus']) }}</span>
            @endif

            <span class="wl-fig" style="font-weight: 700;"><span>Total</span>{{ $money($row['total']) }}</span>
        </div>
    @empty
        <div class="empty-state">
            <i class="fas fa-clock-rotate-left"></i>
            <strong>Nothing behind this month yet</strong>
            <p>Each month closes into this list once it has been worked.</p>
        </div>
    @endforelse
</div>

@endif
@endsection

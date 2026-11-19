@extends('layouts.admin')
@section('title', '')

@section('content')
<style>
    /* Only this screen's own pieces. Everything else is the system's. */
    .tl-sub { font-size: 0.74rem; color: var(--text-muted); font-weight: 400; margin-top: 2px; }
    .tl-trade {
        display: inline-block; padding: 1px 8px; border-radius: var(--radius-pill);
        font-size: 0.68rem; font-weight: 700;
        background: rgba(99,102,241,0.12); color: #6366F1;
    }
    html.dark-mode .tl-trade { background: rgba(129,140,248,0.16); color: #A5B4FC; }
    .tl-when { font-size: 0.85rem; font-weight: 600; color: var(--primary); white-space: nowrap; }
    .tl-contact { font-size: 0.84rem; display: flex; align-items: center; gap: 6px; }
    .tl-contact i { color: var(--text-soft); width: 12px; }
    .tl-acts { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }

    /* Eleven columns do not fit a laptop. Rather than squeezing every cell
       until a note reads one word per line, the table keeps its width and the
       box around it scrolls. */
    .table-wrap > table { min-width: 1180px; }
    .table-wrap td:nth-child(5) { min-width: 150px; }
    .tl-note { font-size: 0.68rem; color: var(--text-muted); }

    /* The label above a field, wherever a field appears on this screen. */
    .tl-label {
        display: block; margin-bottom: 6px;
        font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; color: var(--text-soft);
    }
</style>

<div class="page-head">
    <div>
        <h1><i class="fas fa-right-left"></i> Transferred leads &amp; follow ups</h1>
        <div class="page-sub">Who has the lead, when the call is, and how it ended</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.transferred_leads.export', request()->query()) }}" class="btn btn-ghost"
           title="Download these handovers as a spreadsheet">
            <i class="fas fa-file-csv"></i> Export
        </a>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('transferLeadModal').classList.add('show')">
            <i class="fas fa-plus"></i> Add follow up
        </button>
    </div>
</div>

<form method="GET" action="{{ route('admin.transferred_leads') }}" class="filter-bar">
    <div class="filter-field" style="max-width: 220px;">
        <label for="monthPicker">Month</label>
        <div style="position: relative;">
            <input type="text" id="monthPicker" name="month" value="{{ request('month') }}" placeholder="Every month" style="padding-right: 2.2rem; cursor: pointer;">
            <i class="far fa-calendar-alt" style="position: absolute; right: 11px; top: 50%; transform: translateY(-50%); color: var(--text-soft); pointer-events: none;"></i>
        </div>
    </div>
    @if(auth()->user()->isCloser())
    <div class="filter-field" style="max-width: 220px;">
        <label for="filterAgent">Agent</label>
        <select id="filterAgent" name="filter_agent_id" onchange="this.form.submit()">
            <option value="">All agents</option>
            @foreach($agents as $agt)
                <option value="{{ $agt->id }}" {{ request('filter_agent_id') == $agt->id ? 'selected' : '' }}>{{ $agt->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="filter-go">
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
        <a href="{{ route('admin.transferred_leads') }}" class="btn btn-ghost">Clear</a>
    </div>
</form>

@include('admin.partials.lead_pipeline_board')

<div id="statsContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.1rem; margin-bottom: 1.3rem;">
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Active schedules</div>
            <span class="kpi-icon is-primary"><i class="fas fa-calendar-check"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) ($active_count ?? 0) }}">{{ $active_count ?? 0 }}</div>
        <div class="kpi-foot"><span>calls still to happen</span></div>
    </div>
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Missed follow ups</div>
            <span class="kpi-icon is-danger"><i class="fas fa-calendar-times"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) ($missed_count ?? 0) }}">{{ $missed_count ?? 0 }}</div>
        <div class="kpi-foot"><span>went past their time</span></div>
    </div>

    {{-- What the handovers came to. A list of leads without this is a list of
         work; with it, it is a list of results. --}}
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Won</div>
            <span class="kpi-icon is-success"><i class="fas fa-trophy"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) ($won_count ?? 0) }}">{{ $won_count ?? 0 }}</div>
        <div class="kpi-foot">
            <span>
                @if(($settled_count ?? 0) > 0)
                    {{ $conversion_rate ?? 0 }}% of the {{ $settled_count }} that were settled
                @else
                    none settled yet
                @endif
            </span>
        </div>
    </div>

    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Worth</div>
            <span class="kpi-icon is-primary"><i class="fas fa-sack-dollar"></i></span>
        </div>
        <div class="kpi-value">${{ number_format((float) ($won_value ?? 0)) }}</div>
        <div class="kpi-foot">
            <span>
                @if(($awaiting_figure ?? 0) > 0)
                    {{ $awaiting_figure }} won without a figure yet
                @else
                    from the deals these became
                @endif
            </span>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-container table-wrap">
        <table>
            <thead>
                <tr>
                    @if(auth()->user()->isCloser())
                    <th>Agent</th>
                    @endif
                    <th>Customer Name</th>
                    <th>Trade / Where</th>
                    <th>Contact</th>
                    <th>Notes</th>
                    <th>Scheduled At</th>
                    <th>Stage</th>
                    <th style="text-align: right;">Worth</th>
                    <th>Closer</th>
                    <th>Status</th>
                    <th>Converted</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transferred_leads as $lead)
                <tr data-lead-id="{{ $lead->id }}" data-proposal-sent="{{ $lead->is_proposal_sent ? '1' : '0' }}" data-15m-dismissed="{{ is_array($lead->reminders_sent) && in_array('15m_dismissed', $lead->reminders_sent) ? '1' : '0' }}">
                    @if(auth()->user()->isCloser())
                    <td style="font-weight: 600;">{{ $lead->agent ? $lead->agent->name : 'Unknown' }}</td>
                    @endif
                    <td style="font-weight: 500;">
                        {{ $lead->displayName() }}
                        @if($lead->business_name && $lead->business_name !== $lead->customer_name)
                            <div class="tl-sub">{{ $lead->customer_name }}</div>
                        @endif
                        @if($lead->is_proposal_sent)
                            <span class="badge badge-success" style="margin-left: 5px;">Proposal sent</span>
                        @elseif(is_array($lead->reminders_sent) && in_array('15m_dismissed', $lead->reminders_sent))
                            <span class="badge badge-danger" style="margin-left: 5px;">Not sent</span>
                        @endif
                    </td>
                    <td style="font-size: 0.8rem;">
                        @if($lead->category)
                            <span class="tl-trade">{{ $lead->category->name }}</span>
                        @endif
                        <div style="color: var(--text-muted); margin-top: 4px;">{{ $lead->place() ?: '—' }}</div>
                    </td>
                    <td>
                        @if($lead->phone)<div class="tl-contact"><i class="fas fa-phone"></i> {{ $lead->phone }}</div>@endif
                        @if($lead->email)<div class="tl-contact"><i class="fas fa-envelope"></i> {{ $lead->email }}</div>@endif
                    </td>
                    <td>{{ \Illuminate\Support\Str::limit($lead->notes, 30) ?: '-' }}</td>
                    <td class="tl-when">
                        @if($lead->scheduled_at)
                            <i class="far fa-clock"></i> {{ \Carbon\Carbon::parse($lead->scheduled_at)->format('M d, Y h:i A') }}
                            @if($lead->status === 'Active')
                            <div class="realtime-timer" data-id="{{ $lead->id }}" data-name="{{ htmlspecialchars($lead->customer_name) }}" data-time="{{ \Carbon\Carbon::parse($lead->scheduled_at)->toIso8601String() }}" style="font-size: 0.75rem; font-weight: 700; margin-top: 3px;"></div>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    {{-- Where it is in the pipeline, and who has it. --}}
                    <td>
                        @php
                            // Where it got to, in the system's own vocabulary
                            // for done, lost, waiting and still in play.
                            $stageTone = match($lead->stage) {
                                'won' => 'badge-success',
                                'lost' => 'badge-danger',
                                'pool' => '',
                                'proposal' => 'badge-info',
                                default => 'badge-warning',
                            };
                        @endphp
                        <span class="badge {{ $stageTone }}">{{ $lead->stageLabel() }}</span>
                        @if($lead->lost_reason)
                            <div class="tl-note">{{ $lead->lost_reason }}</div>
                        @endif
                    </td>

                    {{-- What the handover came to. This is the question anybody
                         looking at this list is actually asking, and until now
                         the answer was two screens away on the deal. --}}
                    <td style="text-align: right; white-space: nowrap;">
                        @if($lead->deal)
                            <a href="{{ route('admin.deals', ['search' => $lead->deal->name]) }}"
                               style="text-decoration: none; color: var(--text-main); font-weight: 700; font-variant-numeric: tabular-nums;">
                                ${{ number_format((float) $lead->deal->value, 2) }}
                            </a>
                            <div class="tl-note">
                                <span class="badge {{ $lead->deal->status === 'WON' ? 'badge-success' : ($lead->deal->status === 'LOST' ? 'badge-danger' : 'badge-warning') }}"
                                      style="font-size: 0.58rem;">{{ $lead->deal->status }}</span>
                                Deal #{{ $lead->deal->id }}
                            </div>
                        @elseif($lead->stage === 'lost')
                            <span style="color: var(--text-soft);">—</span>
                        @else
                            <span style="color: var(--text-soft);">not yet</span>
                        @endif
                    </td>
                    <td style="font-size: 0.82rem;">
                        {{ $lead->closer?->display_name ?? '—' }}
                        @if($lead->claimed_at)
                            <div class="tl-note">picked up {{ $lead->claimed_at->diffForHumans() }}</div>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusTone = match($lead->status) {
                                'Missed' => 'badge-danger',
                                'Active' => 'badge-info',
                                default => 'badge-success',
                            };
                        @endphp
                        <span class="status-badge badge {{ $statusTone }} {{ strtolower($lead->status) }}">{{ $lead->status }}</span>
                    </td>
                    <td>
                        @php
                            // Converting is something you do; the rest is
                            // something that is true. One looks like a button,
                            // the others like a state.
                            if ($lead->is_converted) {
                                $convertedText = 'Converted';
                                $convertedIcon = 'fa-check-circle';
                                $convertedTone = 'badge-success';
                            } elseif ($lead->status === 'Active') {
                                $convertedText = 'Pending';
                                $convertedIcon = 'fa-clock';
                                $convertedTone = 'badge-warning';
                            } elseif ($lead->status === 'Completed') {
                                $convertedText = 'Convert';
                                $convertedIcon = 'fa-exchange-alt';
                                $convertedTone = '';
                            } else { // Missed
                                $convertedText = 'Not Converted';
                                $convertedIcon = 'fa-times-circle';
                                $convertedTone = 'badge-danger';
                            }
                        @endphp
                        @if(auth()->user()->isCloser() && $lead->status !== 'Missed')
                        <form action="{{ route('admin.transferred_leads.convert', $lead->id) }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $lead->is_converted ? 'btn-ghost' : 'btn-primary' }}">
                                <i class="fas {{ $convertedIcon }}"></i> {{ $convertedText }}
                            </button>
                        </form>
                        @else
                            <span class="badge {{ $convertedTone }}"><i class="fas {{ $convertedIcon }}"></i> {{ $convertedText }}</span>
                        @endif
                    </td>
                    <td>
                      <div class="tl-acts">
                        <button class="btn-action edit btn btn-ghost btn-sm" title="Edit Lead" style="color: var(--info);"
                            data-id="{{ $lead->id }}"
                            data-customer_name="{{ $lead->customer_name }}"
                            data-owner_name="{{ $lead->owner_name }}"
                            data-phone="{{ $lead->phone }}"
                            data-personal_number="{{ $lead->personal_number }}"
                            data-email="{{ $lead->email }}"
                            data-personal_email="{{ $lead->personal_email }}"
                            data-address="{{ $lead->address }}"
                            data-yelp_link="{{ $lead->yelp_link }}"
                            data-google_link="{{ $lead->google_link }}"
                            data-notes="{{ $lead->notes }}"
                            data-card_information="{{ json_encode($lead->card_information) }}"
                            data-scheduled_at="{{ $lead->scheduled_at ? \Carbon\Carbon::parse($lead->scheduled_at)->format('Y-m-d\TH:i') : '' }}"
                            data-status="{{ $lead->status }}"
                            onclick="editLead(this)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('admin.transferred_leads.delete', $lead->id) }}" method="POST" onsubmit="confirmDelete(event, this);" style="margin: 0; display: inline-flex;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action delete btn btn-ghost btn-sm" title="Delete Lead" style="color: var(--danger);">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                      </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11">
                        <div class="empty-state">
                            <i class="fas fa-right-left"></i>
                            <strong>Nothing has been handed over yet</strong>
                            <p>Leads an agent transfers to a closer show up here, with the time of the call and how it ended.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transferred_leads->hasPages())
        <div style="padding: 1rem 0 0;">
            {{ $transferred_leads->links() }}
        </div>
    @endif
</div>

<!-- Add Transfer Lead Modal -->
<div class="modal-overlay" id="transferLeadModal">
    <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <button class="close-modal" onclick="document.getElementById('transferLeadModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 1.5rem;">Add Follow Up / Transfer</h2>
        <form action="{{ route('admin.transferred_leads.store') }}" method="POST">
            @csrf
            <input type="hidden" name="lead_id" id="transferLeadId" value="{{ request('lead_id') }}">

            {{-- What the agent already knows goes with the lead, so the closer
                 is not looking the business up again before the call. --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Business Name</label>
                    <input type="text" name="business_name" id="transferBusinessName" value="{{ request('business_name') }}" placeholder="e.g. Tucson Gutter Pros"
                           class="control">
                </div>
                <div>
                    <label class="tl-label">Trade / Category</label>
                    <select name="business_category_id" id="transferTrade" class="control">
                        <option value="">Not set</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) request('business_category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">State</label>
                    <select name="usa_state_id" id="transferState" data-cities="transferCity"
                            class="control">
                        <option value="">Not set</option>
                        @foreach($states as $state)
                            <option value="{{ $state->id }}" {{ (string) request('usa_state_id') === (string) $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="tl-label">City</label>
                    <select name="city_id" id="transferCity" data-selected="{{ request('city_id') }}"
                            class="control">
                        <option value="">Pick a state first</option>
                    </select>
                </div>
            </div>

            {{-- Nobody named means the pool, which is how most of these go. --}}
            <div style="margin-bottom: 1rem;">
                <label class="tl-label">Hand it to</label>
                <select name="closer_id" class="control">
                    <option value="">The pool — whoever is free takes it</option>
                    @foreach($closers as $closer)
                        <option value="{{ $closer->id }}">{{ $closer->display_name }}</option>
                    @endforeach
                </select>
            </div>
            
            @if(auth()->user()->isCloser())
            <div style="margin-bottom: 1rem;">
                <label class="tl-label">Assign Agent (Optional)</label>
                <div class="custom-dropdown" tabindex="0">
                    <div class="dropdown-selected">
                        <span>-- Myself --</span>
                        <i class="fas fa-chevron-down" style="color: #94a3b8; font-size: 0.8rem;"></i>
                    </div>
                    <div class="dropdown-options">
                        <div class="dropdown-option selected" data-value="">-- Myself --</div>
                        @foreach($agents as $agt)
                            <div class="dropdown-option" data-value="{{ $agt->id }}">{{ $agt->name }}</div>
                        @endforeach
                    </div>
                    <select name="agent_id" style="display:none;">
                        <option value="">-- Myself --</option>
                        @foreach($agents as $agt)
                            <option value="{{ $agt->id }}">{{ $agt->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Client Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="customer_name" value="{{ request('customer_name') }}" required style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Owner Name</label>
                    <input type="text" name="owner_name" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Business Number</label>
                    <input type="text" name="phone" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Personal Number</label>
                    <input type="text" name="personal_number" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Business Email</label>
                    <input type="email" name="email" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Personal Email</label>
                    <input type="email" name="personal_email" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="tl-label">Address</label>
                <input type="text" name="address" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Yelp Link</label>
                    <input type="url" name="yelp_link" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Google Link</label>
                    <input type="url" name="google_link" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="tl-label">Notes / Additional Info</label>
                <textarea name="notes" rows="3" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);"></textarea>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->isCloser())
            <h3 style="font-size: 1rem; color: var(--text-main); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                💳 Card Information (Confidential)
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <input type="text" name="card_information[name]" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Name on Card">
                </div>
                <div>
                    <input type="text" name="card_information[expiry]" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="MM/YY">
                </div>
                <div>
                    <input type="text" name="card_information[number]" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="16-digit Card Number">
                </div>
                <div>
                    <input type="text" name="card_information[cvv]" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="CVV">
                </div>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <input type="text" name="card_information[address]" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Billing Address">
            </div>
            @endif

            <div style="padding: 1.25rem; background: var(--bg-body); border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border); border-left: 4px solid var(--primary); position: relative; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.02);">
                <div style="position: absolute; top: -10px; right: -10px; opacity: 0.05; font-size: 6rem; color: var(--primary); pointer-events: none;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div style="font-weight: 700; margin-bottom: 12px; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                    <div style="background: var(--bg-card, #fff); box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: var(--primary); width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    Schedule Follow Up <span style="color:var(--danger)">*</span>
                </div>
                <div class="datetime-wrapper" style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 2;"><i class="far fa-clock"></i></span>
                    <input type="text" name="scheduled_at" class="datetime-picker" required placeholder="Select date and time...">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Save Follow Up</button>
        </form>
    </div>
</div>

<!-- Edit Transfer Lead Modal -->
<div class="modal-overlay" id="editLeadModal">
    <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <button class="close-modal" onclick="document.getElementById('editLeadModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 1.5rem;">Edit Follow Up</h2>
        <form id="editLeadForm" method="POST">
            @csrf
            @method('PUT')
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Client Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="customer_name" id="edit_customer_name" required style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Owner Name</label>
                    <input type="text" name="owner_name" id="edit_owner_name" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Business Number</label>
                    <input type="text" name="phone" id="edit_phone" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Personal Number</label>
                    <input type="text" name="personal_number" id="edit_personal_number" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Business Email</label>
                    <input type="email" name="email" id="edit_email" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Personal Email</label>
                    <input type="email" name="personal_email" id="edit_personal_email" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="tl-label">Address</label>
                <input type="text" name="address" id="edit_address" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="tl-label">Yelp Link</label>
                    <input type="url" name="yelp_link" id="edit_yelp_link" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label class="tl-label">Google Link</label>
                    <input type="url" name="google_link" id="edit_google_link" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="tl-label">Notes / Additional Info</label>
                <textarea name="notes" id="edit_notes" rows="3" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);"></textarea>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->isCloser())
            <h3 style="font-size: 1rem; color: var(--text-main); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                💳 Card Information (Confidential)
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <input type="text" name="card_information[name]" id="edit_card_name" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Name on Card">
                </div>
                <div>
                    <input type="text" name="card_information[expiry]" id="edit_card_expiry" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="MM/YY">
                </div>
                <div>
                    <input type="text" name="card_information[number]" id="edit_card_number" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="16-digit Card Number">
                </div>
                <div>
                    <input type="text" name="card_information[cvv]" id="edit_card_cvv" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="CVV">
                </div>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <input type="text" name="card_information[address]" id="edit_card_address" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Billing Address">
            </div>
            @endif

            <div style="margin-bottom: 1.5rem;">
                <label class="tl-label">Status</label>
                <select name="status" id="edit_status" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);">
                    <option value="Active">Active</option>
                    <option value="Missed">Missed</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>

            <div style="padding: 1.25rem; background: var(--bg-body); border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border); border-left: 4px solid var(--primary); position: relative; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.02);">
                <div style="position: absolute; top: -10px; right: -10px; opacity: 0.05; font-size: 6rem; color: var(--primary); pointer-events: none;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div style="font-weight: 700; margin-bottom: 12px; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                    <div style="background: var(--bg-card, #fff); box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: var(--primary); width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    Schedule Follow Up
                </div>
                <div class="datetime-wrapper" style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 2;"><i class="far fa-clock"></i></span>
                    <input type="text" name="scheduled_at" id="edit_scheduled_at" class="datetime-picker" placeholder="Select date and time...">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Update Follow Up</button>
        </form>
    </div>
</div>

<!-- 5-Minute Timeline Overlay -->
<div id="timelineOverlay" style="display:none; position: fixed; bottom: 20px; right: 20px; background: var(--bg-card); border: 2px solid var(--primary); padding: 15px 20px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 1000; max-width: 300px; animation: slideUp 0.3s ease;">
    <h3 style="margin:0 0 10px 0; color:var(--primary); font-size:1.1rem;"><i class="fas fa-hourglass-half"></i> Upcoming Follow Up!</h3>
    <p id="timelineText" style="margin:0; font-size: 0.9rem; font-weight:500;">Customer is scheduled in <span id="timelineTimer" style="font-weight:700; color:var(--danger)">5:00</span> minutes.</p>
</div>

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
<style>
.datetime-wrapper input {
    width: 100% !important;
    padding: 0.6rem 0.9rem 0.6rem 2.6rem !important;
    border-radius: var(--radius-sm) !important;
    border: 1px solid var(--border) !important;
    font-size: 0.875rem !important;
    background: var(--bg-card) !important;
    outline: none !important;
    font-weight: 500 !important;
    color: var(--text-main) !important;
    box-sizing: border-box !important;
}
.datetime-wrapper input:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px var(--ring) !important;
}
@keyframes slideUp { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.swal-custom-popup { border-radius: 12px !important; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.custom-dropdown { position: relative; outline: none; width: 100%; }
.dropdown-selected { padding: 0.55rem 0.7rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; transition: border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease); }
.dropdown-selected:hover { border-color: var(--border-strong); }
.custom-dropdown.active .dropdown-selected { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
.custom-dropdown.active .dropdown-selected i { transform: rotate(180deg); }
.dropdown-selected i { transition: transform var(--base) var(--ease); color: var(--text-soft); }
.dropdown-options { position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: var(--bg-raised); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); max-height: 220px; overflow-y: auto; z-index: 999; opacity: 0; visibility: hidden; transform: translateY(-8px); transition: opacity var(--base) var(--ease), transform var(--base) var(--ease), visibility var(--base); }
.custom-dropdown.active .dropdown-options { opacity: 1; visibility: visible; transform: translateY(0); }
.dropdown-option { padding: 0.55rem 0.75rem; font-size: 0.875rem; color: var(--text-main); cursor: pointer; transition: background-color var(--fast) var(--ease); border-bottom: 1px solid var(--border); }
.dropdown-option:hover { background: var(--bg-subtle); }
.dropdown-option:last-child { border-bottom: none; }
/* Premium Modern Flatpickr Calendar */
.flatpickr-calendar {
    background: var(--bg-raised) !important;
    box-shadow: var(--shadow-lg) !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius-lg) !important;
    padding: 10px !important;
    font-family: inherit !important;
    width: 325px !important;
}
/* The parts flatpickr paints itself, told where the page's colours are. */
.flatpickr-months, .flatpickr-weekdays, .flatpickr-innerContainer,
.flatpickr-rContainer, .flatpickr-days, .dayContainer { background: transparent !important; }
.flatpickr-month, .flatpickr-weekdaycontainer { background: transparent !important; }
.flatpickr-day.nextMonthDay, .flatpickr-day.prevMonthDay { color: var(--text-soft) !important; }
.flatpickr-day.flatpickr-disabled { color: var(--text-soft) !important; opacity: 0.45 !important; }
.flatpickr-day.today { border-color: var(--primary) !important; }
.flatpickr-calendar:before, .flatpickr-calendar:after { display: none !important; }

/* Header Formatting */
.flatpickr-months { padding-top: 5px !important; padding-bottom: 5px !important; }
.flatpickr-current-month { font-size: 1.15rem !important; padding-top: 0 !important; }
.flatpickr-current-month span.cur-month { font-weight: 800 !important; color: var(--text-main) !important; }
.flatpickr-current-month input.cur-year { font-weight: 700 !important; color: var(--text-muted) !important; background: transparent !important; }
.flatpickr-prev-month svg, .flatpickr-next-month svg { fill: var(--text-muted) !important; width: 16px !important; height: 16px !important; }
.flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: var(--primary) !important; }

/* Month Select Dropdown (For Filter by Month) */
.flatpickr-monthSelect-months {
    display: flex !important; flex-wrap: wrap !important; justify-content: space-between !important; margin-top: 10px !important; padding: 0 !important;
}
.flatpickr-monthSelect-month {
    width: 31% !important; margin-bottom: 12px !important; border-radius: var(--radius-sm) !important; padding: 12px 0 !important;
    background: var(--bg-subtle) !important; border: 1px solid var(--border) !important;
    transition: background-color var(--fast) var(--ease), border-color var(--fast) var(--ease), color var(--fast) var(--ease) !important;
    font-weight: 600 !important; color: var(--text-muted) !important; display: flex !important; align-items: center !important; justify-content: center !important; height: auto !important;
}
.flatpickr-monthSelect-month:hover { background: var(--bg-card) !important; border-color: var(--primary) !important; color: var(--primary) !important; }
.flatpickr-monthSelect-month.selected { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; font-weight: 700 !important; }

/* Normal Date Picker Days */
span.flatpickr-weekday { color: var(--text-soft) !important; font-weight: 700 !important; font-size: 0.8rem !important; background: transparent !important; }
.flatpickr-day { border-radius: var(--radius-xs) !important; font-weight: 600 !important; color: var(--text-main) !important; transition: background-color var(--fast) var(--ease), color var(--fast) var(--ease) !important; }
.flatpickr-day:hover { background: var(--bg-subtle) !important; border-color: transparent !important; color: var(--primary) !important; }
.flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange { background: var(--primary) !important; color: #fff !important; border-color: transparent !important; font-weight: 700 !important; }

/* Time Picker Formatting */
.flatpickr-time { border-top: 1px dashed var(--border-strong) !important; margin-top: 10px !important; padding-top: 5px !important; height: auto !important; max-height: none !important; background: transparent !important; }
.flatpickr-time input, .flatpickr-time .flatpickr-time-separator { font-size: 1.1rem !important; color: var(--text-main) !important; font-weight: 600 !important; padding: 5px 0 !important; background: transparent !important; }
.flatpickr-time .flatpickr-am-pm { font-weight: 700 !important; padding: 5px 0 !important; color: var(--text-main) !important; background: transparent !important; }
.flatpickr-time input:hover, .flatpickr-time .flatpickr-am-pm:hover, .flatpickr-time input:focus, .flatpickr-time .flatpickr-am-pm:focus { background: var(--bg-subtle) !important; border-radius: var(--radius-xs) !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Custom Dropdown Logic
        document.querySelectorAll('.custom-dropdown').forEach(dropdown => {
            let selected = dropdown.querySelector('.dropdown-selected');
            let options = dropdown.querySelector('.dropdown-options');
            let hiddenSelect = dropdown.querySelector('select');
            let optionDivs = dropdown.querySelectorAll('.dropdown-option');

            selected.addEventListener('click', (e) => {
                dropdown.classList.toggle('active');
            });

            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target)) dropdown.classList.remove('active');
            });

            optionDivs.forEach(opt => {
                opt.addEventListener('click', () => {
                    selected.querySelector('span').innerText = opt.innerText;
                    hiddenSelect.value = opt.getAttribute('data-value');
                    optionDivs.forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    dropdown.classList.remove('active');
                });
            });
        });
        flatpickr("#monthPicker", {
            plugins: [
                new monthSelectPlugin({
                    shorthand: true, // defaults to false
                    dateFormat: "Y-m", // defaults to "F Y"
                    altFormat: "F Y", // defaults to "F Y"
                    theme: "light" // defaults to "light"
                })
            ],
            onChange: function(selectedDates, dateStr, instance) {
                instance.element.form.submit();
            }
        });

        // Initialize Datetime Picker for Scheduled At fields
        flatpickr(".datetime-picker", {
            enableTime: true,
            dateFormat: "Y-m-d\\TH:i",
            altInput: true,
            altFormat: "F j, Y h:i K",
            time_24hr: false
        });
    });
    function editLead(button) {
        const data = button.dataset;
        document.getElementById('editLeadForm').action = '{{ url("admin/transferred-leads") }}/' + data.id;
        document.getElementById('edit_customer_name').value = data.customer_name || '';
        document.getElementById('edit_owner_name').value = data.owner_name || '';
        document.getElementById('edit_phone').value = data.phone || '';
        document.getElementById('edit_personal_number').value = data.personal_number || '';
        document.getElementById('edit_email').value = data.email || '';
        document.getElementById('edit_personal_email').value = data.personal_email || '';
        document.getElementById('edit_address').value = data.address || '';
        document.getElementById('edit_yelp_link').value = data.yelp_link || '';
        document.getElementById('edit_google_link').value = data.google_link || '';
        document.getElementById('edit_notes').value = data.notes || '';
        let cardInfo = {};
        if (data.card_information) {
            try { cardInfo = JSON.parse(data.card_information); } catch(e) {}
        }
        document.getElementById('edit_card_name').value = cardInfo.name || '';
        document.getElementById('edit_card_expiry').value = cardInfo.expiry || '';
        document.getElementById('edit_card_number').value = cardInfo.number || '';
        document.getElementById('edit_card_cvv').value = cardInfo.cvv || '';
        document.getElementById('edit_card_address').value = cardInfo.address || '';
        
        document.getElementById('edit_scheduled_at').value = data.scheduled_at || '';
        if (document.getElementById('edit_scheduled_at')._flatpickr && data.scheduled_at) {
            document.getElementById('edit_scheduled_at')._flatpickr.setDate(data.scheduled_at);
        }
        document.getElementById('edit_status').value = data.status || 'Active';
        
        document.getElementById('editLeadModal').classList.add('show');
    }

    // Real-time UI Countdown & Local Reminders
    function updateTimers() {
        const sentReminders = JSON.parse(localStorage.getItem('sentReminders') || '{}');
        let remindersUpdated = false;

        document.querySelectorAll('.realtime-timer').forEach(el => {
            const leadId = el.getAttribute('data-id');
            const customerName = el.getAttribute('data-name');
            const scheduledAt = new Date(el.getAttribute('data-time')).getTime();
            const now = new Date().getTime();
            const distance = scheduledAt - now;

            if (distance < 0) {
                el.innerHTML = "<i class='fas fa-exclamation-triangle'></i> Overdue";
                el.style.color = "var(--danger)";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            let str = "in ";
            if(days > 0) str += days + "d ";
            if(hours > 0) str += hours + "h ";
            str += minutes + "m " + seconds + "s";
            
            el.innerHTML = str;
            if(distance < 3600000) { // Less than 1 hour
                el.style.color = "var(--danger)";
            } else {
                el.style.color = "var(--primary)";
            }

            // Reminders Logic
            let reminders = sentReminders[leadId] || [];
            const diffMinutes = distance / (1000 * 60);

            if (diffMinutes <= 30 && diffMinutes > 15 && !reminders.includes('30m')) {
                reminders.push('30m');
                remindersUpdated = true;
                Swal.fire({
                    icon: 'info',
                    title: 'Upcoming Follow Up',
                    text: `Reminder: Follow up for ${customerName} is scheduled in 30 minutes!`,
                    confirmButtonColor: '#f99d4e',
                    customClass: { popup: 'swal-custom-popup' }
                });
            } else if (diffMinutes <= 15 && diffMinutes > 2 && !reminders.includes('15m')) {
                reminders.push('15m');
                remindersUpdated = true;
                window.currentPopupLeadId = leadId;
                Swal.fire({
                    title: 'Proposal Check',
                    text: `We are scheduled next 15 minutes with ${customerName}. Did you send the proposal already?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#ef4444',
                    confirmButtonText: 'Yes, sent!',
                    cancelButtonText: 'Not yet',
                    customClass: { popup: 'swal-custom-popup' }
                }).then((result) => {
                    delete window.currentPopupLeadId;
                    if (result.isConfirmed) {
                        fetch(`{{ url('admin/transferred-leads') }}/${leadId}/proposal-sent`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        }).then(() => {
                            Swal.fire('Saved!', 'Proposal marked as sent.', 'success');
                            refreshTable();
                        });
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        fetch(`{{ url('admin/transferred-leads') }}/${leadId}/dismiss-15m`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        }).then(() => {
                            refreshTable();
                        });
                    }
                });
            } else if (diffMinutes <= 2 && diffMinutes >= 0 && !reminders.includes('2m')) {
                reminders.push('2m');
                remindersUpdated = true;
                Swal.fire({
                    icon: 'warning',
                    title: 'Timeline Starting!',
                    text: `URGENT: Follow up for ${customerName} is starting in 2 minutes!`,
                    confirmButtonColor: '#f99d4e',
                    customClass: { popup: 'swal-custom-popup' }
                });
                
                document.getElementById('timelineOverlay').style.display = 'block';
                document.getElementById('timelineText').innerHTML = `<strong>${customerName}</strong> in 2 minutes!`;
                setTimeout(() => { document.getElementById('timelineOverlay').style.display = 'none'; }, 120000);
            }

            sentReminders[leadId] = reminders;
        });

        if (remindersUpdated) {
            localStorage.setItem('sentReminders', JSON.stringify(sentReminders));
        }
    }

    setInterval(updateTimers, 1000);
    updateTimers();

    // Auto Table Refresh every 30 seconds for live updates
    function refreshTable() {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTbody = doc.querySelector('table tbody');
                const newStats = doc.querySelector('#statsContainer');
                if (newTbody) {
                    document.querySelector('table tbody').innerHTML = newTbody.innerHTML;
                    updateTimers(); // Apply JS to new elements
                    
                    if (window.currentPopupLeadId) {
                        let row = document.querySelector('tr[data-lead-id="' + window.currentPopupLeadId + '"]');
                        if (row) {
                            let dismissed = row.getAttribute('data-15m-dismissed');
                            let sent = row.getAttribute('data-proposal-sent');
                            if (dismissed === '1' || sent === '1') {
                                Swal.close();
                                delete window.currentPopupLeadId;
                            }
                        }
                    }
                }
                if (newStats) {
                    document.getElementById('statsContainer').innerHTML = newStats.innerHTML;
                }
            })
            .catch(err => console.error('Table refresh failed:', err));
    }

    function confirmDelete(event, form) {
        event.preventDefault();
        Swal.fire({
            title: 'Delete Lead?',
            text: "Are you sure you want to delete this transferred lead? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    setInterval(refreshTable, 30000);
</script>
@endpush
<script>
/*
 * The city list follows the state, same as on the calling list: only that
 * state's cities are fetched, rather than sending eleven hundred to every page.
 */
(function () {
    var state = document.getElementById('transferState');
    var city = document.getElementById('transferCity');
    if (!state || !city) return;

    function load() {
        var wanted = city.dataset.selected || '';

        if (!state.value) {
            city.innerHTML = '<option value="">Pick a state first</option>';
            return;
        }

        city.innerHTML = '<option value="">Loading…</option>';

        fetch('{{ url('admin/states') }}/' + state.value + '/cities', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.ok ? res.json() : { cities: [] }; })
        .then(function (data) {
            var html = '<option value="">Not set</option>';

            (data.cities || []).forEach(function (c) {
                html += '<option value="' + c.id + '"' + (String(c.id) === String(wanted) ? ' selected' : '') + '>'
                     + c.name.replace(/</g, '&lt;') + '</option>';
            });

            city.innerHTML = html;
        })
        .catch(function () { city.innerHTML = '<option value="">Could not load cities</option>'; });
    }

    state.addEventListener('change', function () { city.dataset.selected = ''; load(); });

    // Arriving from the calling list, the state is already chosen.
    if (state.value) load();
})();

@if(request('lead_id'))
    document.getElementById('transferLeadModal').classList.add('show');
@endif
</script>
@endsection

@extends('layouts.admin')

@section('content')
<div class="content-header" style="margin-bottom: 30px; border-bottom: 2px solid var(--border); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text-main); margin: 0; font-weight: 700;">Ticket #{{ $ticket->id }}</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 1rem;">{{ $ticket->subject }}</p>
    </div>
    <div>
        <a href="{{ route('support.dashboard') }}" class="btn btn-ghost" style="padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600;">&larr; Back to Dashboard</a>
    </div>
</div>

@if(session('success'))
<div style="background-color: rgba(16,185,129,0.1); border-left: 4px solid var(--success); color: var(--success); padding: 15px; margin-bottom: 20px; border-radius: 4px;">
    {{ session('success') }}
</div>
@endif

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Left Column: Deal & Ticket Info -->
    <div>
        <div class="card" style="margin-bottom: 20px; padding: 25px;">
            <h3 style="margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border); padding-bottom: 10px;">Ticket Details</h3>
            <p><strong>Description:</strong> <br> {{ $ticket->description ?: 'No description provided.' }}</p>
            
            <form action="{{ route('support.updateStatus', $ticket->id) }}" method="POST" style="margin-top: 20px; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; border: 1px solid var(--border);">
                @csrf
                @method('PUT')
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main);">Status</label>
                        <select name="status" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main);">
                            <option value="pending_verification" {{ $ticket->status == 'pending_verification' ? 'selected' : '' }}>Pending Verification</option>
                            <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved" {{ $ticket->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="refunded" {{ $ticket->status == 'refunded' ? 'selected' : '' }}>Refunded</option>
                            <option value="chargeback" {{ $ticket->status == 'chargeback' ? 'selected' : '' }}>Chargeback</option>
                        </select>
                    </div>
                    @if(auth()->user()->isSupportManager() || auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main);">Assign To</label>
                        <select name="assigned_to" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main);">
                            <option value="">Unassigned</option>
                            @foreach($supportAgents as $agent)
                                <option value="{{ $agent->id }}" {{ $ticket->assigned_to == $agent->id ? 'selected' : '' }}>{{ $agent->name }} ({{ $agent->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main);">Priority</label>
                        <select name="priority" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main);">
                            <option value="low" {{ $ticket->priority == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="normal" {{ $ticket->priority == 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="high" {{ $ticket->priority == 'high' ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ $ticket->priority == 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 15px; width: 100%; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer;">Update Ticket</button>
            </form>
        </div>

        @if($ticket->deal)
        <div class="card" style="padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--text-main);">Deal / Client Information</h3>
                @if(auth()->user()->isSupportManager() || auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                <button onclick="document.getElementById('editClientModal').style.display='flex'" class="btn btn-ghost" style="padding: 5px 10px; font-size: 0.85rem; border-radius: 6px;">
                    <i class="fas fa-edit"></i> Edit Info
                </button>
                @endif
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <p><strong>Business Name:</strong> {{ $ticket->deal->name }}</p>
                <p><strong>Owner Name:</strong> {{ $ticket->deal->business_owner_name ?? 'N/A' }}</p>
                <p><strong>Business Number:</strong> {{ $ticket->deal->business_number ?? 'N/A' }}</p>
                <p><strong>Personal Number:</strong> {{ $ticket->deal->personal_number ?? 'N/A' }}</p>
                <p><strong>Email:</strong> {{ $ticket->deal->business_email ?? 'N/A' }}</p>
                <p><strong>Deal Value:</strong> ${{ number_format($ticket->deal->value, 2) }}</p>
                <p><strong>Charged:</strong> ${{ number_format($ticket->deal->charge_payment, 2) }}</p>
                <p><strong>Remaining:</strong> ${{ number_format($ticket->deal->remaining_payment, 2) }}</p>
                <p><strong>Sales Agent:</strong> {{ $ticket->deal->closed_by ?? 'N/A' }}</p>
                <p><strong>Closer:</strong> {{ $ticket->deal->closer_by ?? 'N/A' }}</p>
            </div>
            
            <div style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 20px;">
                <h4 style="margin-top: 0; color: var(--text-main);">Payment Details</h4>
                @if(auth()->user()->isSupportManager() || auth()->user()->isAdmin())
                <p><strong>Card Number:</strong> {{ $ticket->deal->card_number ?? 'N/A' }}</p>
                <p><strong>Card Name:</strong> {{ $ticket->deal->card_name ?? 'N/A' }}</p>
                <p><strong>Expiry:</strong> {{ $ticket->deal->card_expiry ?? 'N/A' }} | <strong>CVV:</strong> {{ $ticket->deal->card_cvv ?? 'N/A' }}</p>
                <p><strong>Billing Address:</strong> {{ $ticket->deal->card_address ?? 'N/A' }}</p>
                @else
                <p style="color: var(--text-muted); font-style: italic;">Payment details are restricted to Support Managers.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Right Column: Notes -->
    <div>
        <div class="card" style="padding: 25px; height: 100%; display: flex; flex-direction: column;">
            <h3 style="margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border); padding-bottom: 10px;">Support Notes</h3>
            
            <div style="flex-grow: 1; max-height: 400px; overflow-y: auto; margin-bottom: 20px; padding-right: 10px;">
                @forelse($ticket->notes as $note)
                <div style="background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <strong style="color: var(--text-main); font-size: 0.9rem;">{{ $note->user->name ?? 'Unknown' }}</strong>
                        <span style="color: var(--text-muted); font-size: 0.8rem;">{{ $note->created_at->format('M d, g:i A') }}</span>
                    </div>
                    <p style="margin: 0; color: var(--text-main); font-size: 0.95rem; white-space: pre-wrap;">{{ $note->note }}</p>
                </div>
                @empty
                <p style="color: var(--text-muted); text-align: center;">No notes yet.</p>
                @endforelse
            </div>

            <form action="{{ route('support.addNote', $ticket->id) }}" method="POST" style="margin-top: auto;">
                @csrf
                <textarea name="note" required placeholder="Type your note here..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); min-height: 100px; margin-bottom: 10px; resize: vertical;"></textarea>
                <button type="submit" class="btn" style="width: 100%; padding: 10px; border-radius: 8px; background: var(--success); color: white; border: none; font-weight: 600; cursor: pointer;">Add Note</button>
            </form>
        </div>
    </div>
</div>

@if(auth()->user()->isSupportManager() || auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
@if($ticket->deal)
<div id="editClientModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
            <h3 style="margin: 0; color: var(--text-main);">Edit Client Details</h3>
            <button type="button" class="btn btn-ghost btn-icon" title="Close" onclick="document.getElementById('editClientModal').style.display='none'"><i class="fas fa-xmark"></i></button>
        </div>
        
        <form action="{{ route('support.updateClient', $ticket->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main); font-size: 0.9rem;">Client Status</label>
                <select name="client_status" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);">
                    <option value="">Select Status</option>
                    <option value="active" {{ $ticket->deal->client_status == 'active' ? 'selected' : '' }}>Active Customer</option>
                    <option value="on-hold" {{ $ticket->deal->client_status == 'on-hold' ? 'selected' : '' }}>On Hold</option>
                    <option value="charge-back" {{ $ticket->deal->client_status == 'charge-back' ? 'selected' : '' }}>Charge Back</option>
                    <option value="case-studies" {{ $ticket->deal->client_status == 'case-studies' ? 'selected' : '' }}>Case Study</option>
                    <option value="deactivated" {{ $ticket->deal->client_status == 'deactivated' ? 'selected' : '' }}>Deactivated</option>
                    <option value="win-back" {{ $ticket->deal->client_status == 'win-back' ? 'selected' : '' }}>Win Back</option>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main); font-size: 0.9rem;">Business Owner Name</label>
                <input type="text" name="business_owner_name" value="{{ $ticket->deal->business_owner_name }}" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main); font-size: 0.9rem;">Business Email</label>
                <input type="email" name="business_email" value="{{ $ticket->deal->business_email }}" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main); font-size: 0.9rem;">Business Number</label>
                <input type="text" name="business_number" value="{{ $ticket->deal->business_number }}" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main); font-size: 0.9rem;">Personal Number</label>
                <input type="text" name="personal_number" value="{{ $ticket->deal->personal_number }}" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);">
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('editClientModal').style.display='none'" class="btn btn-ghost" style="padding: 10px 20px; border-radius: 6px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px; border-radius: 6px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endif
@endif

@endsection

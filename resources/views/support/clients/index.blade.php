@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="content-header" style="margin-bottom: 30px; border-bottom: 2px solid var(--border); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text-main); margin: 0; font-weight: 700;">{{ $title }}</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 1rem;">View and manage {{ strtolower($title) }}.</p>
    </div>
</div>

<div class="card" style="margin-bottom: 2rem; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); padding: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <div class="card-title" style="display: flex; align-items: center; gap: 8px; font-size: 1.1rem; font-weight: 700; color: var(--text-main);">
            <i class="fas fa-list" style="color: var(--primary); font-size: 1.2rem;"></i> {{ $title }} List
        </div>
    </div>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Deal ID</th>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Client / Deal</th>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Deal Value</th>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Closer</th>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Status</th>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Support Ticket</th>
                    <th style="padding: 1.2rem 1rem; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                    <tr style="transition: background 0.2s ease;">
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle;">
                            <strong>#{{ $client->id }}</strong>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $client->created_at->format('M d, Y') }}</div>
                        </td>
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle;">
                            <div style="font-weight: 600;">{{ $client->business_owner_name ?: 'Unknown Client' }}</div>
                            <div style="font-size: 0.85rem; color: var(--primary);">{{ $client->name ?: 'No Deal Name' }}</div>
                        </td>
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle;">
                            <div style="font-weight: 600;">${{ number_format($client->value, 2) }}</div>
                        </td>
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle;">
                            <div style="font-size: 0.9rem;">{{ $client->closer_by ?: 'N/A' }}</div>
                        </td>
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle;">
                            @if($client->client_status)
                                <span class="badge" style="background: rgba(16,185,129,0.1); color: var(--success);">{{ ucwords(str_replace('-', ' ', $client->client_status)) }}</span>
                            @else
                                <span class="badge" style="background: rgba(100,116,139,0.1); color: var(--secondary);">N/A</span>
                            @endif
                        </td>
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle;">
                            @if($client->supportTicket)
                                <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--info);">Ticket #{{ $client->supportTicket->id }}</span>
                            @else
                                <span class="badge" style="background: rgba(100,116,139,0.1); color: var(--secondary);">No Ticket</span>
                            @endif
                        </td>
                        <td style="padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; text-align: right; display: flex; justify-content: flex-end; gap: 8px;">
                            <a href="{{ route('support.client.profile', $client->id) }}" class="btn btn-ghost" style="padding: 0.5rem 1rem; font-size: 0.85rem; border: 1px solid var(--border);" title="View Profile">
                                <i class="fas fa-user-circle"></i> Profile
                            </a>
                            <button onclick="openStatusModal({{ $client->id }}, '{{ $client->client_status }}')" class="btn btn-ghost" style="padding: 0.5rem 1rem; font-size: 0.85rem; border: 1px solid var(--border);" title="Edit Status">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            @if($client->supportTicket)
                                <a href="{{ route('support.show', $client->supportTicket->id) }}" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    Ticket <i class="fas fa-arrow-right"></i>
                                </a>
                            @else
                                <span class="btn btn-ghost" style="padding: 0.5rem 1rem; font-size: 0.85rem; cursor: not-allowed; opacity: 0.5;">
                                    No Ticket
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p style="font-size: 1.1rem; margin: 0;">No {{ strtolower($title) }} found at this time.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Status Modal -->
<div id="quickEditModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); padding: 30px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
            <h3 style="margin: 0; color: var(--text-main);">Update Client Status</h3>
            <button type="button" class="btn btn-ghost btn-icon" title="Close" onclick="document.getElementById('quickEditModal').style.display='none'"><i class="fas fa-xmark"></i></button>
        </div>
        
        <form id="quickEditForm" action="" method="POST">
            @csrf
            @method('PUT')
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-main); font-size: 0.9rem;">Client Status</label>
                <select name="client_status" id="quickEditStatusSelect" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);">
                    <option value="">Select Status</option>
                    <option value="active">Active Customer</option>
                    <option value="on-hold">On Hold</option>
                    <option value="charge-back">Charge Back</option>
                    <option value="case-studies">Case Study</option>
                    <option value="deactivated">Deactivated</option>
                    <option value="win-back">Win Back</option>
                    <option value="dispatched">Dispatched Sales</option>
                    <option value="pending">Pending Sales</option>
                    <option value="sales-on-hold">On Hold (Sales)</option>
                </select>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('quickEditModal').style.display='none'" class="btn btn-ghost" style="padding: 10px 20px; border-radius: 6px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px; border-radius: 6px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatusModal(dealId, currentStatus) {
    const form = document.getElementById('quickEditForm');
    form.action = `/admin/support/deals/${dealId}/status`; 
    document.getElementById('quickEditStatusSelect').value = currentStatus || '';
    document.getElementById('quickEditModal').style.display = 'flex';
}
</script>
@endsection

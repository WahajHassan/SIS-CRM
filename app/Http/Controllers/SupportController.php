<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\SupportTicket;
use App\Models\Deal;
use App\Models\TicketNote;
use App\Models\SystemLog;

class SupportController extends Controller
{
    public function dashboard()
    {
        $this->authorizeSection();
        
        $ticketsQuery = SupportTicket::with(['deal', 'company', 'assignedTo']);
        
        if (auth()->user()->isSupportAgent()) {
            $ticketsQuery->where('assigned_to', auth()->id());
        } elseif (auth()->user()->company_id && !auth()->user()->isSuperAdmin()) {
            $ticketsQuery->where('company_id', auth()->user()->company_id);
        }

        $tickets = $ticketsQuery->orderBy('created_at', 'desc')->get();
        
        $stats = [
            'total' => $tickets->count(),
            'pending' => $tickets->where('status', 'pending_verification')->count(),
            'in_progress' => $tickets->where('status', 'in_progress')->count(),
            'resolved' => $tickets->where('status', 'resolved')->count(),
            'refunded' => $tickets->where('status', 'refunded')->count(),
        ];

        return view('support.dashboard', compact('tickets', 'stats'));
    }

    public function show($id)
    {
        $this->authorizeSection();
        
        $ticket = SupportTicket::with(['deal', 'company', 'assignedTo', 'notes.user'])->findOrFail($id);
        
        // Basic auth check for support agents
        if (auth()->user()->isSupportAgent() && $ticket->assigned_to !== auth()->id()) {
            abort(403);
        }

        $supportAgents = \App\Models\User::whereIn('role', ['support_agent', 'support_manager'])->get();
        
        return view('support.show', compact('ticket', 'supportAgents'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending_verification,in_progress,resolved,refunded,chargeback',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $request->status;
        
        if ($request->has('priority')) {
            $ticket->priority = $request->priority;
        }

        if ($request->has('assigned_to') && auth()->user()->isSupportManager()) {
            $ticket->assigned_to = $request->assigned_to;
        }

        $ticket->save();

        SystemLog::log('Support Ticket Updated', "Updated ticket #{$ticket->id} status to {$ticket->status}");

        return redirect()->back()->with('success', 'Ticket updated successfully.');
    }

    public function addNote(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string'
        ]);

        TicketNote::create([
            'support_ticket_id' => $id,
            'user_id' => auth()->id(),
            'note' => $request->note
        ]);

        return redirect()->back()->with('success', 'Note added successfully.');
    }

    public function updateClient(Request $request, $id)
    {
        $this->authorizeSection();
        
        $ticket = SupportTicket::with('deal')->findOrFail($id);
        
        $request->validate([
            'business_owner_name' => 'nullable|string',
            'business_number' => 'nullable|string',
            'personal_number' => 'nullable|string',
            'business_email' => 'nullable|email',
            'client_status' => 'nullable|string',
        ]);
        
        if ($ticket->deal) {
            $ticket->deal->update($request->only([
                'business_owner_name', 'business_number', 'personal_number', 'business_email', 'client_status'
            ]));
        }
        
        SystemLog::log('Client Details Updated', "Updated client details for ticket #{$ticket->id}");

        return redirect()->back()->with('success', 'Client details updated successfully.');
    }

    public function clients($type)
    {
        $this->authorizeSection();
        
        $validTypes = ['active', 'on-hold', 'charge-back', 'case-studies', 'deactivated', 'win-back', 'dispatched', 'pending', 'sales-on-hold'];
        if (!in_array($type, $validTypes)) {
            abort(404);
        }

        $typeLabels = [
            'active' => 'Active Customers',
            'on-hold' => 'On Hold Clients',
            'charge-back' => 'Charge Back Clients',
            'case-studies' => 'Case Studies',
            'deactivated' => 'Deactivated Customers',
            'win-back' => 'Win Back Customers',
            'dispatched' => 'Dispatched Sales',
            'pending' => 'Pending Sales',
            'sales-on-hold' => 'On Hold Clients (Sales)'
        ];

        $title = $typeLabels[$type];

        // Fetch Deals directly
        $clientsQuery = Deal::with(['company', 'supportTicket']);
        
        $clientsQuery->where('client_status', $type);

        $clients = $clientsQuery->orderBy('created_at', 'desc')->get();

        return view('support.clients.index', compact('title', 'type', 'clients'));
    }

    public function updateDealStatus(Request $request, $id)
    {
        $this->authorizeSection();

        $request->validate([
            'client_status' => 'nullable|string'
        ]);

        $deal = Deal::findOrFail($id);
        $deal->update([
            'client_status' => $request->client_status
        ]);

        SystemLog::log('Client Status Updated', "Updated status to {$request->client_status} for deal #{$deal->id}");

        return redirect()->back()->with('success', 'Client status updated successfully.');
    }

    public function clientProfile($id)
    {
        $this->authorizeSection();
        $client = Deal::with(['company', 'supportTicket'])->findOrFail($id);
        return view('support.clients.profile', compact('client'));
    }

    public function sheetView()
    {
        $this->authorizeSection();
        // Load all deals/clients for the sheet
        $clients = Deal::orderBy('created_at', 'desc')->get();
        return view('support.clients.sheet', compact('clients'));
    }

    public function saveSheetData(Request $request)
    {
        $this->authorizeSection();
        
        $data = $request->input('data');
        if (!is_array($data)) {
            return response()->json(['success' => false, 'message' => 'Invalid data format.']);
        }

        foreach ($data as $row) {
            if (isset($row['id']) && $row['id']) {
                $deal = Deal::find($row['id']);
                if ($deal) {
                    $deal->update([
                        'name' => $row['name'] ?? $deal->name,
                        'business_owner_name' => $row['business_owner_name'] ?? $deal->business_owner_name,
                        'business_email' => $row['business_email'] ?? $deal->business_email,
                        'business_number' => $row['business_number'] ?? $deal->business_number,
                        'client_status' => $row['client_status'] ?? $deal->client_status,
                        'status' => $row['status'] ?? $deal->status,
                        'value' => $row['value'] ?? $deal->value,
                    ]);
                }
            }
        }

        SystemLog::log('Google Sheet Data Updated', 'Bulk updated client details via Google Sheet Interface');
        return response()->json(['success' => true]);
    }
}

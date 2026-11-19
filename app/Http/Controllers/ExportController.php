<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\SystemLog;
use App\Models\TransferredLead;
use App\Services\Spreadsheet;
use Illuminate\Http\Request;

/**
 * Getting the work back out.
 *
 * Each export runs the same query the screen it sits on has just run, filters
 * and all — so what downloads is what the person is looking at, not a different
 * and larger thing. An export that quietly ignores the filters is worse than
 * none, because the number at the bottom of the spreadsheet disagrees with the
 * number on the screen and nobody can tell which is wrong.
 *
 * The leads file is deliberately the same shape the importer accepts, so a
 * floor can export, fix a column in Excel, and put it back.
 */
class ExportController extends Controller
{
    /* ---- leads --------------------------------------------------------------- */

    public function leads(Request $request)
    {
        abort_unless(auth()->user()->canAccess('leads'), 403, 'Unauthorized action.');

        $query = Lead::with(['category', 'state', 'city', 'creator'])->latest();

        foreach (['business_category_id', 'usa_state_id', 'city_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        SystemLog::log('Exported Leads', 'Downloaded the leads list' . $this->saying($request));

        // These headings are the importer's vocabulary too: export, edit, put back.
        return Spreadsheet::stream(
            Spreadsheet::name('leads', $request),
            ['Name', 'Business', 'Phone', 'Email', 'Trade', 'State', 'City',
             'Address', 'Website', 'Source', 'Status', 'Notes', 'Added by', 'Added on'],
            $query,
            fn (Lead $lead) => [
                $lead->name,
                $lead->business_name,
                $lead->phone,
                $lead->email,
                $lead->category?->name,
                $lead->state?->name,
                $lead->city?->name,
                $lead->address,
                $lead->website,
                $lead->source,
                $lead->status,
                $lead->notes,
                $lead->creator?->display_name,
                $lead->created_at?->format('Y-m-d H:i'),
            ]
        );
    }

    /* ---- deals --------------------------------------------------------------- */

    public function deals(Request $request)
    {
        abort_unless(auth()->user()->canAccess('deals'), 403, 'Unauthorized action.');
        abort_if(auth()->user()->isAgent(), 403, 'Unauthorized action.');

        $query = Deal::with(['transfer', 'agent', 'closerUser'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_owner_name', 'like', "%{$search}%")
                  ->orWhere('business_number', 'like', "%{$search}%")
                  ->orWhere('business_email', 'like', "%{$search}%");
            });
        }

        SystemLog::log('Exported Deals', 'Downloaded the deals list' . $this->saying($request));

        // No card columns. They are encrypted at rest precisely so they do not
        // leave the system, and a spreadsheet in somebody's downloads folder is
        // the opposite of that.
        return Spreadsheet::stream(
            Spreadsheet::name('deals', $request),
            ['Deal', 'Value', 'Charged', 'Remaining', 'Status', 'Agent', 'Closer',
             'Business owner', 'Business phone', 'Business email', 'Address', 'Created'],
            $query,
            fn (Deal $deal) => [
                $deal->name,
                $deal->value,
                $deal->charge_payment,
                $deal->remaining_payment,
                $deal->status,
                $deal->closed_by ?: $deal->agent?->display_name,
                $deal->closer_by ?: $deal->closerUser?->display_name,
                $deal->business_owner_name,
                $deal->business_number,
                $deal->business_email,
                $deal->business_address,
                $deal->created_at?->format('Y-m-d H:i'),
            ]
        );
    }

    /* ---- handovers ----------------------------------------------------------- */

    public function transfers(Request $request)
    {
        abort_unless(auth()->user()->canAccess('transferred_leads'), 403, 'Unauthorized action.');

        $query = TransferredLead::with(['agent', 'closer', 'category', 'state', 'city', 'deal'])->latest();

        foreach (['stage', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('filter_agent_id')) {
            $query->where('agent_id', $request->input('filter_agent_id'));
        }

        if ($request->filled('month')) {
            try {
                $month = \Carbon\Carbon::parse($request->input('month'));
                $query->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month);
            } catch (\Throwable $e) {
                // An unreadable month filter narrows nothing rather than failing.
            }
        }

        SystemLog::log('Exported Handovers', 'Downloaded the transferred leads list' . $this->saying($request));

        return Spreadsheet::stream(
            Spreadsheet::name('handovers', $request),
            ['Business', 'Contact', 'Phone', 'Email', 'Trade', 'Where', 'Agent', 'Closer',
             'Stage', 'Scheduled', 'Picked up', 'Closed', 'Lost reason',
             'Deal', 'Worth', 'Deal status'],
            $query,
            fn (TransferredLead $t) => [
                $t->business_name,
                $t->customer_name,
                $t->phone,
                $t->email,
                $t->category?->name,
                $t->place(),
                $t->agent?->display_name,
                $t->closer?->display_name,
                $t->stageLabel(),
                $t->scheduled_at?->format('Y-m-d H:i'),
                $t->claimed_at?->format('Y-m-d H:i'),
                $t->closed_at?->format('Y-m-d H:i'),
                $t->lost_reason,
                $t->deal ? '#' . $t->deal->id : '',
                $t->deal?->value,
                $t->deal?->status,
            ]
        );
    }

    // ---- the plumbing -------------------------------------------------------

    /** What was narrowed, for the log. */
    private function saying(Request $request): string
    {
        $bits = collect(['search', 'status', 'stage', 'month'])
            ->filter(fn ($k) => $request->filled($k))
            ->map(fn ($k) => $k . '=' . $request->input($k));

        return $bits->isEmpty() ? ' (everything)' : ' (' . $bits->implode(', ') . ')';
    }
}

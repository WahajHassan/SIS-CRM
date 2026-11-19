<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadStageLog;
use App\Models\SystemLog;
use App\Models\TransferredLead;
use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The road from a call to a closed deal.
 *
 * An agent hands a lead over and it lands in the pool. A closer claims it, works
 * it forward, and either wins it — which creates the deal, pre-filled from
 * everything already known — or loses it with a reason. Every move is recorded
 * against the transfer, so "what happened to that lead" is a question the system
 * answers rather than one somebody has to remember.
 *
 * One place owns stage changes. Screens ask this; they do not set the column
 * themselves, because a stage change is never only a stage change — it decides
 * who owns the lead, when the clock started, and what gets written down.
 */
class LeadPipeline
{
    /** Which stages a transfer may move to from where it is. */
    public const ALLOWED = [
        'pool' => ['claimed'],
        'claimed' => ['contacted', 'appointment', 'proposal', 'won', 'lost', 'no_show', 'pool'],
        'contacted' => ['appointment', 'proposal', 'won', 'lost', 'no_show', 'pool'],
        'appointment' => ['contacted', 'proposal', 'won', 'lost', 'no_show', 'pool'],
        'proposal' => ['appointment', 'won', 'lost', 'no_show', 'pool'],
        'no_show' => ['contacted', 'appointment', 'proposal', 'won', 'lost', 'pool'],
        'won' => ['lost'],
        'lost' => ['claimed', 'contacted', 'appointment', 'proposal', 'won'],
    ];

    /**
     * Hand a lead over. It goes into the pool unless a closer is named.
     */
    public function transfer(array $data, User $agent, ?Lead $lead = null): TransferredLead
    {
        $data['agent_id'] = $data['agent_id'] ?? $agent->id;
        $data['lead_id'] = $lead?->id ?? ($data['lead_id'] ?? null);
        $data['stage'] = !empty($data['closer_id']) ? 'claimed' : 'pool';
        $data['claimed_at'] = !empty($data['closer_id']) ? now() : null;

        // What the agent already knows travels with it, so the closer does not
        // have to go and look the business up again.
        if ($lead) {
            $data['business_name'] = $data['business_name'] ?? $lead->business_name;
            $data['business_category_id'] = $data['business_category_id'] ?? $lead->business_category_id;
            $data['usa_state_id'] = $data['usa_state_id'] ?? $lead->usa_state_id;
            $data['city_id'] = $data['city_id'] ?? $lead->city_id;
            $data['address'] = $data['address'] ?? $lead->address;
        }

        $transfer = TransferredLead::create($data);

        $this->log($transfer, $agent, null, $transfer->stage, $lead ? 'Handed over from the calling list' : null);
        $this->tellTheClosers($transfer, $agent);
        $this->countOnTheBoard($agent, 'number_of_transferred', $transfer->created_at);

        return $transfer;
    }

    /**
     * A closer takes one out of the pool.
     *
     * First come, first served, and the check is a locked read rather than a
     * look-then-write: two closers pressing Claim at the same moment must not
     * both get it.
     */
    public function claim(TransferredLead $transfer, User $closer): bool
    {
        $taken = DB::transaction(function () use ($transfer, $closer) {
            $fresh = TransferredLead::withoutGlobalScope('company')
                ->lockForUpdate()
                ->find($transfer->id);

            if (!$fresh || $fresh->closer_id) {
                return false;
            }

            $fresh->update([
                'closer_id' => $closer->id,
                'stage' => 'claimed',
                'claimed_at' => now(),
                'status' => 'Active',
            ]);

            return true;
        });

        if (!$taken) {
            return false;
        }

        $transfer->refresh();
        $this->log($transfer, $closer, 'pool', 'claimed');
        $this->tellTheAgent($transfer, "{$closer->display_name} picked up {$transfer->displayName()}");

        return true;
    }

    /** Put one back for somebody else to take. */
    public function release(TransferredLead $transfer, User $actor, ?string $reason = null): void
    {
        $from = $transfer->stage;

        $transfer->update([
            'closer_id' => null,
            'stage' => 'pool',
            'claimed_at' => null,
        ]);

        $this->log($transfer, $actor, $from, 'pool', $reason ?: 'Put back in the pool');
    }

    /**
     * Move one along.
     *
     * Winning is not done here — that goes through convert(), because a win
     * without the deal behind it is only half the story.
     */
    public function moveTo(TransferredLead $transfer, string $stage, User $actor, ?string $note = null): void
    {
        if (!array_key_exists($stage, TransferredLead::STAGES)) {
            return;
        }

        $from = $transfer->stage;

        if ($from === $stage) {
            return;
        }

        $changes = ['stage' => $stage];

        if ($stage === 'lost') {
            $changes['lost_reason'] = $note;
            $changes['closed_at'] = now();
            $changes['status'] = 'Missed';
        }

        if ($stage === 'won') {
            $changes['closed_at'] = now();
            $changes['is_converted'] = true;
            $changes['status'] = 'Completed';
        }

        if ($stage === 'proposal') {
            $changes['is_proposal_sent'] = true;
        }

        // Somebody has to own it from the moment it is being worked on.
        if ($stage !== 'pool' && !$transfer->closer_id && $actor->isCloser()) {
            $changes['closer_id'] = $actor->id;
            $changes['claimed_at'] = $transfer->claimed_at ?: now();
        }

        $transfer->update($changes);
        $this->log($transfer, $actor, $from, $stage, $note);

        if (in_array($stage, ['won', 'lost'], true)) {
            $this->tellTheAgent(
                $transfer,
                $stage === 'won'
                    ? "{$transfer->displayName()} was won by {$actor->display_name}"
                    : "{$transfer->displayName()} was marked lost" . ($note ? ": {$note}" : '')
            );
        }
    }

    /**
     * Win it: the deal is created from what the transfer already holds.
     *
     * Everything typed once on the way in — the business, the owner, the
     * contacts, the address — is carried over rather than asked for again, and
     * the deal and the transfer each point at the other.
     */
    public function convert(TransferredLead $transfer, User $closer, array $extra = []): Deal
    {
        if ($transfer->deal) {
            return $transfer->deal;
        }

        $deal = Deal::create(array_merge([
            'company_id' => $transfer->company_id,
            'transferred_lead_id' => $transfer->id,
            'lead_id' => $transfer->lead_id,
            'agent_id' => $transfer->agent_id,
            'closer_user_id' => $closer->id,

            'name' => $transfer->displayName(),
            'value' => 0,
            'status' => 'PENDING',

            // The names stay, because the scoreboard and the history still read
            // them; the ids above are what the chain is followed by.
            'closed_by' => $transfer->agent?->performer?->name ?? $transfer->agent?->name,
            'closer_by' => $closer->performer?->name ?? $closer->name,

            'business_owner_name' => $transfer->owner_name ?: $transfer->customer_name,
            'business_number' => $transfer->phone,
            'business_email' => $transfer->email,
            'personal_number' => $transfer->personal_number,
            'personal_email' => $transfer->personal_email,
            'business_address' => $transfer->address,
            'areas_covered' => $transfer->place(),
            'services_offered' => $transfer->category?->name,
            'yelp_link' => $transfer->yelp_link,
            'google_link' => $transfer->google_link,
        ], $extra));

        $transfer->update(['deal_id' => $deal->id]);
        $this->moveTo($transfer, 'won', $closer, 'Converted into a deal');

        // The scoreboard counts a sale for both of them, the way it shows one.
        $this->countOnTheBoard($closer, 'number_of_sales', $deal->created_at);

        if ($transfer->agent && $transfer->agent->id !== $closer->id) {
            $this->countOnTheBoard($transfer->agent, 'number_of_sales', $deal->created_at);
        }

        SystemLog::log('Converted Lead', "{$transfer->displayName()} became deal #{$deal->id}");

        return $deal;
    }

    /** What the pool and each closer's desk look like right now. */
    public function board(?User $viewer = null): array
    {
        $viewer = $viewer ?: auth()->user();

        $pool = TransferredLead::with(['agent', 'category', 'state', 'city'])
            ->inPool()
            ->orderBy('scheduled_at')
            ->get();

        $mine = $viewer
            ? TransferredLead::with(['agent', 'category', 'state', 'city'])
                ->claimedBy($viewer->id)
                ->orderBy('scheduled_at')
                ->get()
            : collect();

        return [
            'pool' => $pool,
            'mine' => $mine,
            'waiting' => $pool->count(),
        ];
    }

    // ---- the plumbing -------------------------------------------------------

    /**
     * Keep the scoreboard's own counters up with the work.
     *
     * The board is built from sales performers, and a login points at one. The
     * month's handovers and sales were counted in the screen that happened to
     * create the row, so anything that came in another way — a lead converted
     * from the pipeline, for instance — never reached the board. Counting it
     * here means the board follows the work rather than the screen.
     */
    private function countOnTheBoard(?User $user, string $field, $when): void
    {
        $performer = $user?->performer;

        if (!$performer) {
            return;                                 // not somebody the board shows
        }

        $when = $when ? Carbon::parse($when) : now();

        $stat = \App\Models\MonthlyPerformerStat::firstOrCreate(
            [
                'sales_performer_id' => $performer->id,
                'month' => $when->month,
                'year' => $when->year,
            ],
            [
                'company_id' => $performer->company_id,
                'goal' => $performer->goal ?? 0,
                'number_of_transferred' => 0,
                'number_of_sales' => 0,
            ]
        );

        $stat->increment($field);
        $performer->increment($field);
    }

    private function log(TransferredLead $transfer, ?User $actor, ?string $from, string $to, ?string $note = null): void
    {
        LeadStageLog::create([
            'company_id' => $transfer->company_id,
            'transferred_lead_id' => $transfer->id,
            'user_id' => $actor?->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'note' => $note,
        ]);
    }

    /** A lead nobody knows about is a lead nobody claims. */
    private function tellTheClosers(TransferredLead $transfer, User $agent): void
    {
        if ($transfer->closer_id) {
            $closer = User::find($transfer->closer_id);

            if ($closer) {
                $closer->notify(new SystemAnnouncement([
                    'title' => 'A lead for you',
                    'message' => "{$agent->display_name} handed you {$transfer->displayName()}.",
                    'icon' => 'fas fa-user-tie',
                    'type' => 'info',
                    'url' => route('admin.transferred_leads'),
                ]));
            }

            return;
        }

        $closers = User::where('role', 'closer')
            ->where('company_id', $transfer->company_id)
            ->get();

        foreach ($closers as $closer) {
            $closer->notify(new SystemAnnouncement([
                'title' => 'New lead in the pool',
                'message' => "{$transfer->displayName()} — {$agent->display_name} just handed it over.",
                'icon' => 'fas fa-inbox',
                'type' => 'info',
                'url' => route('admin.transferred_leads'),
            ]));
        }
    }

    private function tellTheAgent(TransferredLead $transfer, string $message): void
    {
        $agent = $transfer->agent;

        if (!$agent) {
            return;
        }

        $agent->notify(new SystemAnnouncement([
            'title' => 'Your lead',
            'message' => $message,
            'icon' => 'fas fa-arrow-right-arrow-left',
            'type' => 'info',
            'url' => route('admin.transferred_leads'),
        ]));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

/**
 * A lead handed from the agent who found it to whoever closes it.
 *
 * It arrives in the pool, a closer claims it, and from there it moves forward
 * until it is won or lost. Every move is recorded, and a won one carries the
 * deal it became — so the chain from the first call to the money is one the
 * system can answer rather than one somebody has to remember.
 */
class TransferredLead extends Model
{
    use BelongsToCompany, SoftDeletes;

    /** Where a transfer can be, in the order it travels. */
    public const STAGES = [
        'pool' => 'In the pool',
        'claimed' => 'Claimed',
        'contacted' => 'Contacted',
        'appointment' => 'Appointment set',
        'proposal' => 'Proposal sent',
        'won' => 'Won',
        'lost' => 'Lost',
        'no_show' => 'No show',
    ];

    /** Stages that mean nobody is working on it any more. */
    public const CLOSED_STAGES = ['won', 'lost'];

    protected $fillable = [
        'company_id',
        'lead_id',
        'deal_id',
        'agent_id',
        'closer_id',
        'business_name',
        'business_category_id',
        'usa_state_id',
        'city_id',
        'stage',
        'claimed_at',
        'closed_at',
        'lost_reason',
        'customer_name',
        'phone',
        'email',
        'notes',
        'scheduled_at',
        'is_proposal_sent',
        'is_converted',
        'reminders_sent',
        'status',
        'owner_name',
        'personal_number',
        'personal_email',
        'address',
        'yelp_link',
        'google_link',
        'card_information'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'claimed_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_proposal_sent' => 'boolean',
        'is_converted' => 'boolean',
        'reminders_sent' => 'array',
        // Encrypted at rest for the same reason as the card columns on Deal.
        'card_information' => 'encrypted:array'
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id')->withTrashed();
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closer_id')->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

    public function state()
    {
        return $this->belongsTo(UsaState::class, 'usa_state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function stageLogs()
    {
        return $this->hasMany(LeadStageLog::class)->latest();
    }

    /** Waiting for somebody to pick it up. */
    public function scopeInPool($query)
    {
        return $query->where('stage', 'pool')->whereNull('closer_id');
    }

    /** What this closer is working on. */
    public function scopeClaimedBy($query, $userId)
    {
        return $query->where('closer_id', $userId)->whereNotIn('stage', self::CLOSED_STAGES);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('stage', self::CLOSED_STAGES);
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, self::CLOSED_STAGES, true);
    }

    public function displayName(): string
    {
        return $this->business_name ?: $this->customer_name;
    }

    public function place(): string
    {
        return collect([$this->city?->name, $this->state?->name])->filter()->implode(', ');
    }

    /** How long it sat in the pool before anybody took it. */
    public function waitedMinutes(): ?int
    {
        return $this->claimed_at ? (int) $this->created_at->diffInMinutes($this->claimed_at) : null;
    }
}

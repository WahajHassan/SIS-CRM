<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One move of a transfer through the pipeline, and who made it.
 *
 * Written by LeadPipeline rather than by any screen, so the history is the same
 * whether a stage changed from the closer's queue, from an admin correcting it,
 * or from a deal being created.
 */
class LeadStageLog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'transferred_lead_id', 'user_id', 'from_stage', 'to_stage', 'note'];

    public function transfer()
    {
        return $this->belongsTo(TransferredLead::class, 'transferred_lead_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** "Sana claimed it" — one line, for the history list. */
    public function line(): string
    {
        $who = $this->user?->display_name ?: $this->user?->name ?: 'The system';
        $to = TransferredLead::STAGES[$this->to_stage] ?? $this->to_stage;

        return $this->from_stage
            ? "{$who} moved it to {$to}"
            : "{$who} put it in {$to}";
    }
}

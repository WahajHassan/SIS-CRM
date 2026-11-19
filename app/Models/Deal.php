<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class Deal extends Model
{
    use HasFactory, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'name',
        'value',
        'charge_payment',
        'remaining_payment',
        'status',
        'closed_by',
        'closer_by',
        'time_ago',
        'yelp_link',
        'google_link',
        'business_owner_name',
        'business_number',
        'personal_number',
        'business_email',
        'personal_email',
        'business_address',
        'areas_covered',
        'services_offered',
        'business_hours',
        'card_number',
        'card_name',
        'card_expiry',
        'card_cvv',
        'card_address',
        'docusign_file',
        'proposal_pdf',
        'payment_auth_file',
        'company_id',
        // The chain back to where this deal came from. The closed_by / closer_by
        // names above stay for everything already reading them.
        'transferred_lead_id',
        'lead_id',
        'agent_id',
        'closer_user_id',
        'client_status',
    ];

    /**
     * Card details are encrypted at rest, so the database and any backup of it no
     * longer carry readable card numbers. Reading and writing them is unchanged for
     * the rest of the application.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'card_number' => 'encrypted',
        'card_name' => 'encrypted',
        'card_expiry' => 'encrypted',
        'card_cvv' => 'encrypted',
        'card_address' => 'encrypted',
    ];

    public function supportTicket()
    {
        return $this->hasOne(SupportTicket::class);
    }

    /** The handover this deal came out of, if it came out of one. */
    public function transfer()
    {
        return $this->belongsTo(TransferredLead::class, 'transferred_lead_id')->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id')->withTrashed();
    }

    public function closerUser()
    {
        return $this->belongsTo(User::class, 'closer_user_id')->withTrashed();
    }
}

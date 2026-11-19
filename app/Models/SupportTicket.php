<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\BelongsToCompany;

class SupportTicket extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'deal_id',
        'assigned_to',
        'subject',
        'description',
        'status',
        'priority',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function notes()
    {
        return $this->hasMany(TicketNote::class);
    }
}

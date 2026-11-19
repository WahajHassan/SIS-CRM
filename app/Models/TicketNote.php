<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\BelongsToCompany;

class TicketNote extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'support_ticket_id',
        'user_id',
        'note',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

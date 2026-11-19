<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class Email extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'deal_id',
        'lead_id',
        'message_id',
        'direction',
        'from_address',
        'to_address',
        'subject',
        'body_html',
        'status',
    ];
}

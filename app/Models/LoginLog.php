<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class LoginLog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'ip_address', 'user_agent'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

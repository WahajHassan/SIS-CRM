<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class Conversation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['type', 'name', 'created_by', 'company_id'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_users')
                    ->withPivot('last_read_at')
                    ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}

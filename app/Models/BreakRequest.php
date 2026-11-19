<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class BreakRequest extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'user_id',
        'company_id',
        'type',
        'status', // pending, approved, declined
        'processed_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }
}

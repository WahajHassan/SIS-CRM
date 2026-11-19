<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * What one person is allowed of one leave type in one year, and what they have
 * spent of it.
 */
class LeaveBalance extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'leave_type_id', 'year', 'allotted', 'used', 'note'];

    protected $casts = [
        'year' => 'integer',
        'allotted' => 'float',
        'used' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function type()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function remaining(): float
    {
        return max(0, $this->allotted - $this->used);
    }

    /** Unlimited types carry no allowance to run out of. */
    public function isUnlimited(): bool
    {
        return $this->allotted <= 0;
    }
}

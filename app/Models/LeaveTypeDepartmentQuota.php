<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A department that is allowed more (or less) of a leave type than the default. */
class LeaveTypeDepartmentQuota extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'leave_type_id', 'department_id', 'yearly_quota', 'monthly_limit'];

    protected $casts = [
        'yearly_quota' => 'integer',
        'monthly_limit' => 'integer',
    ];

    public function type()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

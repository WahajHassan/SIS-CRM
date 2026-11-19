<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A part of the company — Sales, Tech, and whatever is added later.
 *
 * The lead is who approves this department's leave at the first step, which is
 * why it lives here rather than on each employee.
 */
class Department extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'code', 'lead_user_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function lead()
    {
        return $this->belongsTo(User::class, 'lead_user_id')->withTrashed();
    }

    public function profiles()
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    /** The employees in this department, through their personnel files. */
    public function employees()
    {
        return $this->hasManyThrough(User::class, EmployeeProfile::class, 'department_id', 'id', 'id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

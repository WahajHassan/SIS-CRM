<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class EmployeeSpotlight extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'role', 'achievement', 'avatar_initials', 'is_active', 'company_id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

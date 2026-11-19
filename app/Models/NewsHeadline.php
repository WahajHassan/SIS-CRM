<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class NewsHeadline extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'headline',
        'is_active',
        'company_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

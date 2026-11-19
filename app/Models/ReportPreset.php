<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A saved set of report filters and ticked columns, named by whoever made it. */
class ReportPreset extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'name', 'report_type', 'filters', 'columns'];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class MonthlyPerformerStat extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'sales_performer_id',
        'month',
        'year',
        'goal',
        'number_of_transferred',
        'number_of_sales',
        'company_id',
    ];

    public function performer()
    {
        return $this->belongsTo(SalesPerformer::class, 'sales_performer_id')->withTrashed();
    }
}

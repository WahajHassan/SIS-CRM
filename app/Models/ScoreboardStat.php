<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class ScoreboardStat extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'monthly_target',
        'total_revenue',
        'deals_closed',
        'avg_deal_size',
        'active_leads',
        'revenue_trend',
        'deals_trend',
        'avg_deal_trend',
        'leads_trend',
        'won_count',
        'lost_count',
        'pipeline_probability',
        'pipeline_proposal',
        'conversion_rate',
        'achieved_prospects',
        'live_update',
        'company_id',
    ];
}

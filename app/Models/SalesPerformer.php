<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class SalesPerformer extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'name',
        'role',
        'is_closer',
        'revenue',
        'goal',
        'conversion_rate',
        'avatar_initials',
        'number_of_transferred',
        'number_of_sales',
        'sudo_name',
        'id_card_number',
        'employee_id_number',
        'contract_type',
        'contract_file',
        'id_card_front',
        'id_card_back',
        'police_clearance_certificate',
        'company_id',
        'status'
    ];

    /**
     * The login behind this performer, if there is one.
     *
     * Not every performer has one — somebody can be on the scoreboard before
     * they are given an account, and some never get one. The face and the
     * verification tick live on the user, so the places that draw a performer
     * ask through here and show a plain name when it comes back empty.
     */
    public function user()
    {
        return $this->hasOne(User::class, 'sales_performer_id');
    }

    public function monthlyStats()
    {
        return $this->hasMany(MonthlyPerformerStat::class, 'sales_performer_id');
    }

    public function currentStat()
    {
        return $this->monthlyStats()
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'closed_by', 'name');
    }

    // Helper to get effective goal (from current stat or fallback to flat field)
    public function getEffectiveGoalAttribute()
    {
        $stat = $this->currentStat();
        return $stat ? $stat->goal : $this->goal;
    }

    public function getEffectiveTransferredAttribute()
    {
        $stat = $this->currentStat();
        return $stat ? $stat->number_of_transferred : $this->number_of_transferred;
    }

    public function getEffectiveSalesAttribute()
    {
        $deals_count = Deal::where('status', 'WON')
            ->where(function($query) {
                $query->where('closed_by', $this->name)
                      ->orWhere('closer_by', $this->name);
            })
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $stat = $this->currentStat();
        $manual_sales = $stat ? $stat->number_of_sales : $this->number_of_sales;

        return max($deals_count, $manual_sales);
    }

    public function getRevenueAttribute()
    {
        $deals_revenue = Deal::where('status', 'WON')
            ->where(function($query) {
                $query->where('closed_by', $this->name)
                      ->orWhere('closer_by', $this->name);
            })
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('value');

        $manual_revenue = $this->attributes['revenue'] ?? 0;

        return max($deals_revenue, $manual_revenue);
    }

    public function getDisplayNameAttribute()
    {
        return $this->sudo_name ?: $this->name;
    }

    protected $casts = [
        'is_closer' => 'boolean',
    ];
}

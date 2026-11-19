<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A business an agent got onto Viewrec for free.
 *
 * Five a day is what the floor is asked for, so the only numbers that matter
 * here are "how many today" and "how many this month" — both of which are
 * counted off these rows rather than kept anywhere separately.
 */
class FreeSignup extends Model
{
    use BelongsToCompany;

    /** Where a signup can get to, and what each one is called on screen. */
    public const STATUSES = [
        'new' => 'New',
        'verified' => 'Verified',
        'active' => 'Active',
        'dropped' => 'Dropped',
    ];

    protected $fillable = [
        'company_id', 'created_by', 'name', 'business_name', 'email', 'phone',
        'website', 'business_category_id', 'usa_state_id', 'city_id', 'status', 'notes',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

    public function state()
    {
        return $this->belongsTo(UsaState::class, 'usa_state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /*
     * Named away from on() and by(): Eloquent already has a static on(), which
     * picks a database connection, so FreeSignup::on('2026-10-07') asked for a
     * connection by that name rather than for that day's rows.
     */
    public function scopeOnDay($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }

    public function scopeTakenBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeInMonth($query, \Carbon\Carbon $month)
    {
        return $query->whereBetween('created_at', [
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        ]);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function place(): string
    {
        return collect([$this->city?->name, $this->state?->name])->filter()->implode(', ');
    }

    /** How many this person has done today, against what is asked of them. */
    public static function todayFor(?User $user): array
    {
        $target = (int) (HrSetting::current()->free_signups_per_day ?: 5);

        if (!$user) {
            return ['done' => 0, 'target' => $target, 'left' => $target];
        }

        $done = static::takenBy($user->id)->onDay(now()->toDateString())->count();

        return [
            'done' => $done,
            'target' => $target,
            'left' => max(0, $target - $done),
        ];
    }
}

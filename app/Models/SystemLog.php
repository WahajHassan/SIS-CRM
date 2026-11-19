<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class SystemLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id',
        'company_id',
        'action_type',
        'description',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * Write down that something happened.
     *
     * $about is for the things that happen while nobody is signed in — a
     * password reset being asked for, and the reset itself. Those used to write
     * nothing at all, because this only recorded events with a logged-in user
     * behind them, which meant the single most security-relevant action in the
     * whole CRM left no trace. They are the events most worth being able to ask
     * about later.
     */
    public static function log($actionType, $description, ?User $about = null)
    {
        $who = auth()->check() ? auth()->user() : $about;

        if (!$who) {
            return;
        }

        self::create([
            'user_id' => $who->id,
            'company_id' => $who->company_id,   // an owner may have none
            'action_type' => $actionType,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}

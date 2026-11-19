<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One person looking at another person's CRM, because they said yes.
 *
 * A huddle starts as a request and goes nowhere until the host answers it. It
 * carries two separate permissions, not one: the host always agrees to being
 * watched, and separately decides whether the guest may click. Either can be
 * taken back by ending the huddle, which is why nothing here is ever deleted —
 * who asked, who allowed what, and when it stopped is the record of it.
 */
class Huddle extends Model
{
    use BelongsToCompany;

    public const PENDING  = 'pending';
    public const ACTIVE   = 'active';
    public const DECLINED = 'declined';
    public const ENDED    = 'ended';

    /**
     * A request nobody answers is not left hanging all day: anything older than
     * this is treated as missed.
     */
    public const REQUEST_EXPIRES_AFTER_SECONDS = 90;

    /**
     * Both sides poll while a huddle is on. Gone quiet for this long means
     * somebody closed their laptop without saying goodbye.
     */
    public const GOES_QUIET_AFTER_SECONDS = 60;

    /**
     * Who is not asked by just anybody.
     *
     * The owner and HR see things the floor does not — pay, files, somebody's
     * leave request half typed. Being asked to put that on somebody else's
     * screen is not a question they should have to keep answering, so it is not
     * asked in the first place.
     */
    public const SHIELDED_ROLES = ['superadmin', 'hr'];

    /** And the two who may ask anybody, because helping is their job. */
    public const MAY_ASK_ANYBODY = ['superadmin', 'hr'];

    /**
     * May this person ask that one?
     *
     * Returns null when they may, and the reason when they may not — the same
     * answer serves the button on the page and the check behind it.
     */
    public static function whyNotAsk(User $asker, User $host): ?string
    {
        if ($asker->id === $host->id) {
            return 'That is you.';
        }

        if ($asker->company_id !== $host->company_id) {
            return 'They are not on your team.';
        }

        if (in_array($asker->role, self::MAY_ASK_ANYBODY, true)) {
            return null;
        }

        if (in_array($host->role, self::SHIELDED_ROLES, true)) {
            return $host->role === 'hr'
                ? 'HR is not asked from here. Message them instead.'
                : 'The owner is not asked from here. Message them instead.';
        }

        return null;
    }

    protected $fillable = [
        'company_id', 'host_id', 'guest_id', 'status',
        'can_control', 'answered_at', 'ended_at', 'ended_by',
    ];

    protected $casts = [
        'can_control' => 'boolean',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id')->withTrashed();
    }

    public function guest()
    {
        return $this->belongsTo(User::class, 'guest_id')->withTrashed();
    }

    public function signals()
    {
        return $this->hasMany(HuddleSignal::class);
    }

    /** Still going, in the sense that one of the two is waiting on the other. */
    public function isLive(): bool
    {
        return in_array($this->status, [self::PENDING, self::ACTIVE], true);
    }

    /** Asked for, and not yet answered or given up on. */
    public function scopeWaiting($query)
    {
        return $query->where('status', self::PENDING)
            ->where('created_at', '>=', now()->subSeconds(self::REQUEST_EXPIRES_AFTER_SECONDS));
    }

    public function scopeLive($query)
    {
        return $query->whereIn('status', [self::PENDING, self::ACTIVE]);
    }

    /** Either seat at the table. */
    public function scopeInvolving($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('host_id', $userId)->orWhere('guest_id', $userId);
        });
    }

    public function otherPerson(int $userId): ?User
    {
        return $this->host_id === $userId ? $this->guest : $this->host;
    }

    /**
     * What each side needs to know about it, and nothing else.
     */
    public function forUser(User $user): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'role' => $this->host_id === $user->id ? 'host' : 'guest',
            'canControl' => (bool) $this->can_control,
            'host' => $this->host?->display_name,
            'guest' => $this->guest?->display_name,
            'with' => $this->otherPerson($user->id)?->display_name,
            'startedAt' => $this->answered_at?->toIso8601String(),
            'endedBy' => $this->ended_by,
        ];
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Every hand-made change to somebody's attendance: who changed what, from what
 * to what, and why.
 *
 * Attendance decides pay, so a correction has to be answerable months later.
 */
class AttendanceAuditLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'attendance_id', 'user_id', 'changed_by', 'action',
        'attendance_date', 'changes', 'reason', 'ip_address',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }

    /**
     * Write one entry. `changes` is field => [old, new]; fields that did not
     * move are dropped, so the log reads as what actually happened.
     */
    public static function record(
        string $action,
        User $employee,
        ?Attendance $attendance,
        array $changes,
        ?string $reason = null,
        $date = null
    ): ?self {
        $changes = array_filter($changes, fn ($pair) => ($pair[0] ?? null) != ($pair[1] ?? null));

        if ($action === 'updated' && !$changes) {
            return null;                                   // nothing moved, nothing to log
        }

        return static::create([
            'attendance_id' => $attendance?->id,
            'user_id' => $employee->id,
            'changed_by' => auth()->id(),
            'action' => $action,
            'attendance_date' => $date ?: $attendance?->date,
            'changes' => $changes,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** The change list as short, readable lines for the screen. */
    public function lines(): array
    {
        /*
         * getAttribute, not $this->changes: Eloquent itself has a protected
         * $changes property (the attributes altered by the last save), and
         * inside the model that wins over the column of the same name — which
         * silently returned an empty list every time.
         */
        return collect($this->getAttribute('changes') ?? [])
            ->map(fn ($pair, $field) => sprintf(
                '%s: %s → %s',
                str_replace('_', ' ', $field),
                $pair[0] === null || $pair[0] === '' ? '—' : $pair[0],
                $pair[1] === null || $pair[1] === '' ? '—' : $pair[1]
            ))
            ->values()
            ->all();
    }
}

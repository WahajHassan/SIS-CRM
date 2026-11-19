<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A device somebody can clock in from with their fingerprint or face.
 *
 * No fingerprint is in here. What a browser hands over is a public key the
 * device made and keeps the other half of; the finger never leaves the laptop
 * that read it, and could not be reconstructed from this row if the whole table
 * were taken.
 *
 * What the row is for is the next clock-in: the device signs a challenge, and
 * the signature is checked against this key. Somebody clocking in for an absent
 * colleague would need that colleague's own laptop with that colleague's own
 * finger on it.
 */
class DeviceKey extends Model
{
    protected $fillable = [
        'user_id', 'credential_id', 'public_key', 'label', 'counter', 'last_used_at',
    ];

    protected $casts = [
        'counter' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['public_key'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Has this person set anything up at all? */
    public static function registeredFor(int $userId): bool
    {
        return static::where('user_id', $userId)->exists();
    }
}

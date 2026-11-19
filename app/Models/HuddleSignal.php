<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of the few messages two browsers swap before they can talk directly.
 *
 * An offer, an answer, and a handful of network candidates. They are written
 * here and read back by polling, which is slower than a socket and entirely
 * good enough: it happens once, at the start, and then the two browsers are
 * connected and this table stops being used until the next huddle.
 *
 * No company scope: a signal is reached only through its huddle, which is
 * scoped, and the controller checks the reader is one of the two people in it.
 */
class HuddleSignal extends Model
{
    protected $fillable = ['huddle_id', 'from_id', 'kind', 'payload'];

    public function huddle()
    {
        return $this->belongsTo(Huddle::class);
    }
}

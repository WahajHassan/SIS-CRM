<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's one emoji on one message.
 *
 * The (message, user, emoji) unique index in the migration is what makes
 * toggling idempotent: a double-click cannot leave two of the same reaction.
 */
class MessageReaction extends Model
{
    protected $fillable = ['message_id', 'user_id', 'emoji'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\BelongsToCompany;

class Message extends Model
{
    use BelongsToCompany;

    /*
     * Soft deleted rather than removed: a deleted message still occupies its
     * place in the conversation so the thread reads correctly, and the client
     * renders a "this message was deleted" placeholder in its place.
     */
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'conversation_id', 'sender_id', 'body',
        'attachment_path', 'attachment_type', 'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Reactions collapsed for the client: one entry per emoji with its count and
     * whether the current user is among them, which is all the UI needs to draw
     * the pills and highlight the user's own.
     */
    public function reactionSummary(): array
    {
        $me = auth()->id();

        return $this->reactions
            ->groupBy('emoji')
            ->map(function ($group, $emoji) use ($me) {
                return [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'mine'  => $group->contains('user_id', $me),
                ];
            })
            ->values()
            ->all();
    }
}

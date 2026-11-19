<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * What somebody said about a piece of training.
 *
 * Questions mostly: the point of letting the floor answer back is that the next
 * person with the same question finds it already answered.
 */
class TrainingComment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'training_id', 'user_id', 'body'];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** May this person take it down? Their own, or anybody's if they run it. */
    public function canBeRemovedBy(?User $user): bool
    {
        return $user && ($user->id === $this->user_id || Training::canManage($user));
    }

    public function forFeed(?User $viewer = null): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->user->display_name ?? 'Someone',
            'ago' => $this->created_at->diffForHumans(),
            'mine' => $viewer && $viewer->id === $this->user_id,
            'can_remove' => $this->canBeRemovedBy($viewer),
        ];
    }
}

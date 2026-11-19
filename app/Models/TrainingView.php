<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One person having watched one piece of training.
 *
 * Not company scoped: it hangs off a training that already is, and the pair of
 * ids is unique, so there is nothing here to leak between companies.
 */
class TrainingView extends Model
{
    protected $fillable = ['training_id', 'user_id', 'watched_at'];

    protected $casts = ['watched_at' => 'datetime'];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

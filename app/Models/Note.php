<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note in the user's personal notepad.
 *
 * Scoped twice over: BelongsToCompany keeps it inside the tenant, and every
 * query in NoteController additionally pins it to the signed-in user. A note is
 * private to the person who wrote it — not shared with their company.
 */
class Note extends Model
{
    protected $fillable = ['user_id', 'company_id', 'title', 'body', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    use BelongsToCompany;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Something the office is telling everybody, shown on the dashboard.
 *
 * Separate from the bell notifications: a notification is read once and gone,
 * an announcement stays on the board until it is taken down.
 */
class Announcement extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'title', 'body', 'is_pinned', 'is_active', 'notified', 'created_by'];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
        'notified' => 'boolean',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * The board, newest first.
     *
     * Newest first and nothing else: an announcement that arrives while somebody
     * is looking at the page appears at the top, and it is still at the top after
     * a reload. Ordering pinned items above the rest made those two disagree —
     * a fresh announcement jumped to the top, then dropped below the pinned one
     * the next time the page loaded.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_active', true)->orderByDesc('created_at');
    }

    /**
     * What the dashboard shows: the newest few, plus anything pinned however old
     * it is. Pinning no longer moves something to the top; it keeps it on the
     * board once newer announcements have pushed it past the limit.
     */
    public static function board(int $limit = 20)
    {
        $newest = static::visible()->with('author')->limit($limit)->get();
        $pinned = static::visible()->with('author')->where('is_pinned', true)->get();

        return $newest->concat($pinned)
            ->unique('id')
            ->sortByDesc('created_at')
            ->values();
    }

    /** Who may post and take down announcements. */
    public static function canManage(?User $user): bool
    {
        return $user && ($user->isAdmin() || $user->isHr());
    }

    /** The shape the dashboard's own script expects. */
    public function forFeed(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'pinned' => (bool) $this->is_pinned,
            'author' => $this->author->name ?? 'Management',
            'when' => $this->created_at->diffForHumans(),
            'date' => $this->created_at->format('d M Y, h:i A'),
        ];
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something the floor is being taught.
 *
 * A recording, a voice note, or something written down — one row either way,
 * because the team reads them in one list and talks about them the same way.
 */
class Training extends Model
{
    use BelongsToCompany;

    public const KINDS = [
        'video' => 'Recording',
        'voice' => 'Voice note',
        'text' => 'Written',
    ];

    protected $fillable = [
        'company_id', 'created_by', 'title', 'kind', 'body',
        'media_path', 'media_url', 'media_mime', 'duration_seconds',
        'department_id', 'is_published', 'is_pinned',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function comments()
    {
        return $this->hasMany(TrainingComment::class)->oldest();
    }

    public function views()
    {
        return $this->hasMany(TrainingView::class);
    }

    /** Who may put training up and take it down. */
    public static function canManage(?User $user): bool
    {
        return $user && ($user->isAdmin() || $user->isHr());
    }

    /**
     * What this person is meant to see.
     *
     * Training aimed at a department is for that department; anything with no
     * department named is for everybody. Whoever manages it sees all of it,
     * published or not, because they are the ones publishing.
     */
    public function scopeFor($query, ?User $user)
    {
        if (self::canManage($user)) {
            return $query;
        }

        $departmentId = $user?->employeeProfile?->department_id;

        return $query->where('is_published', true)
            ->where(function ($q) use ($departmentId) {
                $q->whereNull('department_id');

                if ($departmentId) {
                    $q->orWhere('department_id', $departmentId);
                }
            });
    }

    /** Newest first, with anything pinned held at the top. */
    public function scopeNewestFirst($query)
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('created_at');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function hasMedia(): bool
    {
        return (bool) ($this->media_path || $this->media_url);
    }

    /** Where the player should point. */
    public function mediaSrc(): ?string
    {
        if ($this->media_path) {
            return asset('storage/' . $this->media_path);
        }

        return $this->media_url ?: null;
    }

    /**
     * A link that can go in a frame, for the places a recording usually lives.
     *
     * YouTube, Vimeo and Google Drive each serve a watch page that refuses to be
     * framed and an embed address that does not, so the link somebody pastes is
     * turned into the one that works.
     */
    public function embedUrl(): ?string
    {
        $url = $this->media_url;

        if (!$url) {
            return null;
        }

        if (preg_match('~youtu\.be/([\w-]+)~', $url, $m) || preg_match('~youtube\.com/watch\?v=([\w-]+)~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        if (preg_match('~youtube\.com/embed/([\w-]+)~', $url)) {
            return $url;
        }

        if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        if (preg_match('~drive\.google\.com/file/d/([\w-]+)~', $url, $m)) {
            return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
        }

        if (preg_match('~loom\.com/share/([\w-]+)~', $url, $m)) {
            return 'https://www.loom.com/embed/' . $m[1];
        }

        return null;
    }

    /** True when the file can be played by the browser's own player. */
    public function isPlayableFile(): bool
    {
        return (bool) $this->media_path;
    }

    public function durationLabel(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return $minutes . ':' . str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The written part, with the few marks that are worth having.
     *
     * Everything is escaped first and then a short, fixed set of marks is turned
     * into tags — ==highlighted==, **bold**, bullet lines. Anything typed that
     * looks like a tag stays text, so a paste from anywhere is safe to show.
     */
    public function bodyHtml(): string
    {
        $text = e((string) $this->body);

        $text = preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/^\s*[-•]\s+(.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
        $text = preg_replace('/<\/li>\s*<li>/', '</li><li>', $text);

        return nl2br($text);
    }

    /** A line for the card, without the formatting marks. */
    public function excerpt(int $length = 120): string
    {
        $plain = preg_replace('/[=*]{2}/', '', (string) $this->body);

        return Str::limit(trim(preg_replace('/\s+/', ' ', $plain)), $length);
    }

    public function watchedBy(?User $user): bool
    {
        return $user && $this->views()->where('user_id', $user->id)->exists();
    }

    /** The shape the dashboard's own script expects. */
    public function forFeed(?User $viewer = null): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->kind,
            'kind_label' => $this->kindLabel(),
            'excerpt' => $this->excerpt(),
            'author' => $this->author->display_name ?? 'Management',
            'ago' => $this->created_at->diffForHumans(),
            'pinned' => $this->is_pinned,
            'comments' => $this->comments()->count(),
            'watched' => $this->watchedBy($viewer),
            'url' => route('admin.training.show', $this->id),
        ];
    }
}

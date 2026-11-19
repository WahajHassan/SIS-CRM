<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Somebody's best on one of the break-room games.
 *
 * The games are what the floor plays for five minutes between calls, so the
 * only number worth keeping is the best one. Which direction "best" runs in
 * depends on the game — a bigger score at 2048, fewer moves at Memory — and
 * that is written down here rather than in the page, so the board and the
 * saving can never disagree about who is winning.
 */
class GameScore extends Model
{
    use BelongsToCompany;

    /**
     * The games, and how each one is scored.
     *
     * Adding a game is adding a line here: the page draws its card from this,
     * the controller validates against it, and the board sorts by it.
     */
    public const GAMES = [
        '2048' => [
            'name' => '2048',
            'blurb' => 'Slide the tiles together until one of them says 2048.',
            'icon' => 'fa-table-cells-large',
            'unit' => 'points',
            'higher_is_better' => true,
        ],
        'snake' => [
            'name' => 'Snake',
            'blurb' => 'Eat, grow, and do not bite yourself.',
            'icon' => 'fa-staff-snake',
            'unit' => 'points',
            'higher_is_better' => true,
        ],
        'memory' => [
            'name' => 'Memory',
            'blurb' => 'Turn the cards over two at a time and find the pairs.',
            'icon' => 'fa-clone',
            'unit' => 'moves',
            'higher_is_better' => false,
        ],
    ];

    protected $fillable = ['company_id', 'user_id', 'game', 'score'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public static function isAGame(string $game): bool
    {
        return array_key_exists($game, self::GAMES);
    }

    public static function higherIsBetter(string $game): bool
    {
        return (bool) (self::GAMES[$game]['higher_is_better'] ?? true);
    }

    /**
     * Keep this score only if it beats what is already there.
     *
     * Returns true when it did, so the page can say so rather than guessing.
     */
    public static function keepIfBest(User $user, string $game, int $score): bool
    {
        $existing = static::where('user_id', $user->id)->where('game', $game)->first();

        if (!$existing) {
            static::create([
                'user_id' => $user->id,
                'game' => $game,
                'score' => $score,
            ]);

            return true;
        }

        $beatsIt = static::higherIsBetter($game)
            ? $score > $existing->score
            : $score < $existing->score;

        if ($beatsIt) {
            $existing->update(['score' => $score]);
        }

        return $beatsIt;
    }

    /** The top few at one game, for the board beside it. */
    public static function board(string $game, int $howMany = 5)
    {
        return static::where('game', $game)
            ->with('user:id,name,sales_performer_id')
            ->orderBy('score', static::higherIsBetter($game) ? 'desc' : 'asc')
            ->limit($howMany)
            ->get();
    }
}

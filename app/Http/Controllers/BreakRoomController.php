<?php

namespace App\Http\Controllers;

use App\Models\GameScore;
use Illuminate\Http\Request;

/**
 * The break room: something to watch, and something to play.
 *
 * Two small screens for the five minutes between calls. Neither touches
 * anything the CRM does for a living — the watching is a link opened in its own
 * window and a player for links that allow it, and the games are played in the
 * page and remember only a best score.
 *
 * Both are ordinary sections, so an admin can switch either of them off for a
 * person the same way as any other part of the CRM.
 */
class BreakRoomController extends Controller
{
    /**
     * Netflix, and anything else worth putting on.
     *
     * Netflix answers every request to be shown inside another site with a
     * refusal — x-frame-options: DENY — and its player will not run in a frame
     * it does not own either. So it opens in a window of its own, and the panel
     * below it is for the links that do allow it.
     */
    public function watch()
    {
        return view('admin.breakroom.watch');
    }

    public function games()
    {
        $me = auth()->user();

        $mine = GameScore::where('user_id', $me->id)
            ->pluck('score', 'game')
            ->all();

        $boards = [];
        foreach (array_keys(GameScore::GAMES) as $game) {
            $boards[$game] = GameScore::board($game);
        }

        return view('admin.breakroom.games', [
            'games' => GameScore::GAMES,
            'mine' => $mine,
            'boards' => $boards,
        ]);
    }

    /**
     * A score, at the end of a game.
     *
     * Only kept when it beats what that person already has, and the reply says
     * whether it did so the page can make something of it.
     */
    public function score(Request $request)
    {
        $me = auth()->user();

        $data = $request->validate([
            'game' => ['required', 'string', 'max:32'],
            // A number big enough for any of these and small enough not to be
            // somebody having a go at the column.
            'score' => ['required', 'integer', 'min:0', 'max:9999999'],
        ]);

        abort_unless(GameScore::isAGame($data['game']), 404);

        $isBest = GameScore::keepIfBest($me, $data['game'], (int) $data['score']);

        return response()->json([
            'best' => $isBest,
            'mine' => GameScore::where('user_id', $me->id)->where('game', $data['game'])->value('score'),
            'board' => GameScore::board($data['game'])->map(fn (GameScore $row) => [
                'name' => $row->user?->display_name ?? 'Somebody',
                'score' => $row->score,
                'isMe' => $row->user_id === $me->id,
            ])->values(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\GameScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The break-room games keep one number per person: their best.
 *
 * Which way "best" runs depends on the game — more points at 2048, fewer moves
 * at Memory — and the board has to sort the right way round for each. The page
 * sends a number at the end of a game and is in no position to know whether it
 * beat anything, so that answer comes from here.
 */
class GameScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $ali;
    private User $bilal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ali = $this->person('Ali Agent');
        $this->bilal = $this->person('Bilal Agent');
    }

    private function person(string $name): User
    {
        $handle = strtolower(str_replace(' ', '', $name));

        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
        ]);
    }

    private function send(User $who, string $game, int $score)
    {
        return $this->actingAs($who)->postJson(route('admin.games.score'), [
            'game' => $game,
            'score' => $score,
        ]);
    }

    public function test_a_first_score_is_kept(): void
    {
        $this->send($this->ali, '2048', 1200)
            ->assertOk()
            ->assertJsonPath('best', true)
            ->assertJsonPath('mine', 1200);

        $this->assertDatabaseHas('game_scores', [
            'user_id' => $this->ali->id,
            'game' => '2048',
            'score' => 1200,
        ]);
    }

    public function test_a_better_score_replaces_it_and_a_worse_one_does_not(): void
    {
        $this->send($this->ali, '2048', 1200);

        $this->send($this->ali, '2048', 900)
            ->assertOk()
            ->assertJsonPath('best', false)
            ->assertJsonPath('mine', 1200);

        $this->send($this->ali, '2048', 2048)
            ->assertOk()
            ->assertJsonPath('best', true)
            ->assertJsonPath('mine', 2048);

        // One row per person per game, however many times they play.
        $this->assertSame(1, GameScore::where('user_id', $this->ali->id)->where('game', '2048')->count());
    }

    public function test_at_memory_fewer_moves_is_the_better_score(): void
    {
        $this->send($this->ali, 'memory', 20);

        $this->send($this->ali, 'memory', 26)
            ->assertOk()
            ->assertJsonPath('best', false)
            ->assertJsonPath('mine', 20);

        $this->send($this->ali, 'memory', 14)
            ->assertOk()
            ->assertJsonPath('best', true)
            ->assertJsonPath('mine', 14);
    }

    public function test_the_board_is_sorted_the_right_way_round_for_each_game(): void
    {
        $this->send($this->ali, '2048', 800);
        $this->send($this->bilal, '2048', 2400);

        $this->assertSame(
            ['Bilal Agent', 'Ali Agent'],
            GameScore::board('2048')->map(fn (GameScore $row) => $row->user->name)->all(),
            'at 2048 the biggest score leads'
        );

        $this->send($this->ali, 'memory', 30);
        $this->send($this->bilal, 'memory', 18);

        $this->assertSame(
            ['Bilal Agent', 'Ali Agent'],
            GameScore::board('memory')->map(fn (GameScore $row) => $row->user->name)->all(),
            'at Memory the fewest moves leads'
        );
    }

    public function test_the_reply_carries_the_board_and_says_which_row_is_mine(): void
    {
        $this->send($this->bilal, 'snake', 300);

        $reply = $this->send($this->ali, 'snake', 500)->assertOk();

        $reply->assertJsonPath('board.0.name', 'Ali Agent')
            ->assertJsonPath('board.0.isMe', true)
            ->assertJsonPath('board.1.name', 'Bilal Agent')
            ->assertJsonPath('board.1.isMe', false);
    }

    public function test_a_game_nobody_has_heard_of_is_refused(): void
    {
        $this->send($this->ali, 'solitaire', 10)->assertNotFound();

        $this->assertDatabaseCount('game_scores', 0);
    }

    public function test_a_score_that_is_not_a_sensible_number_is_refused(): void
    {
        $this->actingAs($this->ali)
            ->postJson(route('admin.games.score'), ['game' => '2048', 'score' => -5])
            ->assertStatus(422);

        $this->actingAs($this->ali)
            ->postJson(route('admin.games.score'), ['game' => '2048', 'score' => 99999999999])
            ->assertStatus(422);

        $this->actingAs($this->ali)
            ->postJson(route('admin.games.score'), ['game' => '2048', 'score' => 'lots'])
            ->assertStatus(422);

        $this->assertDatabaseCount('game_scores', 0);
    }

    public function test_one_person_cannot_write_over_another_persons_best(): void
    {
        $this->send($this->bilal, '2048', 5000);
        $this->send($this->ali, '2048', 100);

        // Two rows, each belonging to the person who played.
        $this->assertSame(5000, GameScore::where('user_id', $this->bilal->id)->value('score'));
        $this->assertSame(100, GameScore::where('user_id', $this->ali->id)->value('score'));
    }

    public function test_the_games_page_shows_what_this_person_has_done(): void
    {
        $this->send($this->ali, '2048', 1500);

        $this->actingAs($this->ali)
            ->get(route('admin.games'))
            ->assertOk()
            ->assertSee('2048')
            ->assertSee('1,500');
    }

    public function test_the_watching_page_opens(): void
    {
        $this->actingAs($this->ali)
            ->get(route('admin.netflix'))
            ->assertOk()
            ->assertSee('Netflix');
    }
}

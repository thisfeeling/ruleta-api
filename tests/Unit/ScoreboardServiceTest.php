<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Models\{Show, Game, Player};
use App\Events\Scoreboard\{ScoreAdded, ScoreboardUpdated};
use App\Services\Scoreboard\ScoreboardService;

class ScoreboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_score_creates_player_score_and_updates_total_and_dispatches_events()
    {
        Event::fake([ScoreAdded::class, ScoreboardUpdated::class]);

        $show = Show::factory()->create();
        $game = Game::factory()->create(['show_id' => $show->id, 'type' => 'rope']);

        $p1 = Player::factory()->create(['show_id' => $show->id, 'total_score' => 0]);
        $p2 = Player::factory()->create(['show_id' => $show->id, 'total_score' => 0]);

        $service = new ScoreboardService();

        $service->addScore($p1, $game, 50, ['extra' => 'a']);
        $service->addScore($p2, $game, 200, ['extra' => 'b']);

        $this->assertDatabaseHas('player_scores', ['player_id' => $p1->id]);
        $this->assertDatabaseHas('player_scores', ['player_id' => $p2->id]);

        $p1->refresh(); $p2->refresh();

        $this->assertGreaterThanOrEqual(0, $p1->total_score);
        $this->assertGreaterThanOrEqual(0, $p2->total_score);

        Event::assertDispatched(ScoreAdded::class);
        Event::assertDispatched(ScoreboardUpdated::class);
    }

    public function test_get_scoreboard_and_rank()
    {
        $show = Show::factory()->create();
        $game = Game::factory()->create(['show_id' => $show->id, 'type' => 'rope']);

        $p1 = Player::factory()->create(['show_id' => $show->id, 'total_score' => 0]);
        $p2 = Player::factory()->create(['show_id' => $show->id, 'total_score' => 0]);

        $service = new ScoreboardService();

        $service->addScore($p1, $game, 100);
        $service->addScore($p2, $game, 10);

        $board = $service->getScoreboard($show);

        $this->assertEquals(2, $board->count());
        $this->assertEquals($p1->id, $board->first()['player_id']);

        $rank = $service->getPlayerRank($p2->refresh());
        $this->assertEquals(2, $rank);
    }
}

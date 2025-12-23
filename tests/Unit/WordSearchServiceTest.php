<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Game, Player, WordSearchGrid};
use Illuminate\Support\Facades\Event;

class WordSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_grid_dispatches_event()
    {
        Event::fake();

        $game = Game::factory()->create(['type' => 'word_search']);

        app(\App\Services\Game\WordSearchService::class)->start($game);

        Event::assertDispatched(\App\Events\Games\WordSearchGridGenerated::class, function ($event) use ($game) {
            return $event->game->id === $game->id && is_array($event->grid->grid);
        });
    }

    public function test_record_find_dispatches_event()
    {
        Event::fake();

        $game = Game::factory()->create(['type' => 'word_search']);
        $player = Player::factory()->create(['show_id' => $game->show_id, 'status' => 'active']);
        $grid = WordSearchGrid::create(['game_id' => $game->id, 'grid' => array_fill(0,15, array_fill(0,15,'')), 'words' => ['FAMILIA'], 'time_limit_seconds' => 180]);

        app(\App\Services\Game\WordSearchService::class)->handlePlayerAction($game, $player, ['type' => 'found_word', 'word' => 'FAMILIA', 'time_elapsed_ms' => 1000]);

        Event::assertDispatched(\App\Events\Games\WordFound::class, function ($event) use ($player, $grid) {
            return $event->player->id === $player->id && $event->grid->id === $grid->id && $event->word === 'FAMILIA';
        });
    }
}

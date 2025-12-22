<?php

namespace Database\Seeders;

use App\Models\AudioTrack;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerScore;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create some users
        $users = User::factory()->count(20)->create();

        // Create 2 shows
        $shows = Show::factory()->count(2)->create();

        // For each show, create games and players
        foreach ($shows as $show) {
            // Create players for the show using existing users
            $players = Player::factory()->count(12)->create([
                'show_id' => $show->id,
            ]);

            // Create a couple of games
            $games = Game::factory()->count(3)->create([
                'show_id' => $show->id,
            ]);

            // Create scores for players in first game
            foreach ($players as $player) {
                PlayerScore::factory()->create([
                    'player_id' => $player->id,
                    'game_id' => $games->first()->id,
                    'game_type' => $games->first()->type,
                    'raw_score' => rand(0,1000),
                    'normalized_score' => rand(0,1000),
                ]);
            }
        }

        // Create some audio tracks
        AudioTrack::factory()->count(10)->create();
    }
}

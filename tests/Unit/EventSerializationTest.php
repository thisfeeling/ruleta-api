<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

use App\Models\{Show, Game, Player, Achievement, PlayerAchievement, PlayerScore, AuditLog, GameInstruction};
use App\Events\Show\{ShowStarted, PhaseChanged};
use App\Events\Games\{GameStarted, PlayerEliminated};
use App\Events\Achievements\AchievementUnlocked;
use App\Events\Audio\TrackStarted;
use App\Events\Scoreboard\ScoreAdded;
use App\Events\Instructions\InstructionsRequired;
use App\Events\Audit\AuditLogCreated;

class EventSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_started_serializes()
    {
        $show = Show::factory()->create([
            'started_at' => now(),
            'current_phase' => 'lobby',
            'current_player_count' => 5,
        ]);

        $event = new ShowStarted($show);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('show.started', $event->broadcastAs());

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('show_id', $payload);
        $this->assertEquals($show->id, $payload['show_id']);
        $this->assertArrayHasKey('started_at', $payload);
        $this->assertArrayHasKey('current_phase', $payload);
        $this->assertArrayHasKey('player_count', $payload);

        $channels = $event->broadcastOn();
        $this->assertIsArray($channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
    }

    public function test_phase_changed_serializes()
    {
        $show = Show::factory()->create();
        $event = new PhaseChanged($show, 'lobby', 'millionaire');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('show.phase_changed', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals('lobby', $payload['from_phase']);
        $this->assertEquals('millionaire', $payload['to_phase']);

        $this->assertInstanceOf(Channel::class, $event->broadcastOn()[0]);
    }

    public function test_game_started_serializes()
    {
        $game = Game::factory()->create([
            'is_bonus' => true,
            'players_at_start' => 10,
        ]);

        $event = new GameStarted($game);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('game.started', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals($game->id, $payload['game_id']);
        $this->assertTrue($payload['is_bonus']);

        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertInstanceOf(Channel::class, $channels[1]);
    }

    public function test_player_eliminated_serializes()
    {
        $player = Player::factory()->create();
        $game = Game::factory()->create(['show_id' => $player->show_id]);

        $event = new PlayerEliminated($player, $game, 'timeout');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('player.eliminated', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals($player->id, $payload['player_id']);
        $this->assertEquals($player->player_number, $payload['player_number']);
        $this->assertEquals('timeout', $payload['reason']);

        $channels = $event->broadcastOn();
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertInstanceOf(Channel::class, $channels[1]);
    }

    public function test_achievement_unlocked_serializes()
    {
        $player = Player::factory()->create();
        $achievement = Achievement::create([
            'key' => 'first_blood',
            'name_es' => 'Primera Sangre',
            'name_en' => 'First Blood',
            'description_es' => 'Logro por la primera acción',
            'description_en' => 'Achievement for first action',
            'points' => 10,
        ]);

        $playerAchievement = PlayerAchievement::create([
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
            'show_id' => $player->show_id,
            'unlocked_at' => now(),
        ]);

        $event = new AchievementUnlocked($playerAchievement);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('achievement.unlocked', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals($player->id, $payload['player_id']);
        $this->assertEquals($achievement->id, $payload['achievement']['id']);
    }

    public function test_track_started_serializes()
    {
        $event = new TrackStarted(1, 'narration_1', 'voice', 'https://s3.example/test.mp3', 12000);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('audio.track_started', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals('narration_1', $payload['track_key']);
        $this->assertEquals('voice', $payload['channel']);
        $this->assertStringStartsWith('https://', $payload['signed_url']);
    }

    public function test_score_added_serializes()
    {
        $score = \App\Models\PlayerScore::factory()->create();

        $event = new ScoreAdded($score);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('scoreboard.score_added', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals($score->score, $payload['score']);
        $this->assertEquals($score->player->id, $payload['player_id']);
    }

    public function test_instructions_required_serializes()
    {
        $game = Game::factory()->create();
        // Create GameInstruction using migration schema
        $instruction = \App\Models\GameInstruction::create([
            'game_type' => $game->type ?? 'millionaire',
            'content_es' => 'Haz esto',
            'content_en' => 'Do this',
            'estimated_duration_seconds' => 10,
        ]);

        $event = new InstructionsRequired($game, $instruction);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('instructions.required', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals($game->id, $payload['game_id']);
        $this->assertArrayHasKey('instruction', $payload);
    }

    public function test_audit_log_created_summarizes_player_elimination()
    {
        $show = Show::factory()->create();

        $log = AuditLog::create([
            'show_id' => $show->id,
            'event_type' => 'player_eliminated',
            'payload' => ['player_number' => 5],
            'created_at' => now(),
        ]);

        $event = new AuditLogCreated($log);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertEquals('audit.log_created', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals('Player #5 eliminated', $payload['payload_summary']);
    }
}

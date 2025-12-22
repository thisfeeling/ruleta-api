<?php

namespace Tests\Feature;

use App\Models\AudioPlay;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_supervisor_cannot_access_supervisor_routes()
    {
        $user = User::factory()->create(['role' => 'player']);
        $show = Show::factory()->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/supervisor/shows/' . $show->id . '/start');

        $response->assertStatus(403);
    }

    public function test_supervisor_can_control_show()
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $show = Show::factory()->create(['status' => 'lobby']);

        $token = $user->createToken('test')->plainTextToken;

        $start = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/supervisor/shows/' . $show->id . '/start');

        $start->assertStatus(200)->assertJsonPath('show.status', 'in_progress');

        $pause = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/supervisor/shows/' . $show->id . '/pause');

        $pause->assertStatus(200)->assertJsonPath('show.status', 'paused');

        $end = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/supervisor/shows/' . $show->id . '/end');

        $end->assertStatus(200)->assertJsonPath('show.status', 'completed');
    }

    public function test_supervisor_can_approve_and_reject_audio()
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $show = Show::factory()->create(['status' => 'lobby']);

        $audio = AudioPlay::factory()->create(['show_id' => $show->id, 'approved' => null]);

        $token = $user->createToken('test')->plainTextToken;

        $approve = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/supervisor/audio/' . $audio->id . '/approve');

        $approve->assertStatus(200)->assertJsonPath('audio.approved', true);

        $reject = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/supervisor/audio/' . $audio->id . '/reject');

        $reject->assertStatus(200)->assertJsonPath('audio.approved', false);
    }
}

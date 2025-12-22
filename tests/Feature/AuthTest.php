<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_logout()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'supervisor',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'token']);

        $token = $response->json('token');

        $me = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $me->assertStatus(200)->assertJsonFragment(['email' => 'test@example.com']);

        $logout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $logout->assertStatus(200);
    }

    public function test_player_can_join_and_reconnect_with_pin()
    {
        $show = Show::factory()->create([
            'status' => 'lobby',
            'max_players' => 10,
            'current_player_count' => 0,
        ]);

        $response = $this->postJson('/api/auth/player/join', [
            'show_id' => $show->id,
            'name' => 'Player One',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'player', 'token', 'pin']);

        $pin = $response->json('pin');

        $reconnect = $this->postJson('/api/auth/player/reconnect', ['pin' => $pin]);

        $reconnect->assertStatus(200)->assertJsonStructure(['user', 'player', 'token']);
    }
}

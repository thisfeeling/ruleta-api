<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_expires_and_cannot_be_used()
    {
        config(['auth.token_expiration_minutes' => 60]);

        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        $token = $response->json('token');

        // Manually set token expiration in DB to the past
        // Set the expires_at for the token(s) belonging to this user to the past
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->update(['expires_at' => now()->subMinutes(5)]);

        $me = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $me->assertStatus(401);
    }
}

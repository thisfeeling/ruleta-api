<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Player\PINGeneratorService;
use App\Models\Player;

class PINGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_unique_4_digit_pin()
    {
        $service = new PINGeneratorService();

        // Create an existing player with a pin to avoid collisions
        Player::factory()->create(['pin' => '1234']);

        $pin = $service->generate();

        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
        $this->assertNotSame('1234', $pin);

        // Ensure uniqueness across DB
        $this->assertFalse(Player::where('pin', $pin)->exists());
    }

    public function test_validate_and_find_player_by_pin()
    {
        $service = new PINGeneratorService();

        $player = Player::factory()->create(['pin' => '0001', 'status' => 'active']);

        $this->assertTrue($service->validate('0001'));
        $this->assertFalse($service->validate('12')); // invalid length

        $found = $service->findPlayer('0001');

        $this->assertNotNull($found);
        $this->assertSame($player->id, $found->id);

        // Inactive status should not return player
        $player->update(['status' => 'eliminated']);
        $this->assertNull($service->findPlayer('0001'));
    }
}

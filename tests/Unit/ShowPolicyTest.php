<?php

namespace Tests\Unit;

use App\Models\Show;
use App\Models\User;
use App\Policies\ShowPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_control_show()
    {
        $policy = new ShowPolicy();

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $show = Show::factory()->create();

        $this->assertTrue($policy->control($supervisor, $show));
    }

    public function test_player_cannot_control_show()
    {
        $policy = new ShowPolicy();

        $player = User::factory()->create(['role' => 'player']);
        $show = Show::factory()->create();

        $this->assertFalse($policy->control($player, $show));
    }
}

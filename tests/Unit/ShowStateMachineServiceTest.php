<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Game\ShowStateMachineService;
use App\Models\Show;

class ShowStateMachineServiceTest extends TestCase
{
    public function test_get_next_phase(): void
    {
        $service = new ShowStateMachineService();

        $show = new Show(['current_phase' => 'lobby']);

        $this->assertEquals('millionaire_1', $service->getNextPhase($show));

        $show->current_phase = 'roulette';
        $this->assertEquals('results', $service->getNextPhase($show));

        $show->current_phase = 'results';
        $this->assertNull($service->getNextPhase($show));
    }

    public function test_can_insert_bonus_game(): void
    {
        $service = new ShowStateMachineService();

        $show = new Show(['current_phase' => 'millionaire_1']);

        $this->assertTrue($service->canInsertBonusGame($show));

        $show->current_phase = 'results';
        $this->assertFalse($service->canInsertBonusGame($show));
    }
}

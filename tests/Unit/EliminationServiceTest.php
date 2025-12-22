<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Game\EliminationService;
use App\Models\Player;
use Illuminate\Support\Collection;

class EliminationServiceTest extends TestCase
{
    public function test_calculate_elimination_count(): void
    {
        $service = new EliminationService();

        $this->assertEquals(4, $service->calculateEliminationCount(10, 'millionaire'));
        $this->assertEquals(3, $service->calculateEliminationCount(10, 'rope'));
        $this->assertEquals(3, $service->calculateEliminationCount(10, 'spell'));
        $this->assertEquals(9, $service->calculateEliminationCount(10, 'roulette'));
        $this->assertEquals(0, $service->calculateEliminationCount(10, 'unknown'));
    }

    public function test_get_worst_performers(): void
    {
        $service = new EliminationService();

        $players = collect([]);

        $p1 = new Player(); $p1->id = 1; $p1->total_score = 100;
        $p2 = new Player(); $p2->id = 2; $p2->total_score = 50;
        $p3 = new Player(); $p3->id = 3; $p3->total_score = 75;
        $p4 = new Player(); $p4->id = 4; $p4->total_score = 10;

        $players = collect([$p1, $p2, $p3, $p4]);

        $worstTwo = $service->getWorstPerformers($players, 2);

        $this->assertInstanceOf(Collection::class, $worstTwo);
        $this->assertEquals([4,2], $worstTwo->pluck('id')->values()->all());
    }
}

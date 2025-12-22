<?php

namespace App\Services\Game;

use App\Models\Show;
use App\Models\Game as GameModel;
use App\Events\Show\PhaseChanged;

class ShowStateMachineService
{
    protected array $phases = [
        'lobby',
        'millionaire_1',
        'spell_1',
        'rope',
        'millionaire_2',
        'spell_2',
        'roulette',
        'results',
    ];

    public function transitionTo(Show $show, string $phase): void
    {
        $oldPhase = $show->current_phase;

        $show->update(['current_phase' => $phase]);

        event(new PhaseChanged($show, $oldPhase, $phase));
    }

    public function getNextPhase(Show $show): ?string
    {
        $currentIndex = array_search($show->current_phase, $this->phases);

        if ($currentIndex === false || $currentIndex >= count($this->phases) - 1) {
            return null;
        }

        return $this->phases[$currentIndex + 1];
    }

    public function canInsertBonusGame(Show $show): bool
    {
        return in_array($show->current_phase, [
            'millionaire_1',
            'spell_1',
            'rope',
            'millionaire_2',
        ]);
    }

    public function startBonusGame(Show $show, string $type): GameModel
    {
        return GameModel::create([
            'show_id' => $show->id,
            'type' => $type,
            'round_number' => 1,
            'status' => 'pending',
            'is_bonus' => true,
        ]);
    }
}
